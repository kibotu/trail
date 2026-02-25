/**
 * Embed Page Initialization
 *
 * Read-only widget for embedding a user's Trail feed.
 * Overrides card-template.js behaviors to open links in new tabs
 * and fixes clipboard/share for cross-origin iframe context.
 */

(async function () {
    'use strict';

    await loadConfig();

    const body = document.body;
    const nickname = body.dataset.nickname;
    const showHeader = body.dataset.showHeader === '1';
    const showSearch = body.dataset.showSearch === '1';
    const limit = parseInt(body.dataset.limit, 10) || 20;
    const baseUrl = body.dataset.baseUrl || '';

    const sessionState = { isLoggedIn: false, userId: null };

    const entriesContainer = document.getElementById('entriesContainer');
    const loadingElement = document.getElementById('loading');
    const endMessage = document.getElementById('endMessage');

    if (!entriesContainer || !loadingElement || !endMessage) return;

    // ── Auto-resize: notify parent of height changes ──

    let lastPostedHeight = 0;

    function postHeight() {
        const height = document.documentElement.scrollHeight;
        if (height <= 0) return;
        // Add 1px buffer to prevent sub-pixel rounding scrollbars
        const buffered = height + 1;
        if (buffered === lastPostedHeight) return;
        lastPostedHeight = buffered;
        try {
            window.parent.postMessage(
                { type: 'trail-embed-resize', height: buffered },
                '*'
            );
        } catch (_) { /* cross-origin safety */ }
    }

    const resizeObserver = new ResizeObserver(postHeight);
    resizeObserver.observe(document.body);
    window.addEventListener('load', postHeight);

    // Re-post after images/fonts settle
    window.addEventListener('load', () => {
        setTimeout(postHeight, 300);
        setTimeout(postHeight, 1000);
    });

    // ── Profile header (optional) ──

    if (showHeader && typeof UserProfileManager !== 'undefined') {
        const upm = new UserProfileManager({
            nickname: nickname,
            sessionState: sessionState,
            apiBase: '/api'
        });
        upm.init();
    }

    // ── Entries ──

    const entriesManager = new EntriesManager({ sessionState });
    let nextCursor = null;
    let currentSearchQuery = '';

    const infiniteScroll = new InfiniteScroll(async () => {
        const result = await entriesManager.loadEntries(
            `/api/users/${nickname}/entries`,
            {
                cursor: nextCursor,
                limit: limit,
                container: entriesContainer,
                searchQuery: currentSearchQuery || null,
                cardOptions: {
                    showSourceBadge: false,
                    canModify: () => false,
                    isLoggedIn: false,
                    currentUserId: null
                }
            }
        );

        nextCursor = result.next_cursor;

        if (searchManager && currentSearchQuery && entriesContainer.children.length === result.entries.length) {
            const count = result.total_count !== undefined ? result.total_count : result.entries.length;
            searchManager.updateResultsCount(count);
        }

        if (result.entries.length === 0 && entriesContainer.children.length === 0) {
            if (currentSearchQuery) {
                showEmptyState(entriesContainer, {
                    icon: 'fa-magnifying-glass',
                    title: 'No results found',
                    message: `No entries match "${currentSearchQuery}"`
                });
                if (searchManager) searchManager.updateResultsCount(0);
            } else {
                showEmptyState(entriesContainer, {
                    icon: 'fa-file-lines',
                    title: 'No entries yet',
                    message: "This user hasn't posted anything yet."
                });
            }
        }

        postHeight();
        return { hasMore: result.has_more };
    }, {
        threshold: 500,
        loadingElement: loadingElement,
        endElement: endMessage
    });

    // ── Search (optional) ──

    let searchManager = null;
    if (showSearch && typeof SearchManager !== 'undefined') {
        const searchSection = document.getElementById('searchSection');
        if (searchSection) {
            searchManager = new SearchManager({
                userNickname: nickname,
                onSearch: (query) => {
                    currentSearchQuery = query;
                    entriesContainer.innerHTML = '';
                    nextCursor = null;
                    infiniteScroll.reset();
                    infiniteScroll.loadMore();
                }
            });
            searchManager.render(searchSection);
        }
    }

    // ── Embed-specific overrides ──
    // card-template.js uses window.location for navigation and window.location.origin
    // for share URLs, which don't work correctly inside a cross-origin iframe.
    // We intercept all relevant clicks at the container level.

    function getHashId(card) {
        return card.dataset.hashId || card.dataset.entryId;
    }

    function statusUrl(hashId) {
        return `${baseUrl}/status/${hashId}`;
    }

    // Entry card click -> open permalink in new tab (using hash_id)
    entriesContainer.addEventListener('click', (e) => {
        const card = e.target.closest('.entry-card');
        if (!card) return;
        const hashId = getHashId(card);
        if (!hashId) return;

        // Any internal link (user profile, mention, avatar) -> new tab
        const internalLink = e.target.closest('a[href^="/@"], a.mention-link, .user-name-link');
        if (internalLink) {
            e.preventDefault();
            e.stopPropagation();
            const href = internalLink.getAttribute('href');
            window.open(`${baseUrl}${href}`, '_blank', 'noopener');
            return;
        }

        // Comment button -> open permalink in new tab
        const commentBtn = e.target.closest('.comment-button');
        if (commentBtn) {
            e.preventDefault();
            e.stopPropagation();
            const btnHashId = commentBtn.dataset.hashId || hashId;
            window.open(statusUrl(btnHashId), '_blank', 'noopener');
            return;
        }

        // Share button -> override with embed-aware share
        const shareBtn = e.target.closest('.share-button');
        if (shareBtn) {
            e.preventDefault();
            e.stopPropagation();
            openEmbedShareModal(hashId, shareBtn);
            return;
        }

        // Don't navigate for interactive elements
        if (e.target.closest('[data-no-navigate]') ||
            e.target.closest('a') ||
            e.target.closest('button') ||
            e.target.closest('.link-preview-card') ||
            e.target.closest('.entry-image-wrapper')) {
            return;
        }

        // Card body click -> open permalink in new tab
        e.preventDefault();
        e.stopPropagation();
        window.open(statusUrl(hashId), '_blank', 'noopener');
    }, true); // Use capture phase to intercept before card-template.js handlers

    // ── Embed-aware share modal ──
    // Clipboard API requires 'clipboard-write' permission in iframes.
    // Falls back to a selectable text field if clipboard fails.

    function openEmbedShareModal(hashId, buttonElement) {
        if (typeof closeShareModal === 'function') closeShareModal();

        const entryUrl = statusUrl(hashId);
        const supportsNativeShare = typeof navigator.share === 'function';

        const tooltip = document.createElement('div');
        tooltip.id = 'share-tooltip';
        tooltip.style.cssText = `
            position: fixed;
            background: var(--bg-secondary, #1e293b);
            border: 1px solid var(--border, rgba(255, 255, 255, 0.1));
            border-radius: 8px;
            padding: 0.5rem;
            box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.5);
            z-index: 10001;
            min-width: 140px;
        `;

        tooltip.innerHTML = `
            <div style="display: flex; flex-direction: column; gap: 0.25rem;">
                <button id="copy-link-button" style="
                    background: transparent; border: none;
                    color: var(--text-primary, #f8fafc);
                    padding: 0.625rem 0.75rem; border-radius: 4px;
                    font-size: 0.875rem; font-weight: 500; cursor: pointer;
                    display: flex; align-items: center; gap: 0.625rem;
                    text-align: left; white-space: nowrap;
                ">
                    <i class="fa-solid fa-link" style="width: 16px;"></i>
                    <span>Copy Link</span>
                </button>
                ${supportsNativeShare ? `
                    <button id="native-share-button" style="
                        background: transparent; border: none;
                        color: var(--text-primary, #f8fafc);
                        padding: 0.625rem 0.75rem; border-radius: 4px;
                        font-size: 0.875rem; font-weight: 500; cursor: pointer;
                        display: flex; align-items: center; gap: 0.625rem;
                        text-align: left; white-space: nowrap;
                    ">
                        <i class="fa-solid fa-share-from-square" style="width: 16px;"></i>
                        <span>Share</span>
                    </button>
                ` : ''}
            </div>
        `;

        document.body.appendChild(tooltip);

        // Position above button
        const bRect = buttonElement.getBoundingClientRect();
        const tRect = tooltip.getBoundingClientRect();
        let left = bRect.left + (bRect.width / 2) - (tRect.width / 2);
        let top = bRect.top - tRect.height - 8;
        if (left < 8) left = 8;
        if (left + tRect.width > window.innerWidth - 8) left = window.innerWidth - tRect.width - 8;
        if (top < 8) top = bRect.bottom + 8;
        tooltip.style.left = `${left}px`;
        tooltip.style.top = `${top}px`;

        // Copy link with iframe-safe fallback
        document.getElementById('copy-link-button').addEventListener('click', async () => {
            const btn = document.getElementById('copy-link-button');
            try {
                await navigator.clipboard.writeText(entryUrl);
                btn.innerHTML = '<i class="fa-solid fa-check" style="width:16px;"></i><span>Copied!</span>';
                btn.style.color = 'var(--accent, #3b82f6)';
                setTimeout(() => removeTooltip(), 1000);
            } catch (_) {
                // Clipboard blocked in iframe -- show selectable text
                btn.textContent = '';
                const input = document.createElement('input');
                input.type = 'text';
                input.value = entryUrl;
                input.readOnly = true;
                input.style.cssText = 'background:var(--bg-tertiary);border:1px solid var(--border);color:var(--text-primary);padding:0.25rem 0.5rem;border-radius:4px;font-size:0.75rem;width:100%;';
                input.addEventListener('click', () => input.select());
                btn.appendChild(input);
                input.focus();
                input.select();
            }
        });

        if (supportsNativeShare) {
            document.getElementById('native-share-button').addEventListener('click', async () => {
                try {
                    await navigator.share({ url: entryUrl });
                    removeTooltip();
                } catch (err) {
                    if (err.name === 'AbortError') return;
                    if (err.name === 'NotAllowedError') {
                        // Permissions blocked in iframe -- open URL in new tab as fallback
                        window.open(entryUrl, '_blank', 'noopener');
                        removeTooltip();
                        return;
                    }
                    console.error('Share failed:', err);
                }
            });
        }

        function removeTooltip() {
            const t = document.getElementById('share-tooltip');
            if (t) t.remove();
        }

        // Close on outside click
        setTimeout(() => {
            const handler = (ev) => {
                if (!tooltip.contains(ev.target) && ev.target !== buttonElement) {
                    removeTooltip();
                    document.removeEventListener('click', handler);
                }
            };
            document.addEventListener('click', handler);
        }, 0);
    }
})().catch((error) => {
    console.error('Failed to initialize embed:', error);
});

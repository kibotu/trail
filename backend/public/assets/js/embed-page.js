/**
 * Embed Page Initialization
 *
 * Read-only widget for embedding a user's Trail feed.
 * Shared embed behaviors (auto-resize, click overrides, share modal) live
 * in embed-behaviors.js.
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

    const { postHeight } = initEmbedBehaviors(entriesContainer, baseUrl);

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
                    currentUserId: null,
                    onTagClick: (slug) => {
                        if (searchManager) {
                            searchManager.setQuery('#' + slug);
                            document.getElementById('searchInput')?.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
                        } else {
                            window.open(`${baseUrl}/?q=${encodeURIComponent('#' + slug)}`, '_blank', 'noopener');
                        }
                    }
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
})().catch((error) => {
    console.error('Failed to initialize embed:', error);
});

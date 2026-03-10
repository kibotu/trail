/**
 * Landing Page Initialization
 * 
 * Handles all initialization logic for the landing page including:
 * - Entry loading and infinite scroll
 * - Post creation form
 * - Image uploads
 * - Shader background
 */

(async function() {
    'use strict';

    // Load configuration first
    const config = await loadConfig();
    const maxTextLength = config.max_text_length || 140;

    // Get session state from data attributes
    const body = document.body;
    const parseBool = (value) => value === 'true' || value === '1' || value === 1;
    const sessionState = {
        isLoggedIn: parseBool(body.dataset.isLoggedIn),
        userId: body.dataset.userId ? parseInt(body.dataset.userId, 10) : null
    };

    // Initialize entries manager
    const entriesManager = new EntriesManager({ sessionState });

    // Setup menu close handler
    setupMenuCloseHandler();

    // Post form handling (if logged in)
    if (sessionState.isLoggedIn) {
        const postText = document.getElementById('postText');
        const charCounter = document.getElementById('charCounter');
        const submitButton = document.getElementById('submitButton');
        const createPostForm = document.getElementById('createPostForm');
        const postMessage = document.getElementById('postMessage');

        if (postText && charCounter && submitButton && createPostForm) {
            // Set maxlength attribute dynamically
            postText.setAttribute('maxlength', maxTextLength);
            
            // Update initial counter display
            charCounter.textContent = `0 / ${maxTextLength}`;
            
            // Setup character counter
            setupCharacterCounter({
                textarea: postText,
                counter: charCounter,
                submitButton: submitButton
            }, maxTextLength, {
                allowEmpty: true,
                hasImages: () => window.postImageIds && window.postImageIds.length > 0
            });

            // Handle form submission
            createPostForm.addEventListener('submit', async function(e) {
                e.preventDefault();
                
                const text = postText.value.trim();
                const hasImages = window.postImageIds && window.postImageIds.length > 0;
                
                // Validate
                const validation = validateEntryText(text, { 
                    maxLength: maxTextLength,
                    allowEmpty: true, 
                    hasImages 
                });
                if (!validation.valid) {
                    showMessage(postMessage, validation.error, 'error');
                    return;
                }

                setButtonLoading(submitButton, true, 'Posting...');
                postText.disabled = true;

                try {
                    await entriesManager.createEntry(text, window.postImageIds || []);
                    
                    // Clear form
                    postText.value = '';
                    charCounter.textContent = `0 / ${maxTextLength}`;
                    window.postImageIds = [];
                    
                    if (window.postImageUploader && typeof window.postImageUploader.clearPreviews === 'function') {
                        window.postImageUploader.clearPreviews();
                    }
                    
                    // Celebrate!
                    celebratePost('createPostForm');
                    
                    showMessage(postMessage, '<i class="fa-solid fa-check"></i> Post created successfully!', 'success');
                    
                    setTimeout(() => location.reload(), 1500);

                } catch (error) {
                    console.error('Error creating post:', error);
                    showMessage(postMessage, `Failed to create post: ${error.message}`, 'error');
                } finally {
                    setButtonLoading(submitButton, false);
                    postText.disabled = false;
                }
            });
        }

        // Initialize post image uploader
        window.postImageIds = [];
        window.postImageUploader = createImageUploadUI(
            'post',
            'post-image-upload',
            (result) => {
                if (result.removed) {
                    const index = window.postImageIds.indexOf(result.image_id);
                    if (index > -1) {
                        window.postImageIds.splice(index, 1);
                    }
                } else {
                    if (!window.postImageIds) {
                        window.postImageIds = [];
                    }
                    window.postImageIds.push(result.image_id);
                }
                
                // Trigger character counter update
                const postText = document.getElementById('postText');
                if (postText) {
                    postText.dispatchEvent(new Event('input'));
                }
            }
        );
    }

    // Setup infinite scroll for entries
    let nextCursor = null;
    let currentSearchQuery = '';
    const entriesContainer = document.getElementById('entriesContainer');
    const loadingElement = document.getElementById('loading');
    const endMessage = document.getElementById('endMessage');
    const createPostSection = document.querySelector('.create-post-section');

    /**
     * Hydrate a server-rendered entry card by attaching all interactive
     * behaviour that createEntryCard() normally wires up client-side.
     */
    function hydrateEntryCard(card) {
        const entryId = parseInt(card.dataset.entryId, 10);
        const hashId  = card.dataset.hashId || entryId;

        // Permalink click
        card.addEventListener('click', (e) => {
            if (e.target.closest('[data-no-navigate]') ||
                e.target.closest('a') ||
                e.target.closest('button') ||
                e.target.closest('.link-preview-card') ||
                e.target.closest('.entry-image-wrapper')) {
                return;
            }
            window.location.href = `/status/${hashId}`;
        });
        card.addEventListener('keydown', (e) => {
            if (e.key === 'Enter' && !e.target.closest('[data-no-navigate]')) {
                window.location.href = `/status/${hashId}`;
            }
        });

        // Share button
        const shareButton = card.querySelector('.share-button');
        if (shareButton) {
            shareButton.addEventListener('click', (e) => {
                e.stopPropagation();
                openShareModal(hashId, shareButton);
            });
        }

        // Tag clicks
        card.querySelectorAll('.entry-tag').forEach(tagEl => {
            tagEl.addEventListener('click', (e) => {
                e.preventDefault();
                e.stopPropagation();
                if (typeof searchManager !== 'undefined' && searchManager) {
                    searchManager.setQuery('#' + tagEl.dataset.tagSlug);
                    document.getElementById('searchInput')?.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
                }
            });
        });

        // Clap button
        const clapButton = card.querySelector('.clap-button');
        if (clapButton) {
            clapButton.addEventListener('click', async (e) => {
                e.stopPropagation();
                if (clapButton.dataset.isOwn === 'true') {
                    clapButton.classList.add('clap-own-entry-shake');
                    setTimeout(() => clapButton.classList.remove('clap-own-entry-shake'), 500);
                    return;
                }
                if (!sessionState.isLoggedIn) {
                    if (typeof showLoginPrompt === 'function') showLoginPrompt();
                    else alert('Please log in to clap for entries');
                    return;
                }
                let userClaps  = parseInt(clapButton.dataset.userClaps, 10) || 0;
                let totalClaps = parseInt(clapButton.dataset.totalClaps, 10) || 0;
                if (userClaps >= 50) {
                    clapButton.classList.add('clap-limit-reached');
                    setTimeout(() => clapButton.classList.remove('clap-limit-reached'), 500);
                    return;
                }
                userClaps++;
                totalClaps++;
                clapButton.dataset.userClaps  = userClaps;
                clapButton.dataset.totalClaps = totalClaps;
                clapButton.classList.add('clapped', 'clap-animation');
                const icon = clapButton.querySelector('i');
                icon.className = 'fa-solid fa-heart';
                const countSpan = clapButton.querySelector('.clap-count');
                countSpan.textContent = formatClapCount(totalClaps);
                if (typeof createClapParticles === 'function') createClapParticles(clapButton, e.clientX, e.clientY);
                setTimeout(() => clapButton.classList.remove('clap-animation'), 300);
                try {
                    const resp = await fetch(`/api/entries/${hashId}/claps`, {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        credentials: 'same-origin',
                        body: JSON.stringify({ count: userClaps })
                    });
                    if (!resp.ok) throw new Error('Failed');
                    const data = await resp.json();
                    clapButton.dataset.userClaps  = data.user_claps;
                    clapButton.dataset.totalClaps = data.total_claps;
                    countSpan.textContent = formatClapCount(data.total_claps);
                } catch (_) {
                    userClaps--;
                    totalClaps--;
                    clapButton.dataset.userClaps  = userClaps;
                    clapButton.dataset.totalClaps = totalClaps;
                    countSpan.textContent = formatClapCount(totalClaps);
                    if (userClaps === 0) {
                        clapButton.classList.remove('clapped');
                        icon.className = 'fa-regular fa-heart';
                    }
                }
            });
        }

        // Menu buttons
        const menuButton   = card.querySelector('[data-action="toggle-menu"]');
        const editButton   = card.querySelector('[data-action="edit"]');
        const deleteButton = card.querySelector('[data-action="delete"]');
        const reportButton = card.querySelector('[data-action="report"]');
        const muteBtn      = card.querySelector('[data-action="mute"]');

        if (menuButton)   menuButton.addEventListener('click',   (e) => { e.stopPropagation(); if (typeof toggleMenu === 'function') toggleMenu(e, entryId); });
        if (editButton)   editButton.addEventListener('click',   (e) => { e.stopPropagation(); if (typeof editEntry === 'function') editEntry(entryId); });
        if (deleteButton) deleteButton.addEventListener('click', (e) => { e.stopPropagation(); if (typeof deleteEntry === 'function') deleteEntry(entryId); });
        if (reportButton) reportButton.addEventListener('click', (e) => { e.stopPropagation(); if (typeof reportEntry === 'function') reportEntry(entryId, card); });
        if (muteBtn)      muteBtn.addEventListener('click',      (e) => { e.stopPropagation(); const uid = parseInt(muteBtn.dataset.userId, 10); if (typeof muteUser === 'function') muteUser(uid); });

        // View tracking
        viewTrackingObserver.observe(card);

        // Video players
        initializeVideoPlayers(card);
    }

    if (entriesContainer && loadingElement && endMessage) {
        const infiniteScroll = new InfiniteScroll(async () => {
            const result = await entriesManager.loadEntries('/api/entries', {
                cursor: nextCursor,
                limit: 100,
                container: entriesContainer,
                searchQuery: currentSearchQuery || null,
                cardOptions: {
                    showSourceBadge: false,
                    canModify: (entry) => canModifyEntry(entry),
                    isLoggedIn: sessionState.isLoggedIn,
                    currentUserId: sessionState.userId,
                    onTagClick: (slug) => {
                        if (searchManager) {
                            searchManager.setQuery('#' + slug);
                            document.getElementById('searchInput')?.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
                        }
                    }
                }
            });

            nextCursor = result.next_cursor;
            
            // Update search manager with total count from API on first load
            if (currentSearchQuery && searchManager && entriesContainer.children.length === result.entries.length) {
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
                    if (searchManager) {
                        searchManager.updateResultsCount(0);
                    }
                } else {
                    showEmptyState(entriesContainer, {
                        icon: 'fa-file-lines',
                        title: 'No entries yet',
                        message: 'Be the first to share something!'
                    });
                }
            }

            return { hasMore: result.has_more };
        }, {
            threshold: 500,
            loadingElement: loadingElement,
            endElement: endMessage,
            enabled: false
        });

        // SSR hydration: if entries were server-rendered, attach handlers
        // and pick up the pagination cursor instead of fetching the first page.
        if (window.__SSR_ENTRIES__ && entriesContainer.children.length > 0) {
            nextCursor = window.__SSR_ENTRIES__.nextCursor;
            infiniteScroll.setHasMore(window.__SSR_ENTRIES__.hasMore);
            entriesContainer.querySelectorAll('.entry-card').forEach(hydrateEntryCard);
            infiniteScroll.start();
        } else {
            infiniteScroll.start();
        }

        // Initialize SearchManager
        const searchSection = document.getElementById('searchSection');
        let searchManager = null;
        if (searchSection) {
            searchManager = new SearchManager({
                onSearch: (query) => {
                    currentSearchQuery = query;
                    
                    if (createPostSection) {
                        createPostSection.style.display = query ? 'none' : 'block';
                    }
                    
                    entriesContainer.innerHTML = '';
                    nextCursor = null;
                    
                    infiniteScroll.reset();
                    infiniteScroll.loadMore();
                }
            });
            searchManager.render(searchSection);
        }
    }

    // Expose functions globally for card-template.js
    window.editEntry = function(entryId) {
        entriesManager.editEntry(entryId);
    };

    window.deleteEntry = function(entryId) {
        entriesManager.deleteEntry(entryId);
    };

    window.cancelEdit = function(entryId) {
        entriesManager.cancelEdit(entryId);
    };

    window.saveEdit = function(entryId) {
        entriesManager.saveEdit(entryId);
    };

    // Initialize shader background
    const shaderCanvas = document.getElementById('shader-canvas');
    if (shaderCanvas) {
        initShaderBackground('shader-canvas');
    }
})().catch(error => {
    console.error('Failed to initialize landing page:', error);
});

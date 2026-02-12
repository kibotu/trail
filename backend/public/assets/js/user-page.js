/**
 * User Page Initialization
 * 
 * Handles initialization for user profile page including:
 * - Loading user entries
 * - Infinite scroll
 * - Edit/delete functionality
 */

(async function() {
    'use strict';

    // Load configuration first
    await loadConfig();

    // Get data from body attributes
    const body = document.body;
    const nickname = body.dataset.nickname;
    const parseBool = (value) => value === 'true' || value === '1' || value === 1;
    const sessionState = {
        isLoggedIn: parseBool(body.dataset.isLoggedIn),
        userId: body.dataset.userId ? parseInt(body.dataset.userId, 10) : null,
        isAdmin: parseBool(body.dataset.isAdmin)
    };

    let nextCursor = null;
    let currentSearchQuery = '';

    const entriesContainer = document.getElementById('entriesContainer');
    const loadingElement = document.getElementById('loading');
    const endMessage = document.getElementById('endMessage');

    if (!entriesContainer || !loadingElement || !endMessage) return;

    // Initialize profile manager
    const userProfileManager = new UserProfileManager({
        nickname: nickname,
        sessionState: sessionState,
        apiBase: '/api'
    });

    // Load profile
    userProfileManager.init();

    // Initialize entries manager
    const entriesManager = new EntriesManager({ sessionState });

    // Setup menu close handler
    setupMenuCloseHandler();

    // Setup infinite scroll
    const infiniteScroll = new InfiniteScroll(async () => {
        const result = await entriesManager.loadEntries(`/api/users/${nickname}/entries`, {
            cursor: nextCursor,
            limit: 100,
            container: entriesContainer,
            searchQuery: currentSearchQuery || null,
            cardOptions: {
                showSourceBadge: false,
                canModify: (entry) => canModifyEntry(entry, sessionState),
                isAdmin: sessionState.isAdmin,
                isLoggedIn: sessionState.isLoggedIn,
                currentUserId: sessionState.userId
            }
        });

        nextCursor = result.next_cursor;

        // Update search manager with total count from API on first load
        if (currentSearchQuery && searchManager && entriesContainer.children.length === result.entries.length) {
            // Use total_count from API if available (first page only), otherwise fall back to entries.length
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
                // Update count to 0
                if (searchManager) {
                    searchManager.updateResultsCount(0);
                }
            } else {
                showEmptyState(entriesContainer, {
                    icon: 'fa-file-lines',
                    title: 'No entries yet',
                    message: 'This user hasn\'t posted anything yet.'
                });
            }
        }

        return { hasMore: result.has_more };
    }, {
        threshold: 500,
        loadingElement: loadingElement,
        endElement: endMessage
    });

    // Initialize SearchManager
    const searchSection = document.getElementById('searchSection');
    let searchManager = null;
    if (searchSection) {
        searchManager = new SearchManager({
            userNickname: nickname,
            onSearch: (query) => {
                // Update current search query
                currentSearchQuery = query;
                
                // Clear existing entries
                entriesContainer.innerHTML = '';
                nextCursor = null;
                
                // Reset and reload with search
                infiniteScroll.reset();
                infiniteScroll.loadMore();
            }
        });
        searchManager.render(searchSection);
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
})().catch(error => {
    console.error('Failed to initialize user page:', error);
});

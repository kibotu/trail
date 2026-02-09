/**
 * User Page Initialization
 * 
 * Handles initialization for user profile page including:
 * - Loading user entries
 * - Infinite scroll
 * - Edit/delete functionality
 */

(function() {
    'use strict';

    // Get data from body attributes
    const body = document.body;
    const nickname = body.dataset.nickname;
    const sessionState = {
        isLoggedIn: body.dataset.isLoggedIn === 'true',
        userId: body.dataset.userId ? parseInt(body.dataset.userId, 10) : null,
        userEmail: body.dataset.userEmail || null,
        isAdmin: body.dataset.isAdmin === 'true'
    };

    let nextCursor = null;
    let currentSearchQuery = '';
    let totalResultsCount = 0;

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
                isAdmin: false,
                isLoggedIn: sessionState.isLoggedIn,
                currentUserId: null
            }
        });

        nextCursor = result.next_cursor;

        // Update total count for search results
        if (currentSearchQuery && !nextCursor) {
            // First load - count the entries
            totalResultsCount += result.entries.length;
        } else if (currentSearchQuery) {
            // Subsequent loads - add to count
            totalResultsCount += result.entries.length;
        }
        
        // Update search manager with count on first load
        if (currentSearchQuery && searchManager && entriesContainer.children.length === result.entries.length) {
            searchManager.updateResultsCount(result.entries.length);
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
                totalResultsCount = 0;
                
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
})();

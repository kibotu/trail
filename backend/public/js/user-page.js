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
        userId: null,
        userEmail: body.dataset.userEmail || null,
        isAdmin: body.dataset.isAdmin === 'true'
    };

    let nextCursor = null;
    let userData = null;

    const entriesContainer = document.getElementById('entriesContainer');
    const loadingElement = document.getElementById('loading');
    const endMessage = document.getElementById('endMessage');
    const userHeader = document.getElementById('userHeader');

    if (!entriesContainer || !loadingElement || !endMessage) return;

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
            cardOptions: {
                showSourceBadge: false,
                canModify: (entry) => canModifyEntry(entry, sessionState),
                isAdmin: false,
                isLoggedIn: sessionState.isLoggedIn,
                currentUserId: null
            }
        });

        // Update user header on first load
        if (result.user && !userData && userHeader) {
            userData = result.user;
            const avatarUrl = userData.photo_url || 
                `https://www.gravatar.com/avatar/${userData.gravatar_hash}?s=160&d=mp`;
            const userAvatar = document.getElementById('userAvatar');
            const userName = document.getElementById('userName');
            if (userAvatar) userAvatar.src = avatarUrl;
            if (userName) userName.textContent = `@${userData.nickname}`;
            userHeader.style.display = 'flex';
        }

        nextCursor = result.next_cursor;

        if (result.entries.length === 0 && entriesContainer.children.length === 0) {
            showEmptyState(entriesContainer, {
                icon: 'fa-file-lines',
                title: 'No entries yet',
                message: 'This user hasn\'t posted anything yet.'
            });
        }

        return { hasMore: result.has_more };
    }, {
        threshold: 500,
        loadingElement: loadingElement,
        endElement: endMessage
    });

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

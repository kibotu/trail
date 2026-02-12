/**
 * Status Page Initialization
 * 
 * Handles initialization for single entry status page including:
 * - Loading single entry
 * - Meta tag updates
 * - Edit/delete functionality
 */

(function() {
    'use strict';

    // Get data from body attributes
    const body = document.body;
    const hashId = body.dataset.hashId;
    const parseBool = (value) => value === 'true' || value === '1' || value === 1;
    const sessionState = {
        isLoggedIn: parseBool(body.dataset.isLoggedIn),
        userId: body.dataset.userId ? parseInt(body.dataset.userId, 10) : null
    };

    let currentEntry = null;

    // Initialize entries manager
    const entriesManager = new EntriesManager({ sessionState });

    // Setup menu close handler
    setupMenuCloseHandler();

    // Load entry
    async function loadEntry() {
        const container = document.getElementById('entry-container');
        if (!container) return;
        
        try {
            // Preload config before loading entry
            await loadConfig();
            
            const entry = await entriesManager.loadSingleEntry(hashId);
            currentEntry = entry;
            
            // Update meta tags
            updateMetaTagsFromEntry(entry);
            
            // Render entry (can_edit flag is provided by API)
            container.innerHTML = '';
            const card = createEntryCard(entry, {
                canModify: entry.can_edit === true,
                enablePermalink: false,
                isLoggedIn: sessionState.isLoggedIn,
                currentUserId: sessionState.userId
            });
            
            // Store entry data in card for edit functionality
            if (entry.images && Array.isArray(entry.images)) {
                card.dataset.imageIds = JSON.stringify(entry.images.map(img => img.id));
            }
            
            container.appendChild(card);
            
            // Auto-expand comments on status page
            const autoExpandComments = () => {
                const commentButton = card.querySelector('.comment-button');
                if (commentButton && typeof commentsManager !== 'undefined' && commentsManager.expandComments) {
                    const entryId = parseInt(commentButton.dataset.entryId);
                    const hashId = commentButton.dataset.hashId;
                    commentsManager.expandComments(entryId, hashId, card, commentButton);
                } else if (typeof commentsManager === 'undefined') {
                    // Retry if commentsManager is not yet loaded
                    setTimeout(autoExpandComments, 100);
                }
            };
            
            // Wait for DOM to be ready and commentsManager to be initialized
            setTimeout(autoExpandComments, 200);
            
        } catch (error) {
            console.error('Error loading entry:', error);
            if (error.message === 'Entry not found') {
                container.innerHTML = '<div class="error-message">Entry not found</div>';
            } else {
                container.innerHTML = '<div class="error-message">Failed to load entry</div>';
            }
        }
    }

    // Expose edit/delete functions globally
    window.editEntry = function(entryId) {
        entriesManager.editEntry(entryId);
    };

    window.deleteEntry = async function(entryId) {
        await entriesManager.deleteEntry(currentEntry ? currentEntry.id : entryId, {
            redirectOnSuccess: true,
            redirectUrl: '/'
        });
    };

    window.cancelEdit = function(entryId) {
        entriesManager.cancelEdit(entryId);
    };

    window.saveEdit = async function(entryId) {
        // Custom save that uses currentEntry ID
        const textarea = document.getElementById(`edit-text-${entryId}`);
        const newText = textarea.value.trim();

        if (!newText) {
            showSnackbar('Entry text cannot be empty', 'error');
            return;
        }

        const saveButton = document.querySelector('.save-button');
        if (saveButton) {
            saveButton.disabled = true;
            saveButton.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i><span>Saving...</span>';
        }

        try {
            const apiEntryId = currentEntry ? currentEntry.id : entryId;
            const payload = { text: newText };
            
            // Preserve images
            if (currentEntry) {
                let imageIds = null;
                if (currentEntry.image_ids) {
                    imageIds = typeof currentEntry.image_ids === 'string' 
                        ? JSON.parse(currentEntry.image_ids) 
                        : currentEntry.image_ids;
                } else if (currentEntry.images && Array.isArray(currentEntry.images)) {
                    imageIds = currentEntry.images.map(img => img.id);
                }
                if (imageIds && Array.isArray(imageIds) && imageIds.length > 0) {
                    payload.image_ids = imageIds;
                }
            }
            
            const response = await fetch(`/api/entries/${apiEntryId}`, {
                method: 'PUT',
                credentials: 'same-origin',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(payload)
            });

            if (!response.ok) {
                const error = await response.json();
                throw new Error(error.error || 'Failed to update entry');
            }

            showSnackbar('Entry updated successfully', 'success');
            setTimeout(() => window.location.reload(), 500);

        } catch (error) {
            console.error('Error updating entry:', error);
            showSnackbar(`Failed to update entry: ${error.message}`, 'error');
            
            if (saveButton) {
                saveButton.disabled = false;
                saveButton.innerHTML = '<i class="fa-solid fa-floppy-disk"></i><span>Save</span>';
            }
        }
    };

    // Load entry on page load
    loadEntry();
})();

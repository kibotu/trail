/**
 * Entry Manager - Unified entry CRUD operations
 * 
 * Handles loading, creating, editing, and deleting entries across all pages.
 * Provides consistent behavior and error handling for entry operations.
 */

class EntriesManager {
    constructor(options = {}) {
        this.sessionState = options.sessionState || {};
        this.onEntryCreated = options.onEntryCreated || (() => {});
        this.onEntryUpdated = options.onEntryUpdated || (() => {});
        this.onEntryDeleted = options.onEntryDeleted || (() => {});
    }

    /**
     * Load entries from API with pagination
     * @param {string} apiUrl - API endpoint URL
     * @param {Object} options - Loading options
     * @param {string} options.cursor - Pagination cursor
     * @param {number} options.limit - Number of entries to load
     * @param {HTMLElement} options.container - Container to append entries
     * @param {Object} options.cardOptions - Options for createEntryCard
     * @returns {Promise<Object>} Response with entries, next_cursor, and has_more
     */
    async loadEntries(apiUrl, options = {}) {
        const {
            cursor = null,
            limit = 100,
            container = null,
            cardOptions = {}
        } = options;

        try {
            const url = new URL(apiUrl, window.location.origin);
            url.searchParams.set('limit', limit.toString());
            if (cursor) {
                url.searchParams.set('before', cursor);
            }

            const response = await fetch(url, {
                credentials: 'same-origin'
            });

            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }

            const data = await response.json();

            // Render entries if container provided
            if (container && data.entries && data.entries.length > 0) {
                data.entries.forEach(entry => {
                    const card = createEntryCard(entry, cardOptions);
                    
                    // Store entry data in card for edit functionality
                    if (entry.image_ids) {
                        card.dataset.imageIds = entry.image_ids;
                    } else if (entry.images && Array.isArray(entry.images)) {
                        card.dataset.imageIds = JSON.stringify(entry.images.map(img => img.id));
                    }
                    
                    container.appendChild(card);
                });
            }

            return {
                entries: data.entries || [],
                next_cursor: data.next_cursor || null,
                has_more: data.has_more || false,
                user: data.user || null
            };

        } catch (error) {
            console.error('Error loading entries:', error);
            throw error;
        }
    }

    /**
     * Load a single entry by hash ID
     * @param {string} hashId - Entry hash ID
     * @returns {Promise<Object>} Entry data
     */
    async loadSingleEntry(hashId) {
        try {
            const response = await fetch(`/api/entries/${hashId}`, {
                credentials: 'same-origin'
            });

            if (!response.ok) {
                if (response.status === 404) {
                    throw new Error('Entry not found');
                }
                throw new Error(`HTTP error! status: ${response.status}`);
            }

            return await response.json();

        } catch (error) {
            console.error('Error loading entry:', error);
            throw error;
        }
    }

    /**
     * Create a new entry
     * @param {string} text - Entry text content
     * @param {Array<number>} imageIds - Array of image IDs
     * @param {Object} options - Creation options
     * @returns {Promise<Object>} Created entry data
     */
    async createEntry(text, imageIds = [], options = {}) {
        const { showLoading = true } = options;

        try {
            const payload = { text };
            if (imageIds && imageIds.length > 0) {
                payload.image_ids = imageIds;
            }

            const response = await fetch('/api/entries', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                credentials: 'same-origin',
                body: JSON.stringify(payload)
            });

            if (!response.ok) {
                const error = await response.json();
                throw new Error(error.error || 'Failed to create entry');
            }

            const data = await response.json();
            this.onEntryCreated(data);
            return data;

        } catch (error) {
            console.error('Error creating entry:', error);
            throw error;
        }
    }

    /**
     * Edit an entry - show edit form
     * @param {number} entryId - Entry ID
     * @param {Object} options - Edit options
     */
    editEntry(entryId, options = {}) {
        const { onCancel = null, onSave = null } = options;

        // Close the menu
        const menu = document.getElementById(`menu-${entryId}`);
        if (menu) menu.classList.remove('active');

        const card = document.querySelector(`[data-entry-id="${entryId}"]`);
        if (!card) {
            console.error('Card not found for entry:', entryId);
            return;
        }

        const contentDiv = card.querySelector('.entry-content');
        const textDiv = card.querySelector('.entry-text');
        const currentText = textDiv ? textDiv.textContent : '';

        // Store the original HTML to restore it later
        const originalHtml = contentDiv.innerHTML;
        contentDiv.dataset.originalHtml = originalHtml;

        // Get images and preview card HTML to show during editing
        const imagesDiv = contentDiv.querySelector('.entry-images');
        const imagesHtml = imagesDiv ? imagesDiv.outerHTML : '';
        
        const previewCard = contentDiv.querySelector('.link-preview-card, .link-preview-wrapper');
        const previewHtml = previewCard ? previewCard.outerHTML : '';

        // Add edit-textarea styles if not already present
        this._injectEditStyles();

        // Get max text length from config
        const maxTextLength = getConfigSync('max_text_length', 140);
        
        // Create edit form with images and preview shown
        contentDiv.innerHTML = `
            <div class="edit-form">
                <textarea class="edit-textarea" id="edit-text-${entryId}" maxlength="${maxTextLength}">${escapeHtml(currentText)}</textarea>
                ${imagesHtml}
                ${previewHtml}
                <div class="edit-actions" style="display: flex; gap: 0.5rem; justify-content: flex-end; margin-top: 0.75rem;">
                    <button class="action-button cancel-button" data-entry-id="${entryId}">
                        <i class="fa-solid fa-xmark"></i>
                        <span>Cancel</span>
                    </button>
                    <button class="action-button save-button" data-entry-id="${entryId}">
                        <i class="fa-solid fa-floppy-disk"></i>
                        <span>Save</span>
                    </button>
                </div>
            </div>
        `;

        // Add event listeners to the buttons
        const cancelButton = contentDiv.querySelector('.cancel-button');
        const saveButton = contentDiv.querySelector('.save-button');
        
        cancelButton.addEventListener('click', () => {
            this.cancelEdit(entryId);
            if (onCancel) onCancel();
        });
        
        saveButton.addEventListener('click', () => {
            this.saveEdit(entryId, options);
            if (onSave) onSave();
        });

        // Prevent click-through to status page on edit form
        const editForm = contentDiv.querySelector('.edit-form');
        if (editForm) {
            editForm.addEventListener('click', (e) => {
                e.stopPropagation();
            });
        }

        // Focus textarea
        const textarea = document.getElementById(`edit-text-${entryId}`);
        if (textarea) {
            textarea.addEventListener('click', (e) => {
                e.stopPropagation();
            });
            textarea.focus();
            textarea.setSelectionRange(textarea.value.length, textarea.value.length);
        }
    }

    /**
     * Cancel edit mode
     * @param {number} entryId - Entry ID
     */
    cancelEdit(entryId) {
        const card = document.querySelector(`[data-entry-id="${entryId}"]`);
        if (!card) return;

        const contentDiv = card.querySelector('.entry-content');
        const originalHtml = contentDiv.dataset.originalHtml;
        
        if (originalHtml) {
            contentDiv.innerHTML = originalHtml;
            delete contentDiv.dataset.originalHtml;
        }
    }

    /**
     * Save edited entry
     * @param {number} entryId - Entry ID
     * @param {Object} options - Save options
     */
    async saveEdit(entryId, options = {}) {
        const { reloadOnSuccess = true } = options;

        const textarea = document.getElementById(`edit-text-${entryId}`);
        const newText = textarea.value.trim();

        if (!newText) {
            if (typeof showSnackbar === 'function') {
                showSnackbar('Entry text cannot be empty', 'error');
            } else {
                alert('Entry text cannot be empty');
            }
            return;
        }

        if (!this.sessionState.isLoggedIn) {
            if (typeof showSnackbar === 'function') {
                showSnackbar('You must be logged in to edit entries', 'error');
            } else {
                alert('You must be logged in to edit entries');
            }
            return;
        }

        // Disable save button during request
        const saveButton = document.querySelector(`[data-entry-id="${entryId}"].save-button`);
        if (saveButton) {
            saveButton.disabled = true;
            saveButton.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i><span>Saving...</span>';
        }

        try {
            // Get the card to access stored image_ids
            const card = document.querySelector(`[data-entry-id="${entryId}"]`);
            
            // Preserve existing image_ids
            const payload = { text: newText };
            if (card && card.dataset.imageIds) {
                try {
                    const imageIds = JSON.parse(card.dataset.imageIds);
                    if (Array.isArray(imageIds) && imageIds.length > 0) {
                        payload.image_ids = imageIds;
                    }
                } catch (e) {
                    console.warn('Failed to parse image_ids from card dataset:', e);
                }
            }

            const response = await fetch(`/api/entries/${entryId}`, {
                method: 'PUT',
                credentials: 'same-origin',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify(payload)
            });

            if (!response.ok) {
                const error = await response.json();
                throw new Error(error.error || 'Failed to update entry');
            }

            const data = await response.json();
            this.onEntryUpdated(data);

            // Show success message
            if (typeof showSnackbar === 'function') {
                showSnackbar('Entry updated successfully', 'success');
            }
            
            // Reload the page to show updated content
            if (reloadOnSuccess) {
                setTimeout(() => {
                    window.location.reload();
                }, 500);
            }

        } catch (error) {
            console.error('Error updating entry:', error);
            if (typeof showSnackbar === 'function') {
                showSnackbar(`Failed to update entry: ${error.message}`, 'error');
            } else {
                alert(`Failed to update entry: ${error.message}`);
            }
            
            // Re-enable save button on error
            if (saveButton) {
                saveButton.disabled = false;
                saveButton.innerHTML = '<i class="fa-solid fa-floppy-disk"></i><span>Save</span>';
            }
        }
    }

    /**
     * Delete an entry
     * @param {number} entryId - Entry ID
     * @param {Object} options - Delete options
     */
    async deleteEntry(entryId, options = {}) {
        const {
            confirmMessage = 'Are you sure you want to delete this entry?',
            redirectOnSuccess = false,
            redirectUrl = '/'
        } = options;

        // Close the menu
        const menu = document.getElementById(`menu-${entryId}`);
        if (menu) menu.classList.remove('active');

        if (!confirm(confirmMessage)) {
            return;
        }

        if (!this.sessionState.isLoggedIn) {
            alert('You must be logged in to delete entries');
            return;
        }

        try {
            const response = await fetch(`/api/entries/${entryId}`, {
                method: 'DELETE',
                credentials: 'same-origin'
            });

            if (!response.ok) {
                const error = await response.json();
                throw new Error(error.error || 'Failed to delete entry');
            }

            this.onEntryDeleted(entryId);

            // Remove the card from the DOM with animation
            const card = document.querySelector(`[data-entry-id="${entryId}"]`);
            if (card) {
                card.style.transition = 'opacity 0.3s, transform 0.3s';
                card.style.opacity = '0';
                card.style.transform = 'translateX(-20px)';
                setTimeout(() => card.remove(), 300);
            }

            // Redirect if needed (e.g., on status page)
            if (redirectOnSuccess) {
                setTimeout(() => {
                    window.location.href = redirectUrl;
                }, 500);
            }

        } catch (error) {
            console.error('Error deleting entry:', error);
            alert(`Failed to delete entry: ${error.message}`);
        }
    }

    /**
     * Inject edit form styles into document head
     * @private
     */
    _injectEditStyles() {
        if (document.getElementById('edit-textarea-styles')) return;

        const style = document.createElement('style');
        style.id = 'edit-textarea-styles';
        style.textContent = `
            .edit-textarea {
                width: 100%;
                min-height: 80px;
                background: var(--bg-primary);
                color: var(--text-primary);
                border: 1px solid var(--border);
                border-radius: 8px;
                padding: 0.75rem;
                font-size: 1rem;
                font-family: inherit;
                line-height: 1.6;
                resize: vertical;
            }
            .edit-textarea:focus {
                outline: none;
                border-color: var(--accent);
            }
            .action-button {
                border: none;
                padding: 0.5rem 1rem;
                border-radius: 6px;
                cursor: pointer;
                display: inline-flex;
                align-items: center;
                gap: 0.5rem;
                font-size: 0.875rem;
                font-weight: 500;
                transition: all 0.2s;
            }
            .action-button:hover:not(:disabled) {
                opacity: 0.9;
                transform: translateY(-1px);
            }
            .action-button:disabled {
                opacity: 0.6;
                cursor: not-allowed;
            }
            .cancel-button {
                background: var(--bg-tertiary);
                color: var(--text-primary);
            }
            .save-button {
                background: var(--accent);
                color: white;
            }
            .edit-form .entry-images,
            .edit-form .link-preview-card,
            .edit-form .link-preview-wrapper {
                opacity: 0.7;
                margin-top: 0.75rem;
            }
            .edit-form .entry-images img,
            .edit-form .link-preview-card,
            .edit-form .link-preview-wrapper {
                pointer-events: none;
            }
        `;
        document.head.appendChild(style);
    }
}

// Export for use in other scripts
if (typeof module !== 'undefined' && module.exports) {
    module.exports = { EntriesManager };
}

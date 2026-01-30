/**
 * Landing Page Initialization
 * 
 * Handles all initialization logic for the landing page including:
 * - Entry loading and infinite scroll
 * - Post creation form
 * - Image uploads
 * - Shader background
 */

(function() {
    'use strict';

    // Get session state from data attributes
    const body = document.body;
    const sessionState = {
        isLoggedIn: body.dataset.isLoggedIn === 'true',
        userId: body.dataset.userId ? parseInt(body.dataset.userId, 10) : null,
        userEmail: body.dataset.userEmail || null,
        isAdmin: body.dataset.isAdmin === 'true'
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
            // Setup character counter
            setupCharacterCounter({
                textarea: postText,
                counter: charCounter,
                submitButton: submitButton
            }, 280, {
                allowEmpty: true,
                hasImages: () => window.postImageIds && window.postImageIds.length > 0
            });

            // Handle form submission
            createPostForm.addEventListener('submit', async function(e) {
                e.preventDefault();
                
                const text = postText.value.trim();
                const hasImages = window.postImageIds && window.postImageIds.length > 0;
                
                // Validate
                const validation = validateEntryText(text, { allowEmpty: true, hasImages });
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
                    charCounter.textContent = '0 / 280';
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
        window.addEventListener('DOMContentLoaded', () => {
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
        });
    }

    // Setup infinite scroll for entries
    let nextCursor = null;
    const entriesContainer = document.getElementById('entriesContainer');
    const loadingElement = document.getElementById('loading');
    const endMessage = document.getElementById('endMessage');

    if (entriesContainer && loadingElement && endMessage) {
        const infiniteScroll = new InfiniteScroll(async () => {
            const result = await entriesManager.loadEntries('/api/entries', {
                cursor: nextCursor,
                limit: 100,
                container: entriesContainer,
                cardOptions: {
                    showSourceBadge: false,
                    canModify: (entry) => canModifyEntry(entry, sessionState),
                    isAdmin: sessionState.isAdmin,
                    isLoggedIn: sessionState.isLoggedIn,
                    currentUserId: sessionState.userId
                }
            });

            nextCursor = result.next_cursor;
            
            if (result.entries.length === 0 && entriesContainer.children.length === 0) {
                showEmptyState(entriesContainer, {
                    icon: 'fa-file-lines',
                    title: 'No entries yet',
                    message: 'Be the first to share something!'
                });
            }

            return { hasMore: result.has_more };
        }, {
            threshold: 500,
            loadingElement: loadingElement,
            endElement: endMessage
        });
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
})();

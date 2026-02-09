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
    let totalResultsCount = 0;
    const entriesContainer = document.getElementById('entriesContainer');
    const loadingElement = document.getElementById('loading');
    const endMessage = document.getElementById('endMessage');
    const createPostSection = document.querySelector('.create-post-section');

    if (entriesContainer && loadingElement && endMessage) {
        const infiniteScroll = new InfiniteScroll(async () => {
            const result = await entriesManager.loadEntries('/api/entries', {
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
                        message: 'Be the first to share something!'
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
                onSearch: (query) => {
                    // Update current search query
                    currentSearchQuery = query;
                    totalResultsCount = 0;
                    
                    // Show/hide create post section based on search
                    if (createPostSection) {
                        if (query) {
                            createPostSection.style.display = 'none';
                        } else {
                            createPostSection.style.display = 'block';
                        }
                    }
                    
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

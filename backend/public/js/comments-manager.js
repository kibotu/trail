/**
 * Comments Manager
 * Handles comment display, creation, editing, deletion, claps, and reporting
 */

class CommentsManager {
    constructor() {
        this.expandedEntries = new Set(); // Track which entries have comments expanded
        this.commentsCache = new Map(); // Cache loaded comments per entry
        this.initializeGlobalListeners();
    }

    initializeGlobalListeners() {
        // Comment button click handler
        document.addEventListener('click', (e) => {
            const commentButton = e.target.closest('.comment-button');
            if (commentButton) {
                e.stopPropagation();
                
                // Check if we're on status page (single entry view)
                const isStatusPage = document.body.classList.contains('page-status');
                
                if (isStatusPage) {
                    // On status page: toggle expand/collapse
                    const entryId = parseInt(commentButton.dataset.entryId);
                    const hashId = commentButton.dataset.hashId;
                    this.toggleComments(entryId, hashId, commentButton);
                } else {
                    // On feed pages: navigate to status page
                    const hashId = commentButton.dataset.hashId;
                    window.location.href = `/status/${hashId}`;
                }
            }

            // Comment menu toggle
            const commentMenuButton = e.target.closest('.comment-menu-button');
            if (commentMenuButton) {
                e.stopPropagation();
                const commentId = commentMenuButton.dataset.commentId;
                this.toggleCommentMenu(commentId);
            }

            // Close comment menus when clicking outside
            if (!e.target.closest('.comment-menu-button') && !e.target.closest('.comment-menu-dropdown')) {
                document.querySelectorAll('.comment-menu-dropdown').forEach(menu => {
                    menu.style.display = 'none';
                });
            }
        });
    }

    async toggleComments(entryId, hashId, button) {
        const card = button.closest('.entry-card');
        const isExpanded = this.expandedEntries.has(entryId);
        
        if (isExpanded) {
            this.collapseComments(entryId, card, button);
        } else {
            await this.expandComments(entryId, hashId, card, button);
        }
    }

    async expandComments(entryId, hashId, card, button) {
        this.expandedEntries.add(entryId);
        button.classList.add('active');
        const icon = button.querySelector('i');
        icon.className = 'fa-solid fa-comment';
        
        // Create or show comments section
        let commentsSection = card.querySelector('.comments-section');
        if (!commentsSection) {
            commentsSection = this.createCommentsSection(entryId, hashId);
            card.appendChild(commentsSection);
            
            // Initialize image uploader after section is in DOM
            const isLoggedIn = document.body.dataset.isLoggedIn === 'true';
            if (isLoggedIn) {
                this.initializeImageUploader(commentsSection, entryId);
            }
            
            // Load comments if not cached
            if (!this.commentsCache.has(entryId)) {
                await this.loadComments(entryId, hashId, commentsSection);
            } else {
                this.renderComments(commentsSection, this.commentsCache.get(entryId), entryId, hashId);
            }
        } else {
            commentsSection.style.display = 'block';
        }
    }

    collapseComments(entryId, card, button) {
        this.expandedEntries.delete(entryId);
        button.classList.remove('active');
        const icon = button.querySelector('i');
        icon.className = 'fa-regular fa-comment';
        
        const commentsSection = card.querySelector('.comments-section');
        if (commentsSection) {
            commentsSection.style.display = 'none';
        }
    }

    createCommentsSection(entryId, hashId) {
        const section = document.createElement('div');
        section.className = 'comments-section';
        section.dataset.entryId = entryId;
        section.dataset.hashId = hashId;
        
        const isLoggedIn = document.body.dataset.isLoggedIn === 'true';
        
        section.innerHTML = `
            ${isLoggedIn ? `
                <div class="comment-input-container">
                    <textarea 
                        class="comment-input" 
                        placeholder="Add a comment..."
                        maxlength="280"
                        rows="2"
                        data-entry-id="${entryId}"
                    ></textarea>
                    <div id="comment-image-upload-${entryId}" class="comment-image-upload-container"></div>
                    <div class="comment-input-footer">
                        <span class="char-counter">0 / 280</span>
                        <button class="comment-submit-button" data-entry-id="${entryId}" disabled>
                            <i class="fa-solid fa-paper-plane"></i>
                            Comment
                        </button>
                    </div>
                </div>
            ` : ''}
            <div class="comments-list" data-entry-id="${entryId}">
                <div class="loading-comments">
                    <div class="loading-spinner-small"></div>
                    <span>Loading comments...</span>
                </div>
            </div>
        `;
        
        // Add basic event listeners (image uploader will be initialized after DOM insertion)
        if (isLoggedIn) {
            this.attachBasicCommentInputListeners(section, entryId, hashId);
        }
        
        return section;
    }
    
    initializeImageUploader(section, entryId) {
        const textarea = section.querySelector('.comment-input');
        const submitButton = section.querySelector('.comment-submit-button');
        
        // Initialize image uploader
        window[`commentImageIds_${entryId}`] = [];
        
        // Check if container exists before initializing
        const containerId = `comment-image-upload-${entryId}`;
        const container = document.getElementById(containerId);
        
        if (container) {
            const imageUploader = createImageUploadUI(
                'post',
                containerId,
                (result) => {
                    window[`commentImageIds_${entryId}`].push(result.image_id);
                    // Update submit button state
                    const hasImages = window[`commentImageIds_${entryId}`].length > 0;
                    const length = textarea.value.length;
                    submitButton.disabled = (length === 0 && !hasImages) || length > 280;
                },
                (imageId) => {
                    const ids = window[`commentImageIds_${entryId}`];
                    const index = ids.indexOf(imageId);
                    if (index > -1) ids.splice(index, 1);
                    // Update submit button state
                    const hasImages = window[`commentImageIds_${entryId}`].length > 0;
                    const length = textarea.value.length;
                    submitButton.disabled = (length === 0 && !hasImages) || length > 280;
                }
            );
            window[`commentImageUploader_${entryId}`] = imageUploader;
        } else {
            console.warn(`Image upload container not found: ${containerId}`);
        }
    }

    async loadComments(entryId, hashId, commentsSection) {
        const commentsList = commentsSection.querySelector('.comments-list');
        
        try {
            const response = await fetch(`/api/entries/${hashId}/comments`, {
                credentials: 'same-origin'
            });
            
            if (!response.ok) throw new Error('Failed to load comments');
            
            const data = await response.json();
            this.commentsCache.set(entryId, data.comments);
            this.renderComments(commentsSection, data.comments, entryId, hashId);
        } catch (error) {
            console.error('Error loading comments:', error);
            commentsList.innerHTML = '<div class="error-message">Failed to load comments</div>';
        }
    }

    renderComments(commentsSection, comments, entryId, hashId) {
        const commentsList = commentsSection.querySelector('.comments-list');
        
        if (comments.length === 0) {
            commentsList.innerHTML = `
                <div class="empty-comments">
                    <i class="fa-regular fa-comment"></i>
                    <p>No comments yet. Be the first to comment!</p>
                </div>
            `;
            return;
        }
        
        commentsList.innerHTML = comments.map(comment => 
            this.createCommentCard(comment, entryId, hashId)
        ).join('');
        
        // Attach event listeners to comment actions
        this.attachCommentActionListeners(commentsList, entryId, hashId);
    }

    createCommentCard(comment, entryId, hashId) {
        const currentUserId = parseInt(document.body.dataset.userId);
        const isAdmin = document.body.dataset.isAdmin === 'true';
        const canModify = currentUserId === comment.user_id || isAdmin;
        const isLoggedIn = document.body.dataset.isLoggedIn === 'true';
        
        return `
            <div class="comment-card" data-comment-id="${comment.id}">
                <div class="comment-header">
                    <img src="${escapeHtml(comment.avatar_url)}" alt="${escapeHtml(comment.user_nickname || comment.user_name)}" class="comment-avatar">
                    <div class="comment-header-info">
                        <span class="comment-user-name">${escapeHtml(comment.user_nickname || comment.user_name)}</span>
                        <span class="comment-timestamp">${formatTimestamp(comment.created_at)}</span>
                        ${comment.updated_at && comment.updated_at !== comment.created_at ? 
                            '<span class="comment-edited">• edited</span>' : ''}
                    </div>
                    ${canModify || isLoggedIn ? `
                        <button class="comment-menu-button" data-comment-id="${comment.id}" data-no-navigate>
                            ⋯
                        </button>
                        <div class="comment-menu-dropdown" id="comment-menu-${comment.id}" style="display: none;">
                            ${canModify ? `
                                <button class="menu-item" data-comment-id="${comment.id}" data-entry-id="${entryId}" data-action="edit-comment">
                                    <i class="fa-solid fa-pen"></i>
                                    <span>Edit</span>
                                </button>
                                <button class="menu-item delete" data-comment-id="${comment.id}" data-entry-id="${entryId}" data-hash-id="${hashId}" data-action="delete-comment">
                                    <i class="fa-solid fa-trash"></i>
                                    <span>Delete</span>
                                </button>
                            ` : `
                                <button class="menu-item" data-comment-id="${comment.id}" data-entry-id="${entryId}" data-hash-id="${hashId}" data-action="report-comment">
                                    <i class="fa-solid fa-flag"></i>
                                    <span>Report</span>
                                </button>
                            `}
                        </div>
                    ` : ''}
                </div>
                <div class="comment-body">
                    <div class="comment-text" data-comment-id="${comment.id}">${linkifyText(linkifyMentions(escapeHtml(comment.text)))}</div>
                    ${comment.images && comment.images.length > 0 ? `
                        <div class="comment-images">
                            ${comment.images.map(img => `
                                <img src="${escapeHtml(img.url)}" 
                                     alt="Comment image" 
                                     class="comment-image"
                                     loading="lazy">
                            `).join('')}
                        </div>
                    ` : ''}
                </div>
                <div class="comment-footer">
                    <div class="comment-footer-left"></div>
                    <div class="comment-footer-right">
                        ${isLoggedIn ? `
                            <button class="comment-clap-button ${(comment.user_clap_count || 0) > 0 ? 'clapped' : ''} ${currentUserId === comment.user_id ? 'own-comment' : ''}" 
                                    data-no-navigate
                                    data-comment-id="${comment.id}"
                                    data-entry-id="${entryId}"
                                    data-hash-id="${hashId}"
                                    data-user-claps="${comment.user_clap_count || 0}"
                                    data-total-claps="${comment.clap_count || 0}"
                                    data-is-own="${currentUserId === comment.user_id ? 'true' : 'false'}"
                                    aria-label="${currentUserId === comment.user_id ? 'Your comment' : 'Clap for this comment'}">
                                <i class="fa-${(comment.user_clap_count || 0) > 0 ? 'solid' : 'regular'} fa-heart"></i>
                                <span class="clap-count">${comment.clap_count || 0}</span>
                            </button>
                        ` : `
                            ${comment.clap_count > 0 ? `
                                <div class="comment-clap-display">
                                    <i class="fa-regular fa-heart"></i>
                                    <span class="clap-count">${comment.clap_count}</span>
                                </div>
                            ` : ''}
                        `}
                    </div>
                </div>
            </div>
        `;
    }

    attachBasicCommentInputListeners(section, entryId, hashId) {
        const textarea = section.querySelector('.comment-input');
        const submitButton = section.querySelector('.comment-submit-button');
        const charCounter = section.querySelector('.char-counter');
        
        // Character counter
        textarea.addEventListener('input', () => {
            const length = textarea.value.length;
            const hasImages = (window[`commentImageIds_${entryId}`] || []).length > 0;
            charCounter.textContent = `${length} / 280`;
            submitButton.disabled = (length === 0 && !hasImages) || length > 280;
        });
        
        // Submit comment
        submitButton.addEventListener('click', async () => {
            await this.createComment(entryId, hashId, textarea.value, section);
        });

        // Submit on Ctrl+Enter or Cmd+Enter
        textarea.addEventListener('keydown', (e) => {
            if ((e.ctrlKey || e.metaKey) && e.key === 'Enter') {
                const hasImages = (window[`commentImageIds_${entryId}`] || []).length > 0;
                if ((textarea.value.trim().length > 0 || hasImages) && textarea.value.length <= 280) {
                    submitButton.click();
                }
            }
        });
    }

    attachCommentActionListeners(commentsList, entryId, hashId) {
        // Edit comment
        commentsList.querySelectorAll('[data-action="edit-comment"]').forEach(button => {
            button.addEventListener('click', async (e) => {
                e.stopPropagation();
                const commentId = parseInt(button.dataset.commentId);
                const commentCard = commentsList.querySelector(`[data-comment-id="${commentId}"]`);
                const commentText = commentCard.querySelector('.comment-text').textContent;
                await this.editComment(commentId, commentText, entryId, hashId, commentCard);
            });
        });

        // Delete comment
        commentsList.querySelectorAll('[data-action="delete-comment"]').forEach(button => {
            button.addEventListener('click', async (e) => {
                e.stopPropagation();
                const commentId = parseInt(button.dataset.commentId);
                await this.deleteComment(commentId, entryId, hashId);
            });
        });

        // Report comment
        commentsList.querySelectorAll('[data-action="report-comment"]').forEach(button => {
            button.addEventListener('click', async (e) => {
                e.stopPropagation();
                const commentId = parseInt(button.dataset.commentId);
                await this.reportComment(commentId, entryId, hashId);
            });
        });

        // Clap comment
        commentsList.querySelectorAll('.comment-clap-button').forEach(button => {
            button.addEventListener('click', async (e) => {
                e.stopPropagation();
                
                // Check if this is the user's own comment
                const isOwnComment = button.dataset.isOwn === 'true';
                if (isOwnComment) {
                    button.classList.add('clap-own-entry-shake');
                    setTimeout(() => button.classList.remove('clap-own-entry-shake'), 500);
                    return;
                }
                
                const commentId = parseInt(button.dataset.commentId);
                let userClaps = parseInt(button.dataset.userClaps) || 0;
                let totalClaps = parseInt(button.dataset.totalClaps) || 0;
                
                // Check if user has reached the limit
                if (userClaps >= 50) {
                    button.classList.add('clap-limit-reached');
                    setTimeout(() => button.classList.remove('clap-limit-reached'), 500);
                    return;
                }
                
                // Increment claps
                userClaps++;
                totalClaps++;
                
                // Optimistic UI update
                button.dataset.userClaps = userClaps;
                button.dataset.totalClaps = totalClaps;
                button.classList.add('clapped', 'clap-animation');
                
                // Update icon
                const icon = button.querySelector('i');
                icon.className = 'fa-solid fa-heart';
                
                // Update count
                const countSpan = button.querySelector('.clap-count');
                countSpan.textContent = totalClaps;
                
                // Create particle explosion
                createClapParticles(button, e.clientX, e.clientY);
                
                // Remove animation class
                setTimeout(() => button.classList.remove('clap-animation'), 300);
                
                // Call API
                await this.clapComment(commentId, userClaps, entryId, hashId, button);
            });
        });
    }

    async createComment(entryId, hashId, text, section) {
        const submitButton = section.querySelector('.comment-submit-button');
        const textarea = section.querySelector('.comment-input');
        const imageIds = window[`commentImageIds_${entryId}`] || [];
        
        // Validate: either text or images must be provided
        if (!text.trim() && imageIds.length === 0) return;
        
        // Disable button and show loading
        submitButton.disabled = true;
        submitButton.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Posting...';
        
        try {
            const token = localStorage.getItem('jwt_token');
            const payload = { text: text.trim() };
            if (imageIds.length > 0) {
                payload.image_ids = imageIds;
            }
            
            const response = await fetch(`/api/entries/${hashId}/comments`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    ...(token ? { 'Authorization': `Bearer ${token}` } : {})
                },
                credentials: 'same-origin',
                body: JSON.stringify(payload)
            });
            
            if (!response.ok) {
                const error = await response.json();
                throw new Error(error.error || 'Failed to create comment');
            }
            
            // Clear input
            textarea.value = '';
            section.querySelector('.char-counter').textContent = '0 / 280';
            
            // Clear images
            window[`commentImageIds_${entryId}`] = [];
            if (window[`commentImageUploader_${entryId}`]) {
                window[`commentImageUploader_${entryId}`].clearPreviews();
            }
            
            // Reload comments
            await this.loadComments(entryId, hashId, section);
            
            // Update comment count in button
            this.updateCommentCount(entryId);
            
            // Show success message
            if (typeof showSnackbar === 'function') {
                showSnackbar('Comment posted!', 'success');
            }
        } catch (error) {
            console.error('Error creating comment:', error);
            if (typeof showSnackbar === 'function') {
                showSnackbar(error.message || 'Failed to post comment', 'error');
            }
        } finally {
            submitButton.disabled = false;
            submitButton.innerHTML = '<i class="fa-solid fa-paper-plane"></i> Comment';
        }
    }

    async editComment(commentId, currentText, entryId, hashId, commentCard) {
        const commentBody = commentCard.querySelector('.comment-body');
        const originalHtml = commentBody.innerHTML;
        
        // Replace with edit form
        commentBody.innerHTML = `
            <div class="comment-edit-form">
                <textarea class="comment-input" maxlength="280" rows="3">${escapeHtml(currentText)}</textarea>
                <div class="comment-input-footer">
                    <span class="char-counter">${currentText.length} / 280</span>
                    <div style="display: flex; gap: 0.5rem;">
                        <button class="comment-cancel-button">Cancel</button>
                        <button class="comment-save-button">
                            <i class="fa-solid fa-check"></i>
                            Save
                        </button>
                    </div>
                </div>
            </div>
        `;
        
        const textarea = commentBody.querySelector('.comment-input');
        const saveButton = commentBody.querySelector('.comment-save-button');
        const cancelButton = commentBody.querySelector('.comment-cancel-button');
        const charCounter = commentBody.querySelector('.char-counter');
        
        // Character counter
        textarea.addEventListener('input', () => {
            const length = textarea.value.length;
            charCounter.textContent = `${length} / 280`;
            saveButton.disabled = length === 0 || length > 280 || textarea.value === currentText;
        });
        
        // Focus textarea
        textarea.focus();
        textarea.setSelectionRange(textarea.value.length, textarea.value.length);
        
        // Cancel edit
        cancelButton.addEventListener('click', () => {
            commentBody.innerHTML = originalHtml;
        });
        
        // Save edit
        saveButton.addEventListener('click', async () => {
            const newText = textarea.value.trim();
            if (!newText || newText === currentText) return;
            
            saveButton.disabled = true;
            saveButton.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Saving...';
            
            try {
                const token = localStorage.getItem('jwt_token');
                const response = await fetch(`/api/comments/${commentId}`, {
                    method: 'PUT',
                    headers: {
                        'Content-Type': 'application/json',
                        ...(token ? { 'Authorization': `Bearer ${token}` } : {})
                    },
                    credentials: 'same-origin',
                    body: JSON.stringify({ text: newText })
                });
                
                if (!response.ok) {
                    const error = await response.json();
                    throw new Error(error.error || 'Failed to update comment');
                }
                
                // Reload comments
                const section = commentCard.closest('.comments-section');
                await this.loadComments(entryId, hashId, section);
                
                if (typeof showSnackbar === 'function') {
                    showSnackbar('Comment updated!', 'success');
                }
            } catch (error) {
                console.error('Error updating comment:', error);
                if (typeof showSnackbar === 'function') {
                    showSnackbar(error.message || 'Failed to update comment', 'error');
                }
                commentBody.innerHTML = originalHtml;
            }
        });
    }

    async deleteComment(commentId, entryId, hashId) {
        if (!confirm('Are you sure you want to delete this comment?')) {
            return;
        }
        
        try {
            const token = localStorage.getItem('jwt_token');
            const response = await fetch(`/api/comments/${commentId}`, {
                method: 'DELETE',
                headers: {
                    ...(token ? { 'Authorization': `Bearer ${token}` } : {})
                },
                credentials: 'same-origin'
            });
            
            if (!response.ok) {
                const error = await response.json();
                throw new Error(error.error || 'Failed to delete comment');
            }
            
            // Reload comments
            const card = document.querySelector(`[data-entry-id="${entryId}"]`);
            const section = card.querySelector('.comments-section');
            await this.loadComments(entryId, hashId, section);
            
            // Update comment count in button
            this.updateCommentCount(entryId);
            
            if (typeof showSnackbar === 'function') {
                showSnackbar('Comment deleted', 'success');
            }
        } catch (error) {
            console.error('Error deleting comment:', error);
            if (typeof showSnackbar === 'function') {
                showSnackbar(error.message || 'Failed to delete comment', 'error');
            }
        }
    }

    async clapComment(commentId, userClaps, entryId, hashId, button) {
        const icon = button.querySelector('i');
        const countSpan = button.querySelector('.clap-count');
        const originalUserClaps = parseInt(button.dataset.userClaps) || 0;
        const originalTotalClaps = parseInt(button.dataset.totalClaps) || 0;
        
        try {
            const token = localStorage.getItem('jwt_token');
            const response = await fetch(`/api/comments/${commentId}/claps`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    ...(token ? { 'Authorization': `Bearer ${token}` } : {})
                },
                credentials: 'same-origin',
                body: JSON.stringify({ count: userClaps })
            });
            
            if (!response.ok) {
                throw new Error('Failed to clap comment');
            }
            
            const data = await response.json();
            
            // Update with actual values from server
            button.dataset.userClaps = data.user_claps || 0;
            button.dataset.totalClaps = data.total_claps || 0;
            countSpan.textContent = data.total_claps || 0;
            
            // Update cache
            const comments = this.commentsCache.get(entryId) || [];
            const commentIndex = comments.findIndex(c => c.id === commentId);
            if (commentIndex !== -1) {
                comments[commentIndex].clap_count = data.total_claps || 0;
                comments[commentIndex].user_clap_count = data.user_claps || 0;
            }
        } catch (error) {
            console.error('Error clapping comment:', error);
            
            // Revert optimistic update
            button.dataset.userClaps = originalUserClaps;
            button.dataset.totalClaps = originalTotalClaps;
            
            if (originalUserClaps > 0) {
                button.classList.add('clapped');
                icon.className = 'fa-solid fa-heart';
            } else {
                button.classList.remove('clapped');
                icon.className = 'fa-regular fa-heart';
            }
            countSpan.textContent = originalTotalClaps;
            
            if (typeof showSnackbar === 'function') {
                showSnackbar('Failed to clap comment', 'error');
            }
        }
    }

    async reportComment(commentId, entryId, hashId) {
        if (!confirm('Report this comment as inappropriate?')) {
            return;
        }
        
        try {
            const token = localStorage.getItem('jwt_token');
            const response = await fetch(`/api/comments/${commentId}/report`, {
                method: 'POST',
                headers: {
                    ...(token ? { 'Authorization': `Bearer ${token}` } : {})
                },
                credentials: 'same-origin'
            });
            
            if (!response.ok) {
                const error = await response.json();
                throw new Error(error.error || 'Failed to report comment');
            }
            
            // Reload comments (reported comment will be hidden)
            const card = document.querySelector(`[data-entry-id="${entryId}"]`);
            const section = card.querySelector('.comments-section');
            await this.loadComments(entryId, hashId, section);
            
            if (typeof showSnackbar === 'function') {
                showSnackbar('Comment reported', 'success');
            }
        } catch (error) {
            console.error('Error reporting comment:', error);
            if (typeof showSnackbar === 'function') {
                showSnackbar(error.message || 'Failed to report comment', 'error');
            }
        }
    }

    toggleCommentMenu(commentId) {
        const menu = document.getElementById(`comment-menu-${commentId}`);
        if (!menu) return;
        
        // Close all other menus
        document.querySelectorAll('.comment-menu-dropdown').forEach(m => {
            if (m.id !== `comment-menu-${commentId}`) {
                m.style.display = 'none';
            }
        });
        
        // Toggle this menu
        menu.style.display = menu.style.display === 'none' ? 'block' : 'none';
    }

    updateCommentCount(entryId) {
        // Fetch updated entry to get new comment count
        const card = document.querySelector(`[data-entry-id="${entryId}"]`);
        if (!card) return;
        
        const commentButton = card.querySelector('.comment-button');
        if (!commentButton) return;
        
        const hashId = commentButton.dataset.hashId;
        
        fetch(`/api/entries/${hashId}`, {
            credentials: 'same-origin'
        })
        .then(response => response.json())
        .then(entry => {
            const countSpan = commentButton.querySelector('.comment-count');
            countSpan.textContent = entry.comment_count || 0;
            commentButton.dataset.commentCount = entry.comment_count || 0;
        })
        .catch(error => {
            console.error('Error updating comment count:', error);
        });
    }
}

// Create singleton instance
const commentsManager = new CommentsManager();

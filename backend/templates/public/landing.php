<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="Cache-Control" content="no-cache, no-store, must-revalidate">
    <meta http-equiv="Pragma" content="no-cache">
    <meta http-equiv="Expires" content="0">
    <title>Trail - Public Entries</title>
    <link rel="stylesheet" href="/assets/fonts/fonts.css">
    <link rel="stylesheet" href="/assets/fontawesome/css/all.min.css">
    <link rel="stylesheet" href="/assets/css/main.css">
</head>
<body class="page-landing">
    <div class="orb orb-1"></div>
    <div class="orb orb-2"></div>

    <header>
        <div class="header-banner">
            <canvas id="shader-canvas"></canvas>
        </div>
        <div class="header-content">
            <div class="header-profile-section">
                <div class="header-left">
                    <?php if (isset($isLoggedIn) && $isLoggedIn && isset($userPhotoUrl) && $userPhotoUrl): ?>
                        <img src="<?= htmlspecialchars($userPhotoUrl) ?>" alt="User" class="header-avatar">
                    <?php else: ?>
                        <img src="/assets/app-icon.webp" alt="Trail" class="header-avatar">
                    <?php endif; ?>
                    <div class="header-info">
                        <h1>
                            Trail
                        </h1>
                        <p class="subtitle">Discover what everyone is sharing</p>
                    </div>
                </div>
                
                <?php if (isset($isLoggedIn) && $isLoggedIn): ?>
                    <div class="header-actions">
                        <a href="/api" class="nav-link" aria-label="API Documentation">
                            <i class="fa-solid fa-book"></i>
                        </a>
                        <?php if (isset($isAdmin) && $isAdmin): ?>
                            <a href="/admin" class="nav-link" aria-label="Admin Dashboard">
                                <i class="fa-solid fa-gear"></i>
                            </a>
                        <?php endif; ?>
                        <a href="/profile" class="nav-link" aria-label="Profile">
                            <i class="fa-solid fa-user"></i>
                        </a>
                        <a href="/admin/logout.php" class="logout-button" aria-label="Logout">
                            <i class="fa-solid fa-right-from-bracket"></i>
                        </a>
                    </div>
                <?php elseif (isset($googleAuthUrl) && $googleAuthUrl): ?>
                    <a href="<?= htmlspecialchars($googleAuthUrl) ?>" class="login-button">
                        <svg class="google-icon" viewBox="0 0 24 24" width="20" height="20">
                            <path d="M22.56 12.25c0-.78-.07-1.53-.2-2.25H12v4.26h5.92c-.26 1.37-1.04 2.53-2.21 3.31v2.77h3.57c2.08-1.92 3.28-4.74 3.28-8.09z" fill="#4285F4"/>
                            <path d="M12 23c2.97 0 5.46-.98 7.28-2.66l-3.57-2.77c-.98.66-2.23 1.06-3.71 1.06-2.86 0-5.29-1.93-6.16-4.53H2.18v2.84C3.99 20.53 7.7 23 12 23z" fill="#34A853"/>
                            <path d="M5.84 14.09c-.22-.66-.35-1.36-.35-2.09s.13-1.43.35-2.09V7.07H2.18C1.43 8.55 1 10.22 1 12s.43 3.45 1.18 4.93l2.85-2.22.81-.62z" fill="#FBBC05"/>
                            <path d="M12 5.38c1.62 0 3.06.56 4.21 1.64l3.15-3.15C17.45 2.09 14.97 1 12 1 7.7 1 3.99 3.47 2.18 7.07l3.66 2.84c.87-2.6 3.3-4.53 6.16-4.53z" fill="#EA4335"/>
                        </svg>
                        <span>Sign in with Google</span>
                    </a>
                <?php else: ?>
                    <a href="/admin/login.php" class="login-button">
                        <i class="fa-solid fa-lock"></i>
                        <span>Login</span>
                    </a>
                <?php endif; ?>
            </div>
        </div>
    </header>

    <main>
        <?php if (isset($_GET['error'])): ?>
            <div class="error-message" style="margin-bottom: 2rem;">
                <?= htmlspecialchars($_GET['error']) ?>
            </div>
        <?php endif; ?>
        
        <?php if (isset($isLoggedIn) && $isLoggedIn): ?>
        <div class="create-post-section">
            <div class="create-post-header">
                <i class="fa-solid fa-pen" style="font-size: 1.5rem;"></i>
                <h2>Create a Post</h2>
            </div>
            <form class="post-form" id="createPostForm" onsubmit="return false;">
                <textarea 
                    id="postText" 
                    class="post-textarea" 
                    placeholder="Share a link, thought, or update... (optional, max 280 characters)"
                    maxlength="280"
                    rows="3"
                ></textarea>
                <div id="post-image-upload" style="margin: 1rem 0;"></div>
                <div class="post-form-footer">
                    <span class="char-counter" id="charCounter">0 / 280</span>
                    <button type="submit" class="submit-button" id="submitButton">
                        <i class="fa-solid fa-paper-plane"></i>
                        <span>Post</span>
                    </button>
                </div>
            </form>
            <div id="postMessage" style="display: none;"></div>
        </div>
        <?php endif; ?>
        
        <div class="entries-container" id="entriesContainer">
            <!-- Entries will be loaded here -->
        </div>
        <div class="loading" id="loading" style="display: none;">
            <div class="loading-spinner"></div>
            <p>Loading entries...</p>
        </div>
        <div class="end-message" id="endMessage" style="display: none;">
            <p><i class="fa-solid fa-sparkles"></i> You've reached the end</p>
        </div>
    </main>

    <script src="/js/snackbar.js"></script>
    <script src="/js/card-template.js"></script>
    <script>
        let nextCursor = null;
        let isLoading = false;
        let hasMore = true;

        const entriesContainer = document.getElementById('entriesContainer');
        const loadingElement = document.getElementById('loading');
        const endMessage = document.getElementById('endMessage');

        // User session info (from PHP) - only non-sensitive data
        const isLoggedIn = <?= json_encode($isLoggedIn ?? false) ?>;
        const userId = <?= json_encode($userId ?? null) ?>;
        const userEmail = <?= json_encode($userName ?? null) ?>;
        const isAdmin = <?= json_encode($isAdmin ?? false) ?>;
        // JWT token is stored in httpOnly cookie - not accessible to JavaScript for security

        // Character counter for post form
        if (isLoggedIn) {
            const postText = document.getElementById('postText');
            const charCounter = document.getElementById('charCounter');
            const submitButton = document.getElementById('submitButton');
            const createPostForm = document.getElementById('createPostForm');
            const postMessage = document.getElementById('postMessage');

            // Update character counter and submit button state
            function updateSubmitButton() {
                const length = postText.value.length;
                const hasImages = window.postImageIds && window.postImageIds.length > 0;
                
                charCounter.textContent = `${length} / 280`;
                
                // Update counter color
                charCounter.classList.remove('warning', 'error');
                if (length > 260) {
                    charCounter.classList.add('error');
                } else if (length > 240) {
                    charCounter.classList.add('warning');
                }
                
                // Enable submit if has text OR images (but text can't be too long)
                submitButton.disabled = (length === 0 && !hasImages) || length > 280;
            }
            
            postText.addEventListener('input', updateSubmitButton);

            // Handle form submission
            createPostForm.addEventListener('submit', async function(e) {
                e.preventDefault();
                
                const text = postText.value.trim();
                const hasImages = window.postImageIds && window.postImageIds.length > 0;
                
                // Require either text or images
                if (!text && !hasImages) {
                    showMessage('Please add text or upload an image', 'error');
                    return;
                }
                
                // Check text length if provided
                if (text && text.length > 280) {
                    showMessage('Text must be 280 characters or less', 'error');
                    return;
                }

                if (!isLoggedIn) {
                    showMessage('You must be logged in to post', 'error');
                    return;
                }

                // Disable form during submission
                submitButton.disabled = true;
                postText.disabled = true;
                submitButton.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i><span>Posting...</span>';

                try {
                    // Include image IDs if any were uploaded
                    const payload = { text };
                    if (window.postImageIds && window.postImageIds.length > 0) {
                        payload.image_ids = window.postImageIds;
                    }
                    
                    const response = await fetch('/api/entries', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json'
                        },
                        credentials: 'same-origin', // Include httpOnly cookie with JWT
                        body: JSON.stringify(payload)
                    });

                    if (!response.ok) {
                        const error = await response.json();
                        throw new Error(error.error || 'Failed to create post');
                    }

                    const data = await response.json();
                    
                    // Clear form
                    postText.value = '';
                    charCounter.textContent = '0 / 280';
                    window.postImageIds = [];
                    
                    // Clear image previews
                    if (window.postImageUploader && typeof window.postImageUploader.clearPreviews === 'function') {
                        window.postImageUploader.clearPreviews();
                    }
                    
                    // CELEBRATE! 🎉
                    celebratePost();
                    
                    // Show success message
                    showMessage('<i class="fa-solid fa-check"></i> Post created successfully!', 'success');
                    
                    // Reload entries to show new post
                    setTimeout(() => {
                        location.reload();
                    }, 1500);

                } catch (error) {
                    console.error('Error creating post:', error);
                    showMessage(`Failed to create post: ${error.message}`, 'error');
                } finally {
                    submitButton.disabled = false;
                    postText.disabled = false;
                    submitButton.innerHTML = '<i class="fa-solid fa-paper-plane"></i><span>Post</span>';
                }
            });

            function showMessage(message, type) {
                postMessage.innerHTML = message;
                postMessage.className = type === 'success' ? 'post-success' : 'post-error';
                postMessage.style.display = 'block';
                
                setTimeout(() => {
                    postMessage.style.display = 'none';
                }, 5000);
            }

            // Celebration animation for successful post! 🎉
            function celebratePost() {
                // Create confetti explosion
                createPostConfetti();
                
                // Show floating celebration emojis
                createCelebrationEmojis();
                
                // Add a subtle pulse to the post button
                submitButton.classList.add('celebrate-pulse');
                setTimeout(() => {
                    submitButton.classList.remove('celebrate-pulse');
                }, 1000);
            }

            function createPostConfetti() {
                const colors = ['#4f8cff', '#ec4899', '#f59e0b', '#10b981', '#8b5cf6', '#ef4444', '#06b6d4'];
                const postForm = document.getElementById('createPostForm');
                if (!postForm) return;
                
                const rect = postForm.getBoundingClientRect();
                const centerX = rect.left + rect.width / 2;
                const centerY = rect.top + rect.height / 2;
                
                const confettiCount = 60;
                
                for (let i = 0; i < confettiCount; i++) {
                    const confetti = document.createElement('div');
                    confetti.className = 'post-confetti';
                    confetti.style.left = centerX + 'px';
                    confetti.style.top = centerY + 'px';
                    confetti.style.background = colors[Math.floor(Math.random() * colors.length)];
                    
                    // Random size and shape
                    const size = Math.random() * 6 + 4;
                    confetti.style.width = size + 'px';
                    confetti.style.height = size + 'px';
                    confetti.style.borderRadius = Math.random() > 0.5 ? '50%' : '2px';
                    
                    // Explosive spread in all directions
                    const angle = (Math.PI * 2 * i) / confettiCount + (Math.random() - 0.5) * 0.4;
                    const velocity = Math.random() * 300 + 200;
                    
                    const tx = Math.cos(angle) * velocity;
                    const ty = Math.sin(angle) * velocity - 100; // Slight upward bias
                    
                    confetti.style.setProperty('--tx', tx + 'px');
                    confetti.style.setProperty('--ty', ty + 'px');
                    confetti.style.setProperty('--rotation', (Math.random() * 720 - 360) + 'deg');
                    
                    const duration = Math.random() * 0.8 + 1.2;
                    confetti.style.animation = `post-confetti-burst ${duration}s cubic-bezier(0.25, 0.46, 0.45, 0.94) forwards`;
                    
                    document.body.appendChild(confetti);
                    
                    setTimeout(() => confetti.remove(), duration * 1000 + 100);
                }
            }

            function createCelebrationEmojis() {
                const emojis = ['🎉', '✨', '🚀', '💫', '⭐', '🌟', '🎊', '🔥', '💪', '👏'];
                const postForm = document.getElementById('createPostForm');
                if (!postForm) return;
                
                const rect = postForm.getBoundingClientRect();
                const centerX = rect.left + rect.width / 2;
                const centerY = rect.top + rect.height / 2;
                
                // Create 5-7 floating emojis
                const emojiCount = Math.floor(Math.random() * 3) + 5;
                
                for (let i = 0; i < emojiCount; i++) {
                    const emojiEl = document.createElement('div');
                    emojiEl.className = 'celebration-emoji';
                    emojiEl.textContent = emojis[Math.floor(Math.random() * emojis.length)];
                    
                    // Random starting position around the form
                    const offsetX = (Math.random() - 0.5) * 100;
                    emojiEl.style.left = (centerX + offsetX) + 'px';
                    emojiEl.style.top = centerY + 'px';
                    
                    // Random float direction
                    const floatX = (Math.random() - 0.5) * 150;
                    const floatY = -(Math.random() * 200 + 150);
                    
                    emojiEl.style.setProperty('--float-x', floatX + 'px');
                    emojiEl.style.setProperty('--float-y', floatY + 'px');
                    
                    const duration = Math.random() * 0.5 + 1.5;
                    const delay = Math.random() * 0.3;
                    emojiEl.style.animation = `celebration-emoji-float ${duration}s ease-out ${delay}s forwards`;
                    
                    document.body.appendChild(emojiEl);
                    
                    setTimeout(() => emojiEl.remove(), (duration + delay) * 1000 + 100);
                }
            }
        }

        // Check if current user can modify this entry
        function canModifyEntry(entry) {
            if (!isLoggedIn) return false;
            if (isAdmin) return true;
            return entry.user_email === userEmail;
        }

        // Toggle menu dropdown
        function toggleMenu(event, entryId) {
            event.stopPropagation();
            const menu = document.getElementById(`menu-${entryId}`);
            const allMenus = document.querySelectorAll('.menu-dropdown');
            
            // Close all other menus
            allMenus.forEach(m => {
                if (m !== menu) {
                    m.classList.remove('active');
                }
            });
            
            // Toggle current menu
            menu.classList.toggle('active');
        }

        // Close menus when clicking outside
        document.addEventListener('click', function(event) {
            if (!event.target.closest('.entry-menu')) {
                const allMenus = document.querySelectorAll('.menu-dropdown');
                allMenus.forEach(m => m.classList.remove('active'));
            }
        });

        // Load entries from API
        async function loadEntries() {
            if (isLoading || !hasMore) return;

            isLoading = true;
            loadingElement.style.display = 'block';

            try {
                const url = new URL('/api/entries', window.location.origin);
                url.searchParams.set('limit', '100');
                if (nextCursor) {
                    url.searchParams.set('before', nextCursor);
                }

                const response = await fetch(url);
                if (!response.ok) {
                    throw new Error(`HTTP error! status: ${response.status}`);
                }

                const data = await response.json();

                if (data.entries && data.entries.length > 0) {
                    data.entries.forEach(entry => {
                        // Use shared card template with landing page options
                        const card = createEntryCard(entry, {
                            showSourceBadge: false,              // No source badges on public page
                            canModify: canModifyEntry(entry),    // User-specific permissions
                            isAdmin: isAdmin,                    // Pass admin status
                            isLoggedIn: isLoggedIn,              // Pass login status
                            currentUserId: userId                // Pass current user ID
                        });
                        
                        // Store entry data in card for edit functionality
                        if (entry.image_ids) {
                            card.dataset.imageIds = entry.image_ids;
                        } else if (entry.images && Array.isArray(entry.images)) {
                            card.dataset.imageIds = JSON.stringify(entry.images.map(img => img.id));
                        }
                        
                        entriesContainer.appendChild(card);
                    });

                    nextCursor = data.next_cursor;
                    hasMore = data.has_more;

                    if (!hasMore) {
                        endMessage.style.display = 'block';
                    }
                } else if (entriesContainer.children.length === 0) {
                    // Show empty state only if no entries at all
                    entriesContainer.innerHTML = `
                        <div class="empty-state">
                            <div class="empty-state-icon"><i class="fa-solid fa-file-lines"></i></div>
                            <h2>No entries yet</h2>
                            <p>Be the first to share something!</p>
                        </div>
                    `;
                    hasMore = false;
                }
            } catch (error) {
                console.error('Error loading entries:', error);
                const errorDiv = document.createElement('div');
                errorDiv.className = 'error-message';
                errorDiv.textContent = 'Failed to load entries. Please try again later.';
                entriesContainer.appendChild(errorDiv);
            } finally {
                isLoading = false;
                loadingElement.style.display = 'none';
            }
        }

        // Infinite scroll handler
        function handleScroll() {
            if (isLoading || !hasMore) return;

            const scrollPosition = window.innerHeight + window.scrollY;
            const threshold = document.documentElement.scrollHeight - 500; // Load when 500px from bottom

            if (scrollPosition >= threshold) {
                loadEntries();
            }
        }

        // Initialize
        window.addEventListener('scroll', handleScroll);
        window.addEventListener('resize', handleScroll);

        // Edit entry
        async function editEntry(entryId) {
            // Close the menu
            const menu = document.getElementById(`menu-${entryId}`);
            if (menu) menu.classList.remove('active');

            const card = document.querySelector(`[data-entry-id="${entryId}"]`);
            if (!card) return;

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
            if (!document.getElementById('edit-textarea-styles')) {
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

            // Create edit form with images and preview shown
            contentDiv.innerHTML = `
                <div class="edit-form">
                    <textarea class="edit-textarea" id="edit-text-${entryId}" maxlength="280">${escapeHtml(currentText)}</textarea>
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
            
            cancelButton.addEventListener('click', () => cancelEdit(entryId));
            saveButton.addEventListener('click', () => saveEdit(entryId));

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
                // Prevent click-through on textarea specifically
                textarea.addEventListener('click', (e) => {
                    e.stopPropagation();
                });
                textarea.focus();
                textarea.setSelectionRange(textarea.value.length, textarea.value.length);
            }
        }

        // Cancel edit
        function cancelEdit(entryId) {
            const card = document.querySelector(`[data-entry-id="${entryId}"]`);
            if (!card) return;

            const contentDiv = card.querySelector('.entry-content');
            const originalHtml = contentDiv.dataset.originalHtml;
            
            if (originalHtml) {
                contentDiv.innerHTML = originalHtml;
                delete contentDiv.dataset.originalHtml;
            }
        }

        // Save edit
        async function saveEdit(entryId) {
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

            if (!isLoggedIn) {
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

                // Use fetch with credentials to send httpOnly cookies
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

                // Show success message and reload to display updated content with images
                if (typeof showSnackbar === 'function') {
                    showSnackbar('Entry updated successfully', 'success');
                }
                
                // Reload the page to show updated content with images
                setTimeout(() => {
                    window.location.reload();
                }, 500);

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

        // Delete entry
        async function deleteEntry(entryId) {
            // Close the menu
            const menu = document.getElementById(`menu-${entryId}`);
            if (menu) menu.classList.remove('active');

            if (!confirm('Are you sure you want to delete this entry?')) {
                return;
            }

            if (!isLoggedIn) {
                alert('You must be logged in to delete entries');
                window.location.href = '/admin/login.php';
                return;
            }

            try {
                // Use fetch with credentials to send httpOnly cookies
                const response = await fetch(`/api/entries/${entryId}`, {
                    method: 'DELETE',
                    credentials: 'same-origin'
                });

                if (!response.ok) {
                    const error = await response.json();
                    throw new Error(error.error || 'Failed to delete entry');
                }

                // Remove the card from the DOM
                const card = document.querySelector(`[data-entry-id="${entryId}"]`);
                if (card) {
                    card.style.transition = 'opacity 0.3s, transform 0.3s';
                    card.style.opacity = '0';
                    card.style.transform = 'translateX(-20px)';
                    setTimeout(() => card.remove(), 300);
                }

            } catch (error) {
                console.error('Error deleting entry:', error);
                alert(`Failed to delete entry: ${error.message}`);
            }
        }

        // Load initial entries
        loadEntries();
    </script>
    
    <!-- Image Upload Script -->
    <script src="/js/image-upload.js"></script>
    <script>
        // Initialize post image uploader
        window.postImageIds = [];
        
        if (isLoggedIn) {
            window.addEventListener('DOMContentLoaded', () => {
                window.postImageUploader = createImageUploadUI(
                    'post',
                    'post-image-upload',
                    (result) => {
                        console.log('Post image uploaded:', result);
                        
                        // Handle image removal
                        if (result.removed) {
                            // Remove from postImageIds array
                            const index = window.postImageIds.indexOf(result.image_id);
                            if (index > -1) {
                                window.postImageIds.splice(index, 1);
                            }
                        } else {
                            // Store image ID for submission
                            if (!window.postImageIds) {
                                window.postImageIds = [];
                            }
                            window.postImageIds.push(result.image_id);
                        }
                        
                        // Update submit button state to enable it if we have an image
                        if (typeof updateSubmitButton === 'function') {
                            updateSubmitButton();
                        }
                    }
                );
            });
        }
    </script>

    <!-- Shader Background Script -->
    <script>
        (function() {
            const canvas = document.getElementById('shader-canvas');
            if (!canvas) return;

            const gl = canvas.getContext('webgl2') || canvas.getContext('webgl') || canvas.getContext('experimental-webgl');
            if (!gl) {
                console.warn('WebGL not supported, falling back to gradient');
                canvas.style.background = 'linear-gradient(135deg, rgba(59, 130, 246, 0.4) 0%, rgba(236, 72, 153, 0.3) 50%, rgba(168, 85, 247, 0.3) 100%)';
                return;
            }

            // Vertex shader
            const vertexShaderSource = `
                attribute vec2 position;
                void main() {
                    gl_Position = vec4(position, 0.0, 1.0);
                }
            `;

            // Common shader code (WebGL 1.0 compatible)
            const commonShaderCode = `
                #define PI 3.14159265
                #define saturate(x) clamp(x,0.,1.)
                #define SUNDIR normalize(vec3(0.2,.3,2.))
                #define FOGCOLOR vec3(1.,.2,.1)

                float time;

                float smin( float a, float b, float k ) {
                    float h = max(k-abs(a-b),0.0);
                    return min(a, b) - h*h*0.25/k;
                }

                float smax( float a, float b, float k ) {
                    k *= 1.4;
                    float h = max(k-abs(a-b),0.0);
                    return max(a, b) + h*h*h/(6.0*k*k);
                }

                float box( vec3 p, vec3 b, float r ) {
                    vec3 q = abs(p) - b;
                    return length(max(q,0.0)) + min(max(q.x,max(q.y,q.z)),0.0) - r;
                }

                float capsule( vec3 p, float h, float r ) {
                    p.x -= clamp( p.x, 0.0, h );
                    return length( p ) - r;
                }

                // WebGL1 compatible hash function (no uint)
                vec3 hash3( float n ) {
                    return fract(sin(vec3(n, n+1.0, n+2.0)) * vec3(43758.5453123, 22578.1459123, 19642.3490423));
                }

                float hash( float p ) {
                    return fract(sin(p)*43758.5453123);
                }

                mat2 rot(float v) {
                    float a = cos(v);
                    float b = sin(v);
                    return mat2(a,-b,b,a);
                }

                float train(vec3 p) {
                    vec3 op = p; // original position
                    
                    // base 
                    float d = abs(box(p-vec3(0., 0., 0.), vec3(100.,1.5,5.), 0.))-.1;
                    
                    // windows - repeat along x axis
                    vec3 wp = p;
                    wp.x = mod(wp.x+1.0, 4.0)-2.0; // repeat every 4 units
                    d = smax(d, -box(wp-vec3(0.,0.25,5.), vec3(1.2,.5,0.0), .3), 0.03);
                    
                    // window frames
                    wp.x = mod(op.x-.8, 2.0)-1.0; // repeat every 2 units (aligned with seats)
                    d = smin(d, box(wp-vec3(0.,0.57,5.), vec3(.05,.05,0.1), .0), 0.001);
                    
                    // seats
                    p.x = mod(p.x-.8,2.)-1.;
                    p.z = abs(p.z-4.3)-.3;
                    d = smin(d, box(p-vec3(0.,-1., 0.), vec3(.3,.1-cos(p.z*PI*4.)*.01,.2),.05), 0.05);
                    d = smin(d, box(p-vec3(0.4+pow(p.y+1.,2.)*.1,-0.38, 0.), vec3(.1-cos(p.z*PI*4.)*.01,.7,.2),.05), 0.1);
                    d = smin(d, box(p-vec3(0.1,-1.3, 0.), vec3(.1,.2,.1),.05), 0.01);

                    return d;
                }

                float catenary(vec3 p) {
                    p.z -= 12.;
                    vec3 pp = p;
                    p.x = mod(p.x,100.)-50.;
                    
                    // base
                    float d = box(p-vec3(0.,0.,0.), vec3(.0,3.,.0), .1);
                    d = smin(d, box(p-vec3(0.,2.,0.), vec3(.0,0.,1.), .1), 0.05);
                    p.z = abs(p.z-0.)-2.;
                    d = smin(d, box(p-vec3(0.,2.2,-1.), vec3(.0,0.2,0.), .1), 0.01);
                    
                    // lines
                    pp.z = abs(pp.z-0.)-2.;
                    d = min(d, capsule(p-vec3(-50.,2.4-abs(cos(pp.x*.01*PI)),-1.),10000.,.02));
                    d = min(d, capsule(p-vec3(-50.,2.9-abs(cos(pp.x*.01*PI)),-2.),10000.,.02));
                    
                    return d;
                }

                float city(vec3 p) {
                    vec3 pp = p;
                    vec2 pId = floor((p.xz)/30.);
                    vec3 rnd = hash3(pId.x + pId.y*1000.0);
                    p.xz = mod(p.xz, vec2(30.))-15.;
                    float h = 5.0+(pId.y-3.0)*5.0+rnd.x*20.0;
                    float offset = (rnd.z*2.0-1.0)*10.0;
                    float d = box(p-vec3(offset,-5.,0.), vec3(5.,h,5.), 0.1);
                    d = min(d, box(p-vec3(offset,-5.,0.), vec3(1.,h+pow(rnd.y,4.)*10.,1.), 0.1));
                    d = max(d,-pp.z+100.);
                    d = max(d,pp.z-300.);
                    
                    return d*.6;
                }

                float map(vec3 p) {
                    float d = train(p);
                    // Faster acceleration: starts at 30% speed, reaches full speed in ~5 seconds
                    p.x -= mix(time*4.5, time*15., saturate(time*.2));
                    d = min(d, catenary(p));
                    d = min(d, city(p));
                    d = min(d, city(p+vec3(15.,0.,0.)));
                    return d;
                }
            `;

            // Simplified single-pass shader for performance (48 iterations for header)
            const fragmentShaderSource = `
                precision highp float;
                uniform float u_time;
                uniform vec2 u_resolution;
                
                ${commonShaderCode}

                float trace(vec3 ro, vec3 rd, vec2 nearFar) {
                    float t = nearFar.x;
                    for(int i=0; i<48; i++) {
                        float d = map(ro+rd*t);
                        t += d;
                        if( abs(d) < 0.01 || t > nearFar.y )
                            break;
                    }
                    return t;
                }

                vec3 normal(vec3 p) {
                    vec2 eps = vec2(0.01, 0.);
                    float d = map(p);
                    vec3 n;
                    n.x = d - map(p-eps.xyy);
                    n.y = d - map(p-eps.yxy);
                    n.z = d - map(p-eps.yyx);
                    return normalize(n);
                }

                vec3 skyColor(vec3 rd) {
                    vec3 col = FOGCOLOR;
                    col += vec3(1.,.3,.1)*1. * pow(max(dot(rd,SUNDIR),0.),30.);
                    col += vec3(1.,.3,.1)*10. * pow(max(dot(rd,SUNDIR),0.),10000.);
                    return col;
                }

                void main() {
                    time = u_time;
                    vec2 uv = gl_FragCoord.xy / u_resolution.xy;
                    vec2 v = -1.0+2.0*uv;
                    v.x *= u_resolution.x/u_resolution.y;
                    
                    vec3 ro = vec3(-1.5,-.4,1.2);
                    vec3 rd = normalize(vec3(v, 2.5));
                    rd.xz = rot(.15)*rd.xz;
                    rd.yz = rot(.1)*rd.yz;
                    
                    float t = trace(ro,rd, vec2(0.,300.));
                    vec3 p = ro + rd * t;
                    vec3 n = normal(p);
                    vec3 col = skyColor(rd);
                    
                    if (t < 300.) {
                        vec3 diff = vec3(1.,.5,.3) * max(dot(n,SUNDIR),0.);
                        vec3 amb = vec3(0.1,.15,.2);
                        col = (diff*0.3 + amb*.3)*.02;
                        
                        // Simple reflection for windows
                        if (p.z<6.) {
                            vec3 rrd = reflect(rd,n);
                            float fre = pow( saturate( 1.0 + dot(n,rd)), 8.0 );
                            vec3 rcol = skyColor(rrd);
                            col = mix(col, rcol, fre*.1);
                        }
                        
                        col = mix(col, FOGCOLOR, smoothstep(100.,500.,t));
                    }
                    
                    // Add godrays effect
                    float godray = pow(max(dot(rd,SUNDIR),0.),50.) * 0.3;
                    col += FOGCOLOR * godray * 0.01;
                    
                    // Color correction
                    col = pow(col, vec3(1./2.2));
                    col = pow(col, vec3(.6,1.,.8*(uv.y*.2+.8)));
                    
                    // Vignetting
                    float vignetting = pow(uv.x*uv.y*(1.-uv.x)*(1.-uv.y), .3)*2.5;
                    col *= vignetting;
                    
                    // Fade in (instant - no fade)
                    // col *= smoothstep(0.,10.,u_time);
                    
                    gl_FragColor = vec4(col, 1.0);
                }
            `;

            function createShader(gl, type, source) {
                const shader = gl.createShader(type);
                gl.shaderSource(shader, source);
                gl.compileShader(shader);
                
                if (!gl.getShaderParameter(shader, gl.COMPILE_STATUS)) {
                    console.error('Shader compile error:', gl.getShaderInfoLog(shader));
                    gl.deleteShader(shader);
                    return null;
                }
                
                return shader;
            }

            function createProgram(gl, vertexShader, fragmentShader) {
                const program = gl.createProgram();
                gl.attachShader(program, vertexShader);
                gl.attachShader(program, fragmentShader);
                gl.linkProgram(program);
                
                if (!gl.getProgramParameter(program, gl.LINK_STATUS)) {
                    console.error('Program link error:', gl.getProgramInfoLog(program));
                    gl.deleteProgram(program);
                    return null;
                }
                
                return program;
            }

            const vertexShader = createShader(gl, gl.VERTEX_SHADER, vertexShaderSource);
            const fragmentShader = createShader(gl, gl.FRAGMENT_SHADER, fragmentShaderSource);
            const program = createProgram(gl, vertexShader, fragmentShader);

            if (!program) {
                console.warn('Failed to create shader program');
                canvas.style.background = 'linear-gradient(135deg, rgba(59, 130, 246, 0.4) 0%, rgba(236, 72, 153, 0.3) 50%, rgba(168, 85, 247, 0.3) 100%)';
                return;
            }

            // Set up geometry
            const positionBuffer = gl.createBuffer();
            gl.bindBuffer(gl.ARRAY_BUFFER, positionBuffer);
            const positions = new Float32Array([
                -1, -1,
                 1, -1,
                -1,  1,
                 1,  1,
            ]);
            gl.bufferData(gl.ARRAY_BUFFER, positions, gl.STATIC_DRAW);

            const positionLocation = gl.getAttribLocation(program, 'position');
            const timeLocation = gl.getUniformLocation(program, 'u_time');
            const resolutionLocation = gl.getUniformLocation(program, 'u_resolution');

            function resize() {
                const displayWidth = canvas.clientWidth;
                const displayHeight = canvas.clientHeight;
                
                if (canvas.width !== displayWidth || canvas.height !== displayHeight) {
                    canvas.width = displayWidth;
                    canvas.height = displayHeight;
                    gl.viewport(0, 0, canvas.width, canvas.height);
                }
            }

            function render(time) {
                resize();
                
                gl.clearColor(0, 0, 0, 1);
                gl.clear(gl.COLOR_BUFFER_BIT);
                
                gl.useProgram(program);
                
                gl.enableVertexAttribArray(positionLocation);
                gl.bindBuffer(gl.ARRAY_BUFFER, positionBuffer);
                gl.vertexAttribPointer(positionLocation, 2, gl.FLOAT, false, 0, 0);
                
                gl.uniform1f(timeLocation, time * 0.001);
                gl.uniform2f(resolutionLocation, canvas.width, canvas.height);
                
                gl.drawArrays(gl.TRIANGLE_STRIP, 0, 4);
                
                requestAnimationFrame(render);
            }

            resize();
            window.addEventListener('resize', resize);
            requestAnimationFrame(render);
        })();
    </script>
</body>
</html>

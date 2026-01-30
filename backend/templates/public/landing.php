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

    <!-- Core JavaScript Modules -->
    <script src="/js/snackbar.js"></script>
    <script src="/js/card-template.js"></script>
    <script src="/js/ui-interactions.js"></script>
    <script src="/js/entries-manager.js"></script>
    <script src="/js/infinite-scroll.js"></script>
    <script src="/js/celebrations.js"></script>
    
    <script>
        // Session state from PHP
        const sessionState = {
            isLoggedIn: <?= json_encode($isLoggedIn ?? false) ?>,
            userId: <?= json_encode($userId ?? null) ?>,
            userEmail: <?= json_encode($userName ?? null) ?>,
            isAdmin: <?= json_encode($isAdmin ?? false) ?>
        };

        // Initialize entries manager
        const entriesManager = new EntriesManager({
            sessionState: sessionState
        });

        // Setup menu close handler
        setupMenuCloseHandler();

        // Post form handling (if logged in)
        if (sessionState.isLoggedIn) {
            const postText = document.getElementById('postText');
            const charCounter = document.getElementById('charCounter');
            const submitButton = document.getElementById('submitButton');
            const createPostForm = document.getElementById('createPostForm');
            const postMessage = document.getElementById('postMessage');

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

        // Setup infinite scroll for entries
        let nextCursor = null;
        const entriesContainer = document.getElementById('entriesContainer');
        const loadingElement = document.getElementById('loading');
        const endMessage = document.getElementById('endMessage');

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
    </script>
    
    <!-- Image Upload Script -->
    <script src="/js/image-upload.js"></script>
    <script>
        // Initialize post image uploader
        window.postImageIds = [];
        
        if (sessionState.isLoggedIn) {
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
    </script>

    <!-- Shader Background Script -->
    <script src="/js/shader-background.js"></script>
    <script>
        // Initialize shader background
        initShaderBackground('shader-canvas');
    </script>
</body>
</html>

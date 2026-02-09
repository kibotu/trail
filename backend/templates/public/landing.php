<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="Cache-Control" content="no-cache, no-store, must-revalidate">
    <meta http-equiv="Pragma" content="no-cache">
    <meta http-equiv="Expires" content="0">
    <title>Trail - Public Entries</title>
    <link rel="icon" type="image/x-icon" href="/assets/favicon.ico">
    <link rel="stylesheet" href="/assets/fonts/fonts.css">
    <link rel="stylesheet" href="/assets/fontawesome/css/all.min.css">
    <link rel="stylesheet" href="/assets/css/main.css">
    <?php if (isset($isLoggedIn) && $isLoggedIn): ?>
    <link rel="stylesheet" href="/assets/css/notifications.css">
    <?php endif; ?>
</head>
<body class="page-landing" 
      data-is-logged-in="<?= json_encode($isLoggedIn ?? false) ?>"
      data-user-id="<?= json_encode($userId ?? null) ?>"
      data-user-email="<?= json_encode($userName ?? null) ?>"
      data-is-admin="<?= json_encode($isAdmin ?? false) ?>">
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
                        <!-- Notification Bell -->
                        <div class="notification-bell-container">
                            <button id="notification-bell" class="nav-link" onclick="toggleNotificationDropdown()" aria-label="Notifications">
                                <i class="fa-solid fa-bell"></i>
                                <span id="notification-badge" class="notification-badge hidden">0</span>
                            </button>
                            
                            <!-- Dropdown Menu -->
                            <div id="notification-dropdown" class="notification-dropdown hidden">
                                <div class="notification-dropdown-header">
                                    <h3>Notifications</h3>
                                    <button onclick="markAllAsRead()" class="mark-all-btn">Mark all as read</button>
                                </div>
                                <div id="notification-dropdown-list" class="notification-dropdown-list">
                                    <!-- Populated via AJAX -->
                                </div>
                                <a href="/notifications" class="view-all-link">View All Notifications</a>
                            </div>
                        </div>
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
        
        <!-- Search Section -->
        <div class="search-section" id="searchSection">
            <!-- Populated by SearchManager -->
        </div>
        
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
                    placeholder="Share a link, thought, or update... (optional)"
                    rows="3"
                ></textarea>
                <div id="post-image-upload" style="margin: 1rem 0;"></div>
                <div class="post-form-footer">
                    <span class="char-counter" id="charCounter">0 / ...</span>
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
    <script src="/js/config.js"></script>
    <script src="/js/snackbar.js"></script>
    <script src="/js/card-template.js"></script>
    <script src="/js/ui-interactions.js"></script>
    <script src="/js/entries-manager.js"></script>
    <script src="/js/infinite-scroll.js"></script>
    <script src="/js/celebrations.js"></script>
    <script src="/js/image-upload.js"></script>
    <script src="/js/comments-manager.js"></script>
    <script src="/js/search-manager.js"></script>
    <script src="/js/shader-background.js"></script>
    <script src="/js/scroll-to-top.js"></script>
    <?php if (isset($isLoggedIn) && $isLoggedIn): ?>
    <script src="/js/notifications.js"></script>
    <?php endif; ?>
    
    <!-- Page Initialization -->
    <script src="/js/landing-page.js"></script>
</body>
</html>

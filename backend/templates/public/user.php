<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="Cache-Control" content="no-cache, no-store, must-revalidate">
    <meta http-equiv="Pragma" content="no-cache">
    <meta http-equiv="Expires" content="0">
    <title>@<?= htmlspecialchars($nickname ?? 'user') ?> - Trail</title>
    <link rel="stylesheet" href="/assets/fonts/fonts.css">
    <link rel="stylesheet" href="/assets/fontawesome/css/all.min.css">
    <link rel="stylesheet" href="/assets/css/main.css">
</head>
<body class="page-user">
    <div class="orb orb-1"></div>
    <div class="orb orb-2"></div>

    <header>
        <div class="header-content">
            <a href="/" class="logo">
                <i class="fa-solid fa-link"></i>
                <span>Trail</span>
            </a>
            <div class="header-actions">
                <?php if (isset($isLoggedIn) && $isLoggedIn): ?>
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
        <div class="user-header" id="userHeader" style="display: none;">
            <img id="userAvatar" class="user-avatar" src="" alt="User avatar">
            <div class="user-info">
                <h1 id="userName">Loading...</h1>
                <p class="nickname">@<?= htmlspecialchars($nickname ?? '') ?></p>
            </div>
        </div>

        <div id="errorContainer"></div>

        <div class="entries-container" id="entriesContainer">
            <!-- Entries will be loaded here -->
        </div>
        <div class="loading" id="loading">
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
    
    <script>
        const nickname = <?= json_encode($nickname ?? '') ?>;
        const sessionState = {
            isLoggedIn: <?= json_encode($isLoggedIn ?? false) ?>,
            userId: null,
            userEmail: <?= json_encode($userName ?? null) ?>,
            isAdmin: <?= json_encode($isAdmin ?? false) ?>
        };

        let nextCursor = null;
        let userData = null;

        const entriesContainer = document.getElementById('entriesContainer');
        const loadingElement = document.getElementById('loading');
        const endMessage = document.getElementById('endMessage');
        const userHeader = document.getElementById('userHeader');
        const errorContainer = document.getElementById('errorContainer');

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
            if (result.user && !userData) {
                userData = result.user;
                const avatarUrl = userData.photo_url || 
                    `https://www.gravatar.com/avatar/${userData.gravatar_hash}?s=160&d=mp`;
                document.getElementById('userAvatar').src = avatarUrl;
                document.getElementById('userName').textContent = `@${userData.nickname}`;
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
    </script>
</body>
</html>

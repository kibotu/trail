<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="Cache-Control" content="no-cache, no-store, must-revalidate">
    <meta http-equiv="Pragma" content="no-cache">
    <meta http-equiv="Expires" content="0">
    <title>Profile - Trail</title>
    <link rel="stylesheet" href="/assets/fonts/fonts.css">
    <link rel="stylesheet" href="/assets/fontawesome/css/all.min.css">
    <link rel="stylesheet" href="/assets/css/main.css">
</head>
<body class="page-profile">
    <div class="orb orb-1"></div>
    <div class="orb orb-2"></div>

    <header>
        <div class="header-content">
            <a href="/" class="logo">
                <i class="fa-solid fa-link"></i>
                <span>Trail</span>
            </a>
            <div class="header-actions">
                <a href="/api" class="nav-link" aria-label="API Documentation">
                    <i class="fa-solid fa-book"></i>
                </a>
                <a href="#" id="profileRssLink" class="nav-link" aria-label="RSS Feed" style="display: none;">
                    <i class="fa-solid fa-rss"></i>
                </a>
                <?php if (isset($isAdmin) && $isAdmin): ?>
                    <a href="/admin" class="nav-link" aria-label="Admin Dashboard">
                        <i class="fa-solid fa-gear"></i>
                    </a>
                <?php endif; ?>
                <a href="/admin/logout.php" class="logout-button" aria-label="Logout">
                    <i class="fa-solid fa-right-from-bracket"></i>
                </a>
            </div>
        </div>
    </header>

    <div class="container">
        <div class="profile-card">
            <div id="loading" class="loading">
                <div class="spinner"></div>
                <p>Loading profile...</p>
            </div>

            <div id="profile-content" style="display: none;">
                <div class="profile-header">
                    <img id="profile-avatar" class="profile-avatar" src="" alt="Profile">
                    <div class="profile-info">
                        <h1 id="profile-name"></h1>
                        <p class="email" id="profile-email"></p>
                    </div>
                </div>

                <div id="alert-container"></div>

                <form id="profile-form">
                    <div class="form-group">
                        <label for="nickname">Nickname</label>
                        <input 
                            type="text" 
                            id="nickname" 
                            name="nickname" 
                            placeholder="Enter your nickname"
                            pattern="[a-zA-Z0-9_-]{3,50}"
                            minlength="3"
                            maxlength="50"
                            required
                        >
                        <p class="form-hint">3-50 characters. Letters, numbers, underscore, and hyphen only.</p>
                    </div>

                    <div class="form-group">
                        <label>Profile Image</label>
                        <div id="profile-image-upload"></div>
                        <p class="form-hint">Max 20MB. Formats: JPEG, PNG, GIF, WebP, SVG, AVIF</p>
                    </div>

                    <div class="form-group">
                        <label>Header Image</label>
                        <div id="header-image-upload"></div>
                        <p class="form-hint">Max 20MB. Recommended: 1920x400px</p>
                    </div>

                    <div class="form-group" id="profile-link-group" style="display: none;">
                        <label>Your Profile URL</label>
                        <a id="profile-url" class="profile-link" href="#" target="_blank">
                            <i class="fa-solid fa-link"></i>
                            <span id="profile-url-text"></span>
                        </a>
                    </div>

                    <div class="button-group">
                        <button type="submit" class="btn btn-primary" id="save-btn">
                            Save Changes
                        </button>
                        <button type="button" class="btn btn-secondary" onclick="window.location.href='/'">
                            Cancel
                        </button>
                    </div>
                </form>

                <!-- Muted Users Section -->
                <div class="muted-users-section">
                    <div class="muted-users-header">
                        <h3>Muted Users</h3>
                        <span class="muted-count" id="muted-count">0</span>
                    </div>
                    <div id="muted-users-list" class="muted-users-list">
                        <div class="loading">
                            <div class="spinner"></div>
                            <p>Loading muted users...</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Core JavaScript Modules -->
    <script src="/js/snackbar.js"></script>
    <script src="/js/image-upload.js"></script>
    <script src="/js/profile-manager.js"></script>
    
    <!-- Page Initialization -->
    <script src="/js/profile-page.js"></script>
</body>
</html>

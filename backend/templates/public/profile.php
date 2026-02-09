<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="Cache-Control" content="no-cache, no-store, must-revalidate">
    <meta http-equiv="Pragma" content="no-cache">
    <meta http-equiv="Expires" content="0">
    <title>Profile - Trail</title>
    <link rel="icon" type="image/x-icon" href="/assets/favicon.ico">
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
        <div id="loading" class="loading">
            <div class="spinner"></div>
            <p>Loading profile...</p>
        </div>

        <div id="profile-content" class="profile-layout" style="display: none;">
            <!-- Identity Sidebar -->
            <aside class="profile-sidebar">
                <div class="identity-card">
                    <div class="identity-avatar-container">
                        <img id="identity-avatar" class="identity-avatar" src="" alt="Profile">
                    </div>
                    <h1 id="identity-name" class="identity-name"></h1>
                    <a id="identity-nickname" class="identity-nickname" href="#" style="display: none;">
                        <i class="fa-solid fa-at"></i>
                        <span id="identity-nickname-text"></span>
                    </a>
                    <p id="identity-email" class="identity-email"></p>
                    <p id="identity-member-since" class="identity-member-since"></p>
                </div>
            </aside>

            <!-- Main Content -->
            <main class="profile-main">
                <!-- Account Settings Section -->
                <section class="profile-section">
                    <div class="profile-section-header">
                        <i class="fa-solid fa-user-pen"></i>
                        <h2>Account Settings</h2>
                    </div>

                    <form id="profile-form">
                        <div class="form-group">
                            <label for="nickname">Nickname</label>
                            <div class="input-with-validation">
                                <input 
                                    type="text" 
                                    id="nickname" 
                                    name="nickname" 
                                    placeholder="Enter your nickname"
                                    pattern="[a-zA-Z0-9_\-]{3,50}"
                                    minlength="3"
                                    maxlength="50"
                                    required
                                >
                                <span id="nickname-validation" class="validation-icon" style="display: none;"></span>
                            </div>
                            <p class="form-hint">3-50 characters. Letters, numbers, underscore, and hyphen only.</p>
                        </div>

                        <div class="form-group">
                            <label for="bio">Bio</label>
                            <textarea 
                                id="bio" 
                                name="bio" 
                                placeholder="Tell us about yourself"
                                maxlength="160"
                                rows="5"
                            ></textarea>
                            <div class="bio-counter-bar">
                                <div id="bio-counter-fill" class="bio-counter-fill"></div>
                            </div>
                            <div class="bio-counter-text">
                                <span class="form-hint">
                                    <span id="bio-counter">0</span>/160 characters • Auto-saved
                                </span>
                            </div>
                        </div>
                    </form>
                </section>

                <!-- Developer Tools Section -->
                <section class="profile-section">
                    <div class="profile-section-header">
                        <i class="fa-solid fa-code"></i>
                        <h2>Developer Tools</h2>
                    </div>

                    <div class="token-display">
                        <div class="token-value-row">
                            <code id="api-token-value" class="token-value">
                                ••••••••••••••••••••••••••••••••••••••••••••••••••••••••••••••••
                            </code>
                        </div>
                        <div class="token-actions">
                            <button type="button" class="btn btn-icon" id="toggle-token-btn" 
                                    title="Show/hide token">
                                <i class="fa-solid fa-eye"></i>
                            </button>
                            <button type="button" class="btn btn-icon" id="copy-token-btn" 
                                    title="Copy to clipboard">
                                <i class="fa-solid fa-copy"></i>
                            </button>
                            <button type="button" class="btn btn-icon" id="regenerate-token-btn" 
                                    title="Regenerate API token">
                                <i class="fa-solid fa-rotate"></i>
                            </button>
                        </div>
                        <p class="token-meta">
                            Created: <span id="api-token-created">Loading...</span>
                        </p>
                    </div>

                    <div class="token-hint">
                        <strong>How to use:</strong> Include your API token in the Authorization header
                        <code>curl -H "Authorization: Bearer YOUR_TOKEN" https://trail.services.kibotu.net/api/profile</code>
                    </div>

                    <a href="/api" class="api-docs-link">
                        <i class="fa-solid fa-book"></i>
                        <span>View Full API Documentation</span>
                        <i class="fa-solid fa-arrow-right"></i>
                    </a>
                </section>

                <!-- Privacy Section -->
                <section class="profile-section">
                    <div class="profile-section-header">
                        <i class="fa-solid fa-shield-halved"></i>
                        <h2>Privacy</h2>
                        <span class="section-badge" id="muted-count">0</span>
                    </div>

                    <div id="muted-users-list" class="muted-users-list">
                        <div class="loading">
                            <div class="spinner"></div>
                            <p>Loading muted users...</p>
                        </div>
                    </div>
                </section>
            </main>
        </div>
    </div>

    <!-- Core JavaScript Modules -->
    <script src="/js/snackbar.js"></script>
    <script src="/js/profile-manager.js"></script>
    <script src="/js/api-token-manager.js"></script>
    
    <!-- Page Initialization -->
    <script src="/js/profile-page.js"></script>
</body>
</html>

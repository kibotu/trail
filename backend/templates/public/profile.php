<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="Cache-Control" content="no-cache, no-store, must-revalidate">
    <meta http-equiv="Pragma" content="no-cache">
    <meta http-equiv="Expires" content="0">
    <meta name="description" content="Manage your Trail profile, settings, and activity.">
    <title>Profile - Trail</title>
    <link rel="icon" type="image/x-icon" href="/assets/favicon.ico">
    <link rel="stylesheet" href="/assets/fonts/fonts.css">
    <link rel="stylesheet" href="/assets/fontawesome/css/fontawesome.min.css">
    <link rel="stylesheet" href="/assets/fontawesome/css/solid.min.css">
    <link rel="stylesheet" href="/assets/fontawesome/css/regular.min.css">
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

                    <div class="identity-stats" id="identityStats" style="display:none;">
                        <div class="identity-stat">
                            <span class="identity-stat-value" id="identityStatEntries">0</span>
                            <span class="identity-stat-label">Entries</span>
                        </div>
                        <div class="identity-stat">
                            <span class="identity-stat-value" id="identityStatLinks">0</span>
                            <span class="identity-stat-label">Links</span>
                        </div>
                        <div class="identity-stat">
                            <span class="identity-stat-value" id="identityStatComments">0</span>
                            <span class="identity-stat-label">Comments</span>
                        </div>
                    </div>

                    <div class="identity-stats identity-view-stats" id="identityViewStats" style="display:none;">
                        <div class="identity-stat">
                            <span class="identity-stat-value" id="identityStatEntryViews">0</span>
                            <span class="identity-stat-label">Entry Views</span>
                        </div>
                        <div class="identity-stat">
                            <span class="identity-stat-value" id="identityStatCommentViews">0</span>
                            <span class="identity-stat-label">Comment Views</span>
                        </div>
                        <div class="identity-stat">
                            <span class="identity-stat-value" id="identityStatProfileViews">0</span>
                            <span class="identity-stat-label">Profile Views</span>
                        </div>
                    </div>

                    <div class="identity-stats identity-clap-stats" id="identityClapStats" style="display:none;">
                        <div class="identity-stat">
                            <span class="identity-stat-value" id="identityStatTotalClaps">0</span>
                            <span class="identity-stat-label"><i class="fa-solid fa-heart"></i> Total Claps</span>
                        </div>
                    </div>

                    <div class="identity-top-entries" id="identityTopEntriesByClaps" style="display:none;">
                        <h4 class="identity-top-entries-title"><i class="fa-solid fa-heart"></i> Top by Claps</h4>
                        <ul class="identity-top-entries-list" id="topEntriesByClapslist"></ul>
                    </div>

                    <div class="identity-top-entries" id="identityTopEntriesByViews" style="display:none;">
                        <h4 class="identity-top-entries-title"><i class="fa-solid fa-chart-simple"></i> Top by Views</h4>
                        <ul class="identity-top-entries-list" id="topEntriesByViewsList"></ul>
                    </div>

                    <div class="identity-meta" id="identityMeta" style="display:none;">
                        <p class="identity-meta-item" id="identityLastSeen" style="display:none;">
                            <i class="fa-solid fa-clock"></i>
                            <span></span>
                        </p>
                        <p class="identity-meta-item" id="identityLastEntry" style="display:none;">
                            <i class="fa-solid fa-pen-nib"></i>
                            <span></span>
                        </p>
                    </div>
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

                <!-- Your Data Section -->
                <section class="profile-section data-section">
                    <div class="profile-section-header">
                        <i class="fa-solid fa-download"></i>
                        <h2>Your Data</h2>
                    </div>
                    <p class="data-section-description">Download a complete copy of all your data, including your profile, entries, comments, images, and more. The export is a self-contained HTML file you can open in any browser.</p>
                    <button type="button" class="btn btn-primary" id="exportDataBtn">
                        <i class="fa-solid fa-file-arrow-down"></i>
                        <span>Download My Data</span>
                    </button>
                </section>

                <!-- Feedback Section -->
                <section class="profile-section feedback-section">
                    <div class="feedback-mascot">
                        <img src="/assets/feedback-whale.png" alt="Got Feedback?" loading="lazy">
                    </div>

                    <div class="profile-section-header">
                        <i class="fa-solid fa-comment-dots"></i>
                        <h2>Got Feedback?</h2>
                    </div>

                    <p class="feedback-description">
                        Whether it's a brilliant idea, a pesky bug, or just a compliment to make our day &mdash; we're all ears (and fins).
                    </p>

                    <div class="feedback-categories">
                        <button type="button" class="feedback-chip" data-prefix="Feature Request: ">💡 Feature Request</button>
                        <button type="button" class="feedback-chip" data-prefix="Bug Report: ">🐛 Bug Report</button>
                        <button type="button" class="feedback-chip" data-prefix="Just wanted to say: ">👋 Just Saying Hi</button>
                    </div>

                    <div class="form-group">
                        <div class="feedback-textarea-wrapper">
                            <textarea 
                                id="feedbackText" 
                                placeholder="What's on your mind?"
                                maxlength="5000"
                                rows="4"
                            ></textarea>
                            <button type="button" class="feedback-clear-btn" id="feedbackClearBtn" style="display: none;" title="Clear">
                                <i class="fa-solid fa-xmark"></i>
                            </button>
                        </div>
                        <div class="feedback-meta-row">
                            <span class="feedback-char-count">
                                <span id="feedbackCharCount">0</span> / 5000
                            </span>
                            <span class="feedback-sending-as" id="feedbackSendingAs"></span>
                        </div>
                    </div>

                    <button type="button" class="btn btn-primary" id="submitFeedbackBtn" disabled>
                        <i class="fa-solid fa-paper-plane"></i>
                        <span>Send Feedback</span>
                    </button>
                </section>

                <!-- Embed Section -->
                <section class="profile-section embed-section">
                    <button class="embed-section-toggle" id="embedSectionToggle" type="button" aria-expanded="false" aria-controls="embedSectionContent">
                        <div class="profile-section-header">
                            <i class="fa-solid fa-code"></i>
                            <h2>Embed Your Profile</h2>
                        </div>
                        <i class="fa-solid fa-chevron-down embed-toggle-icon"></i>
                    </button>

                    <div class="embed-section-content" id="embedSectionContent" hidden>
                        <p class="embed-description">
                            Add your Trail feed to any website. Customize the appearance and copy the embed code.
                        </p>

                        <div class="embed-options">
                            <div class="embed-option-group">
                                <label class="embed-option-label">Theme</label>
                                <div class="embed-theme-toggle">
                                    <label class="embed-radio">
                                        <input type="radio" name="embed-theme" value="dark" checked>
                                        <span><i class="fa-solid fa-moon"></i> Dark</span>
                                    </label>
                                    <label class="embed-radio">
                                        <input type="radio" name="embed-theme" value="light">
                                        <span><i class="fa-solid fa-sun"></i> Light</span>
                                    </label>
                                </div>
                            </div>

                            <div class="embed-option-group">
                                <label class="embed-option-label">Options</label>
                                <div class="embed-checkboxes">
                                    <label class="embed-checkbox">
                                        <input type="checkbox" id="embedShowHeader">
                                        <span>Show profile header</span>
                                    </label>
                                    <label class="embed-checkbox">
                                        <input type="checkbox" id="embedShowSearch">
                                        <span>Show search</span>
                                    </label>
                                    <label class="embed-checkbox">
                                        <input type="checkbox" id="embedAutoResize" checked>
                                        <span>Include auto-resize script</span>
                                    </label>
                                </div>
                            </div>
                        </div>

                        <div class="embed-code-container">
                            <label class="embed-option-label">Embed Code</label>
                            <div class="embed-code-block">
                                <code id="embedCodeOutput" class="embed-code"></code>
                                <button type="button" class="btn btn-icon embed-copy-btn" id="embedCopyBtn" title="Copy embed code">
                                    <i class="fa-solid fa-copy"></i>
                                </button>
                            </div>
                        </div>

                        <div class="embed-preview-container">
                            <label class="embed-option-label">Preview</label>
                            <div class="embed-preview-frame">
                                <iframe id="embedPreviewIframe" class="embed-preview-iframe" loading="lazy" sandbox="allow-scripts allow-same-origin allow-popups" allow="web-share; clipboard-write"></iframe>
                            </div>
                        </div>
                    </div>
                </section>

                <!-- Danger Zone -->
                <section class="profile-section danger-zone">
                    <div class="profile-section-header">
                        <i class="fa-solid fa-triangle-exclamation"></i>
                        <h2>Danger Zone</h2>
                    </div>
                    <p class="danger-zone-description">Once your account is deleted, your profile, entries, and comments will be hidden from public view immediately. After a 14-day grace period, all data is permanently removed.</p>
                    <button type="button" class="btn btn-danger" id="deleteAccountBtn">
                        <i class="fa-solid fa-trash"></i>
                        <span>Delete My Account</span>
                    </button>
                </section>
            </main>
        </div>
    </div>

    <script src="/assets/dist/profile.bundle.js" defer></script>

    <footer class="site-footer">
        <div class="site-footer-links">
            <a href="/data-privacy/">Data Privacy</a>
            <a href="/terms-and-conditions/">Terms &amp; Conditions</a>
        </div>
    </footer>
</body>
</html>

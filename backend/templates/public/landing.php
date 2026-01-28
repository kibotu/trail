<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="Cache-Control" content="no-cache, no-store, must-revalidate">
    <meta http-equiv="Pragma" content="no-cache">
    <meta http-equiv="Expires" content="0">
    <title>Trail - Public Entries</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        :root {
            --bg-primary: #0f172a;
            --bg-secondary: #1e293b;
            --bg-tertiary: #334155;
            --text-primary: #f8fafc;
            --text-secondary: #cbd5e1;
            --text-muted: #94a3b8;
            --accent: #3b82f6;
            --accent-hover: #2563eb;
            --border: rgba(255, 255, 255, 0.1);
        }

        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;
            background: var(--bg-primary);
            color: var(--text-primary);
            line-height: 1.6;
            min-height: 100vh;
            overflow-x: hidden;
            position: relative;
        }

        .orb {
            position: fixed;
            border-radius: 50%;
            filter: blur(80px);
            opacity: 0.4;
            z-index: -1;
        }

        .orb-1 {
            width: 500px;
            height: 500px;
            background: rgba(59, 130, 246, 0.4);
            top: -100px;
            left: -100px;
            animation: float-1 25s infinite ease-in-out;
        }

        .orb-2 {
            width: 400px;
            height: 400px;
            background: rgba(236, 72, 153, 0.3);
            bottom: -100px;
            right: -100px;
            animation: float-2 30s infinite ease-in-out;
        }

        @keyframes float-1 {
            0%, 100% { 
                transform: translate(0, 0) scale(1) rotate(0deg); 
            }
            25% { 
                transform: translate(120px, -80px) scale(1.15) rotate(5deg); 
            }
            50% { 
                transform: translate(200px, 50px) scale(1.25) rotate(-3deg); 
            }
            75% { 
                transform: translate(80px, -120px) scale(0.9) rotate(8deg); 
            }
        }

        @keyframes float-2 {
            0%, 100% { 
                transform: translate(0, 0) scale(1) rotate(0deg); 
            }
            25% { 
                transform: translate(-100px, 120px) scale(0.85) rotate(-6deg); 
            }
            50% { 
                transform: translate(-180px, -60px) scale(0.75) rotate(4deg); 
            }
            75% { 
                transform: translate(-60px, 140px) scale(1.1) rotate(-7deg); 
            }
        }

        header {
            background: var(--bg-secondary);
            border-bottom: 1px solid var(--border);
            position: sticky;
            top: 0;
            z-index: 100;
            backdrop-filter: blur(10px);
        }

        .header-banner {
            width: 100%;
            height: 200px;
            background: linear-gradient(135deg, rgba(59, 130, 246, 0.4) 0%, rgba(236, 72, 153, 0.3) 50%, rgba(168, 85, 247, 0.3) 100%);
            position: relative;
        }

        .header-content {
            max-width: 800px;
            margin: 0 auto;
            position: relative;
            padding: 0 2rem;
        }

        .header-profile-section {
            position: relative;
            margin-top: -64px;
            display: flex;
            justify-content: space-between;
            align-items: flex-end;
            padding-bottom: 1rem;
        }

        .header-left {
            display: flex;
            align-items: flex-end;
            gap: 1rem;
        }

        .header-avatar {
            width: 128px;
            height: 128px;
            border-radius: 50%;
            border: 4px solid var(--bg-secondary);
            object-fit: cover;
            background: var(--bg-secondary);
        }

        .header-info {
            padding-bottom: 0.5rem;
        }

        .logo {
            font-size: 2rem;
            margin-right: 0.5rem;
        }

        h1 {
            font-size: 1.5rem;
            font-weight: 700;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .subtitle {
            color: var(--text-secondary);
            font-size: 0.875rem;
            margin-top: 0.25rem;
        }

        .login-button {
            background: var(--accent);
            color: white;
            border: none;
            padding: 0.625rem 1.25rem;
            border-radius: 8px;
            font-size: 0.9375rem;
            font-weight: 500;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            transition: all 0.2s;
            cursor: pointer;
        }

        .login-button:hover {
            background: var(--accent-hover);
            transform: translateY(-1px);
            box-shadow: 0 4px 12px rgba(59, 130, 246, 0.4);
        }

        .google-icon {
            width: 20px;
            height: 20px;
            flex-shrink: 0;
        }

        .header-actions {
            display: flex;
            align-items: center;
            gap: 1rem;
            padding-bottom: 0.5rem;
        }

        .nav-link {
            color: var(--text-secondary);
            text-decoration: none;
            font-size: 0.9375rem;
            font-weight: 500;
            transition: color 0.2s;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
        }

        .nav-link:hover {
            color: var(--accent);
        }

        .logout-button {
            background: var(--bg-tertiary);
            color: var(--text-primary);
            border: none;
            padding: 0.5rem 1rem;
            border-radius: 6px;
            font-size: 0.875rem;
            font-weight: 500;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            transition: all 0.2s;
            cursor: pointer;
        }

        .logout-button:hover {
            background: var(--bg-secondary);
            transform: translateY(-1px);
        }

        main {
            max-width: 800px;
            margin: 0 auto;
            padding: 2rem 1rem;
        }

        .create-post-section {
            background: var(--bg-secondary);
            border: 1px solid var(--border);
            border-radius: 12px;
            padding: 1.5rem;
            margin-bottom: 2rem;
        }

        .create-post-header {
            display: flex;
            align-items: center;
            gap: 1rem;
            margin-bottom: 1rem;
        }

        .create-post-header h2 {
            font-size: 1.25rem;
            font-weight: 600;
            color: var(--text-primary);
            margin: 0;
        }

        .post-form {
            display: flex;
            flex-direction: column;
            gap: 1rem;
        }

        .post-textarea {
            width: 100%;
            min-height: 100px;
            background: var(--bg-primary);
            color: var(--text-primary);
            border: 1px solid var(--border);
            border-radius: 8px;
            padding: 0.75rem;
            font-size: 1rem;
            font-family: inherit;
            line-height: 1.6;
            resize: vertical;
            transition: border-color 0.2s;
        }

        .post-textarea:focus {
            outline: none;
            border-color: var(--accent);
        }

        .post-textarea::placeholder {
            color: var(--text-muted);
        }

        .post-form-footer {
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .char-counter {
            color: var(--text-muted);
            font-size: 0.875rem;
        }

        .char-counter.warning {
            color: #f59e0b;
        }

        .char-counter.error {
            color: #ef4444;
        }

        .submit-button {
            background: var(--accent);
            color: white;
            border: none;
            padding: 0.625rem 1.5rem;
            border-radius: 8px;
            font-size: 0.9375rem;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.2s;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
        }

        .submit-button:hover:not(:disabled) {
            background: var(--accent-hover);
            transform: translateY(-1px);
            box-shadow: 0 4px 12px rgba(59, 130, 246, 0.4);
        }

        .submit-button:disabled {
            background: var(--bg-tertiary);
            color: var(--text-muted);
            cursor: not-allowed;
            transform: none;
        }

        .post-error {
            background: rgba(239, 68, 68, 0.1);
            border: 1px solid rgba(239, 68, 68, 0.3);
            color: #fca5a5;
            padding: 0.75rem;
            border-radius: 8px;
            font-size: 0.875rem;
            margin-top: 0.5rem;
        }

        .post-success {
            background: rgba(34, 197, 94, 0.1);
            border: 1px solid rgba(34, 197, 94, 0.3);
            color: #86efac;
            padding: 0.75rem;
            border-radius: 8px;
            font-size: 0.875rem;
            margin-top: 0.5rem;
        }

        .entries-container {
            display: flex;
            flex-direction: column;
            gap: 1rem;
        }

        .entry-card {
            background: var(--bg-secondary);
            border: 1px solid var(--border);
            border-radius: 12px;
            padding: 1rem 1.5rem;
            transition: background 0.2s;
            cursor: pointer;
        }

        .entry-card:hover {
            background: rgba(30, 41, 59, 0.8);
        }

        .entry-header {
            display: flex;
            gap: 0.75rem;
            margin-bottom: 0.5rem;
        }

        .avatar {
            width: 48px;
            height: 48px;
            border-radius: 50%;
            object-fit: cover;
            flex-shrink: 0;
        }

        .entry-header-content {
            flex: 1;
            min-width: 0;
        }

        .entry-header-top {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 0.5rem;
        }

        .user-info {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            min-width: 0;
        }

        .user-name {
            font-weight: 600;
            color: var(--text-primary);
            font-size: 0.9375rem;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .user-name-link {
            font-weight: 600;
            color: var(--text-primary);
            font-size: 0.9375rem;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
            text-decoration: none;
            transition: color 0.2s;
        }

        .user-name-link:hover {
            color: var(--accent);
            text-decoration: underline;
        }

        .entry-menu {
            position: relative;
        }

        .menu-button {
            background: transparent;
            border: none;
            color: var(--text-muted);
            cursor: pointer;
            padding: 0.25rem;
            border-radius: 50%;
            width: 32px;
            height: 32px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.25rem;
            transition: all 0.2s;
            flex-shrink: 0;
        }

        .menu-button:hover {
            background: var(--bg-tertiary);
            color: var(--accent);
        }

        .menu-dropdown {
            position: absolute;
            top: 100%;
            right: 0;
            background: var(--bg-tertiary);
            border: 1px solid var(--border);
            border-radius: 8px;
            box-shadow: 0 8px 24px rgba(0, 0, 0, 0.4);
            min-width: 160px;
            z-index: 1000;
            display: none;
            overflow: hidden;
            margin-top: 0.25rem;
        }

        .menu-dropdown.active {
            display: block;
        }

        .menu-item {
            background: transparent;
            border: none;
            color: var(--text-primary);
            padding: 0.75rem 1rem;
            width: 100%;
            text-align: left;
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 0.75rem;
            font-size: 0.9375rem;
            font-weight: 500;
            transition: background 0.2s;
        }

        .menu-item:hover {
            background: var(--bg-secondary);
        }

        .menu-item.delete {
            color: #ef4444;
        }

        .menu-item.delete:hover {
            background: rgba(239, 68, 68, 0.1);
        }

        .entry-body {
            margin-left: 56px;
        }

        .entry-text {
            color: var(--text-primary);
            font-size: 1rem;
            line-height: 1.6;
            margin-bottom: 1rem;
            word-wrap: break-word;
            white-space: pre-wrap;
        }

        .entry-text a {
            color: var(--accent);
            text-decoration: none;
        }

        .entry-text a:hover {
            text-decoration: underline;
        }

        .link-preview-card {
            background: var(--bg-tertiary);
            border: 1px solid var(--border);
            border-radius: 8px;
            overflow: hidden;
            margin-top: 0.75rem;
            transition: transform 0.2s, box-shadow 0.2s;
            text-decoration: none;
            display: block;
            color: inherit;
            cursor: pointer;
        }

        .link-preview-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(59, 130, 246, 0.3);
        }

        .link-preview-image {
            width: 100%;
            height: 180px;
            object-fit: cover;
            display: block;
        }

        .link-preview-content {
            padding: 0.75rem;
        }

        .link-preview-title {
            font-weight: 600;
            font-size: 0.9375rem;
            color: var(--text-primary);
            margin-bottom: 0.375rem;
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
            overflow: hidden;
            line-height: 1.4;
        }

        .link-preview-description {
            font-size: 0.875rem;
            color: var(--text-secondary);
            margin-bottom: 0.5rem;
            display: -webkit-box;
            -webkit-line-clamp: 3;
            -webkit-box-orient: vertical;
            overflow: hidden;
            line-height: 1.5;
        }

        .link-preview-url {
            display: flex;
            align-items: center;
            gap: 0.375rem;
            font-size: 0.8125rem;
            color: var(--accent);
        }

        /* Preview source badge styling (admin only, but included for consistency) */
        .link-preview-wrapper {
            position: relative;
            margin-top: 0.75rem;
        }

        .preview-source-badge {
            position: absolute;
            top: 0.5rem;
            right: 0.5rem;
            z-index: 10;
            display: inline-flex;
            align-items: center;
            gap: 0.25rem;
            padding: 0.25rem 0.5rem;
            border-radius: 4px;
            font-size: 0.75rem;
            font-weight: 600;
            color: white;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.2);
            backdrop-filter: blur(4px);
        }

        .preview-source-badge span:first-child {
            font-size: 0.875rem;
        }

        .entry-footer {
            display: flex;
            align-items: center;
            gap: 1.5rem;
            color: var(--text-muted);
            font-size: 0.8125rem;
            padding-top: 0.75rem;
        }

        .timestamp {
            display: flex;
            align-items: center;
            gap: 0.375rem;
        }

        .entry-stats {
            display: flex;
            align-items: center;
            gap: 1.5rem;
        }

        .stat-button {
            background: transparent;
            border: none;
            color: var(--text-muted);
            cursor: pointer;
            padding: 0.25rem;
            display: flex;
            align-items: center;
            gap: 0.375rem;
            font-size: 0.8125rem;
            transition: color 0.2s;
        }

        .stat-button:hover {
            color: var(--accent);
        }

        .action-button {
            background: var(--bg-tertiary);
            color: var(--text-secondary);
            border: none;
            padding: 0.5rem 1rem;
            border-radius: 6px;
            font-size: 0.875rem;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.2s;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
        }

        .action-button:hover {
            background: var(--bg-secondary);
            color: var(--text-primary);
        }

        .edit-form {
            margin-top: 1rem;
            padding-top: 1rem;
            border-top: 1px solid var(--border);
        }

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
            margin-bottom: 0.75rem;
        }

        .edit-textarea:focus {
            outline: none;
            border-color: var(--accent);
        }

        .edit-actions {
            display: flex;
            gap: 0.5rem;
            justify-content: flex-end;
        }

        .save-button {
            background: var(--accent);
            color: white;
        }

        .save-button:hover {
            background: var(--accent-hover);
        }

        .cancel-button {
            background: var(--bg-tertiary);
        }

        .loading {
            text-align: center;
            padding: 2rem;
            color: var(--text-secondary);
        }

        .loading-spinner {
            display: inline-block;
            width: 40px;
            height: 40px;
            border: 4px solid var(--border);
            border-top-color: var(--accent);
            border-radius: 50%;
            animation: spin 1s linear infinite;
        }

        @keyframes spin {
            to { transform: rotate(360deg); }
        }

        .end-message {
            text-align: center;
            padding: 2rem;
            color: var(--text-muted);
            font-size: 0.875rem;
        }

        .error-message {
            background: rgba(239, 68, 68, 0.1);
            border: 1px solid rgba(239, 68, 68, 0.3);
            color: #fca5a5;
            padding: 1rem;
            border-radius: 8px;
            text-align: center;
            margin: 1rem 0;
        }

        .empty-state {
            text-align: center;
            padding: 4rem 2rem;
            color: var(--text-secondary);
        }

        .empty-state-icon {
            font-size: 4rem;
            margin-bottom: 1rem;
        }

        @media (max-width: 768px) {
            .header-banner {
                height: 120px;
            }

            .header-content {
                padding: 0 1rem;
            }

            .header-profile-section {
                margin-top: -48px;
                flex-direction: column;
                align-items: flex-start;
                gap: 1rem;
            }

            .header-avatar {
                width: 96px;
                height: 96px;
                border-width: 3px;
            }

            .header-left {
                flex-direction: column;
                align-items: flex-start;
                gap: 0.5rem;
                width: 100%;
            }

            .header-info {
                padding-bottom: 0;
            }

            h1 {
                font-size: 1.25rem;
            }

            .header-actions {
                flex-wrap: wrap;
                width: 100%;
                justify-content: flex-end;
            }

            .login-button {
                padding: 0.5rem 1rem;
                font-size: 0.875rem;
            }

            .nav-link {
                font-size: 0.875rem;
            }

            main {
                padding: 1rem 0.5rem;
            }

            .create-post-section {
                padding: 1rem;
            }

            .create-post-header h2 {
                font-size: 1.125rem;
            }

            .post-textarea {
                min-height: 80px;
                font-size: 0.9375rem;
            }

            .submit-button {
                padding: 0.5rem 1rem;
                font-size: 0.875rem;
            }

            .entry-card {
                padding: 0.75rem 1rem;
            }

            .entry-header {
                gap: 0.5rem;
            }

            .avatar {
                width: 40px;
                height: 40px;
            }

            .user-name {
                font-size: 0.875rem;
            }

            .entry-body {
                margin-left: 48px;
            }

            .menu-button {
                width: 28px;
                height: 28px;
                font-size: 1.125rem;
            }
        }
    </style>
</head>
<body>
    <div class="orb orb-1"></div>
    <div class="orb orb-2"></div>

    <header>
        <div class="header-banner"></div>
        <div class="header-content">
            <div class="header-profile-section">
                <div class="header-left">
                    <?php if (isset($isLoggedIn) && $isLoggedIn && isset($userPhotoUrl) && $userPhotoUrl): ?>
                        <img src="<?= htmlspecialchars($userPhotoUrl) ?>" alt="User" class="header-avatar">
                    <?php else: ?>
                        <div class="header-avatar" style="display: flex; align-items: center; justify-content: center; font-size: 4rem; background: var(--bg-tertiary);">🔗</div>
                    <?php endif; ?>
                    <div class="header-info">
                        <h1>
                            <span class="logo">🔗</span>
                            Trail
                        </h1>
                        <p class="subtitle">Public Entries from All Users</p>
                    </div>
                </div>
                
                <?php if (isset($isLoggedIn) && $isLoggedIn): ?>
                    <div class="header-actions">
                        <a href="/api" class="nav-link">
                            <span>📚</span>
                            <span>API</span>
                        </a>
                        <?php if (isset($isAdmin) && $isAdmin): ?>
                            <a href="/admin" class="nav-link">
                                <span>⚙️</span>
                                <span>Admin</span>
                            </a>
                        <?php endif; ?>
                        <a href="/profile" class="nav-link">
                            <span>👤</span>
                            <span>Profile</span>
                        </a>
                        <a href="/admin/logout.php" class="logout-button">
                            <span>🚪</span>
                            <span>Logout</span>
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
                        <span>🔐</span>
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
                <span style="font-size: 1.5rem;">✍️</span>
                <h2>Create a Post</h2>
            </div>
            <form class="post-form" id="createPostForm" onsubmit="return false;">
                <textarea 
                    id="postText" 
                    class="post-textarea" 
                    placeholder="Share a link, thought, or update... (max 280 characters)"
                    maxlength="280"
                    rows="3"
                ></textarea>
                <div class="post-form-footer">
                    <span class="char-counter" id="charCounter">0 / 280</span>
                    <button type="submit" class="submit-button" id="submitButton">
                        <span>📝</span>
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
            <p>✨ You've reached the end</p>
        </div>
    </main>

    <script src="/js/card-template.js"></script>
    <script>
        let nextCursor = null;
        let isLoading = false;
        let hasMore = true;

        const entriesContainer = document.getElementById('entriesContainer');
        const loadingElement = document.getElementById('loading');
        const endMessage = document.getElementById('endMessage');

        // User session info (from PHP)
        const isLoggedIn = <?= json_encode($isLoggedIn ?? false) ?>;
        const userEmail = <?= json_encode($userName ?? null) ?>;
        const isAdmin = <?= json_encode($isAdmin ?? false) ?>;
        const jwtToken = <?= json_encode($jwtToken ?? null) ?>;

        // Character counter for post form
        if (isLoggedIn) {
            const postText = document.getElementById('postText');
            const charCounter = document.getElementById('charCounter');
            const submitButton = document.getElementById('submitButton');
            const createPostForm = document.getElementById('createPostForm');
            const postMessage = document.getElementById('postMessage');

            // Update character counter
            postText.addEventListener('input', function() {
                const length = this.value.length;
                charCounter.textContent = `${length} / 280`;
                
                // Update counter color
                charCounter.classList.remove('warning', 'error');
                if (length > 260) {
                    charCounter.classList.add('error');
                } else if (length > 240) {
                    charCounter.classList.add('warning');
                }
                
                // Disable submit if empty or too long
                submitButton.disabled = length === 0 || length > 280;
            });

            // Handle form submission
            createPostForm.addEventListener('submit', async function(e) {
                e.preventDefault();
                
                const text = postText.value.trim();
                if (!text || text.length > 280) {
                    return;
                }

                if (!jwtToken) {
                    showMessage('You must be logged in to post', 'error');
                    return;
                }

                // Disable form during submission
                submitButton.disabled = true;
                postText.disabled = true;
                submitButton.innerHTML = '<span>⏳</span><span>Posting...</span>';

                try {
                    const response = await fetch('/api/entries', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'Authorization': `Bearer ${jwtToken}`
                        },
                        body: JSON.stringify({ text })
                    });

                    if (!response.ok) {
                        const error = await response.json();
                        throw new Error(error.error || 'Failed to create post');
                    }

                    const data = await response.json();
                    
                    // Clear form
                    postText.value = '';
                    charCounter.textContent = '0 / 280';
                    
                    // Show success message
                    showMessage('✓ Post created successfully!', 'success');
                    
                    // Reload entries to show new post
                    setTimeout(() => {
                        location.reload();
                    }, 1000);

                } catch (error) {
                    console.error('Error creating post:', error);
                    showMessage(`Failed to create post: ${error.message}`, 'error');
                } finally {
                    submitButton.disabled = false;
                    postText.disabled = false;
                    submitButton.innerHTML = '<span>📝</span><span>Post</span>';
                }
            });

            function showMessage(message, type) {
                postMessage.textContent = message;
                postMessage.className = type === 'success' ? 'post-success' : 'post-error';
                postMessage.style.display = 'block';
                
                setTimeout(() => {
                    postMessage.style.display = 'none';
                }, 5000);
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
                            isAdmin: false                       // Not admin context
                        });
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
                            <div class="empty-state-icon">📝</div>
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

        // Get JWT token from session
        function getAuthToken() {
            return jwtToken;
        }

        // Edit entry
        async function editEntry(entryId) {
            // Close the menu
            const menu = document.getElementById(`menu-${entryId}`);
            if (menu) menu.classList.remove('active');

            const card = document.querySelector(`[data-entry-id="${entryId}"]`);
            if (!card) return;

            const contentDiv = card.querySelector('.entry-content');
            const textDiv = card.querySelector('.entry-text');
            const currentText = textDiv.textContent;

            // Store the preview card HTML to restore it later
            const previewCard = contentDiv.querySelector('.iframely-embed, .link-preview-card');
            const previewHtml = previewCard ? previewCard.outerHTML : '';

            // Create edit form
            contentDiv.innerHTML = `
                <div class="edit-form">
                    <textarea class="edit-textarea" id="edit-text-${entryId}" maxlength="280">${escapeHtml(currentText)}</textarea>
                    <div class="edit-actions">
                        <button class="action-button cancel-button" onclick="cancelEdit(${entryId}, '${escapeHtml(currentText).replace(/'/g, "\\'")}', \`${previewHtml.replace(/`/g, '\\`')}\`)">
                            <span>❌</span>
                            <span>Cancel</span>
                        </button>
                        <button class="action-button save-button" onclick="saveEdit(${entryId})">
                            <span>💾</span>
                            <span>Save</span>
                        </button>
                    </div>
                </div>
            `;

            // Focus textarea
            const textarea = document.getElementById(`edit-text-${entryId}`);
            textarea.focus();
            textarea.setSelectionRange(textarea.value.length, textarea.value.length);
        }

        // Cancel edit
        function cancelEdit(entryId, originalText, previewHtml = '') {
            const card = document.querySelector(`[data-entry-id="${entryId}"]`);
            if (!card) return;

            const contentDiv = card.querySelector('.entry-content');
            const linkedText = linkifyText(originalText);
            contentDiv.innerHTML = `<div class="entry-text">${linkedText}</div>${previewHtml}`;
        }

        // Save edit
        async function saveEdit(entryId) {
            const textarea = document.getElementById(`edit-text-${entryId}`);
            const newText = textarea.value.trim();

            if (!newText) {
                alert('Entry text cannot be empty');
                return;
            }

            const token = getAuthToken();
            if (!token) {
                alert('You must be logged in to edit entries');
                window.location.href = '/admin/login.php';
                return;
            }

            try {
                const response = await fetch(`/api/entries/${entryId}`, {
                    method: 'PUT',
                    headers: {
                        'Content-Type': 'application/json',
                        'Authorization': `Bearer ${token}`
                    },
                    body: JSON.stringify({ text: newText })
                });

                if (!response.ok) {
                    const error = await response.json();
                    throw new Error(error.error || 'Failed to update entry');
                }

                const data = await response.json();

                // Update the card with new text
                const card = document.querySelector(`[data-entry-id="${entryId}"]`);
                const contentDiv = card.querySelector('.entry-content');
                const escapedText = escapeHtml(newText);
                const linkedText = linkifyText(escapedText);
                contentDiv.innerHTML = `<div class="entry-text">${linkedText}</div>`;

                // Update the footer to show edited timestamp
                const footerDiv = card.querySelector('.entry-footer');
                footerDiv.innerHTML = `
                    <div class="timestamp">
                        <span>✏️</span>
                        <span>edited ${formatTimestamp(data.updated_at)}</span>
                    </div>
                `;

            } catch (error) {
                console.error('Error updating entry:', error);
                alert(`Failed to update entry: ${error.message}`);
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

            const token = getAuthToken();
            if (!token) {
                alert('You must be logged in to delete entries');
                window.location.href = '/admin/login.php';
                return;
            }

            try {
                const response = await fetch(`/api/entries/${entryId}`, {
                    method: 'DELETE',
                    headers: {
                        'Authorization': `Bearer ${token}`
                    }
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
</body>
</html>

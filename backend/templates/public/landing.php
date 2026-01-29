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
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;
            background: var(--bg-primary);
            color: var(--text-primary);
            line-height: 1.6;
            min-height: 100vh;
            overflow-x: hidden;
            position: relative;
        }

        /* IBM Plex Sans for all headings and prominent text */
        h1, h2, h3, h4, h5, h6,
        .logo,
        .user-name,
        .user-name-link,
        .link-preview-title {
            font-family: 'IBM Plex Sans', 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;
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
            position: relative;
            overflow: hidden;
            background: #000;
        }

        #shader-canvas {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            display: block;
            background: #000;
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

        .entry-images {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 0.5rem;
            margin-top: 0.75rem;
            margin-bottom: 0.75rem;
        }

        .entry-image-wrapper {
            position: relative;
            overflow: hidden;
            border-radius: 8px;
            background: var(--bg-tertiary);
        }

        .entry-image {
            width: 100%;
            height: auto;
            max-height: 400px;
            object-fit: cover;
            display: block;
            border-radius: 8px;
            cursor: pointer;
            transition: transform 0.2s;
        }

        .entry-image:hover {
            transform: scale(1.02);
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
        <div class="header-banner">
            <canvas id="shader-canvas"></canvas>
        </div>
        <div class="header-content">
            <div class="header-profile-section">
                <div class="header-left">
                    <?php if (isset($isLoggedIn) && $isLoggedIn && isset($userPhotoUrl) && $userPhotoUrl): ?>
                        <img src="<?= htmlspecialchars($userPhotoUrl) ?>" alt="User" class="header-avatar">
                    <?php else: ?>
                        <img src="/images/app-icon.webp" alt="Trail" class="header-avatar">
                    <?php endif; ?>
                    <div class="header-info">
                        <h1>
                            Trail
                        </h1>
                        <p class="subtitle">Public Entries from All Users</p>
                    </div>
                </div>
                
                <?php if (isset($isLoggedIn) && $isLoggedIn): ?>
                    <div class="header-actions">
                        <a href="/api" class="nav-link">
                            <i class="fa-solid fa-book"></i>
                            <span>API</span>
                        </a>
                        <?php if (isset($isAdmin) && $isAdmin): ?>
                            <a href="/admin" class="nav-link">
                                <i class="fa-solid fa-gear"></i>
                                <span>Admin</span>
                            </a>
                        <?php endif; ?>
                        <a href="/profile" class="nav-link">
                            <i class="fa-solid fa-user"></i>
                            <span>Profile</span>
                        </a>
                        <a href="/admin/logout.php" class="logout-button">
                            <i class="fa-solid fa-right-from-bracket"></i>
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

                if (!jwtToken) {
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
                            'Content-Type': 'application/json',
                            'Authorization': `Bearer ${jwtToken}`
                        },
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
                    
                    // Show success message
                    showMessage('<i class="fa-solid fa-check"></i> Post created successfully!', 'success');
                    
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
                    submitButton.innerHTML = '<i class="fa-solid fa-paper-plane"></i><span>Post</span>';
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
                            <i class="fa-solid fa-xmark"></i>
                            <span>Cancel</span>
                        </button>
                        <button class="action-button save-button" onclick="saveEdit(${entryId})">
                            <i class="fa-solid fa-floppy-disk"></i>
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
                        <i class="fa-solid fa-pen"></i>
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
    
    <!-- Image Upload Script -->
    <script src="/js/image-upload.js"></script>
    <script>
        // Initialize post image uploader
        window.postImageIds = [];
        
        if (isLoggedIn) {
            window.addEventListener('DOMContentLoaded', () => {
                const postImageUploader = createImageUploadUI(
                    'post',
                    'post-image-upload',
                    (result) => {
                        console.log('Post image uploaded:', result);
                        // Store image ID for submission
                        if (!window.postImageIds) {
                            window.postImageIds = [];
                        }
                        window.postImageIds.push(result.image_id);
                        
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

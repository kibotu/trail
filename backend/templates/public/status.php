<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="Cache-Control" content="no-cache, no-store, must-revalidate">
    <meta http-equiv="Pragma" content="no-cache">
    <meta http-equiv="Expires" content="0">
    
    <!-- Dynamic Open Graph meta tags will be set by JavaScript -->
    <meta property="og:type" content="article">
    <meta property="og:site_name" content="Trail">
    <meta name="twitter:card" content="summary">
    
    <title>Entry - Trail</title>
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

        .header-content {
            max-width: 800px;
            margin: 0 auto;
            padding: 1rem 2rem;
            display: flex;
            align-items: center;
            gap: 1rem;
        }

        .back-button {
            background: transparent;
            border: none;
            color: var(--text-primary);
            font-size: 1.5rem;
            cursor: pointer;
            padding: 0.5rem;
            border-radius: 50%;
            transition: background 0.2s;
            display: flex;
            align-items: center;
            justify-content: center;
            width: 40px;
            height: 40px;
        }

        .back-button:hover {
            background: var(--bg-tertiary);
        }

        .logo {
            font-size: 1.5rem;
            font-weight: 700;
        }

        main {
            max-width: 800px;
            margin: 2rem auto;
            padding: 0 2rem;
        }

        .entry-container {
            margin-bottom: 2rem;
        }

        .entry-card {
            background: var(--bg-secondary);
            border: 1px solid var(--border);
            border-radius: 12px;
            padding: 1.5rem;
            margin-bottom: 1rem;
            transition: all 0.2s;
        }

        .entry-header {
            display: flex;
            gap: 12px;
            margin-bottom: 12px;
        }

        .avatar {
            width: 48px;
            height: 48px;
            border-radius: 50%;
            object-fit: cover;
        }

        .entry-header-content {
            flex: 1;
            min-width: 0;
        }

        .entry-header-top {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
        }

        .user-info {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            flex-wrap: wrap;
        }

        .user-name,
        .user-name-link {
            font-weight: 600;
            font-size: 1rem;
            color: var(--text-primary);
            text-decoration: none;
        }

        .user-name-link:hover {
            text-decoration: underline;
        }

        .timestamp {
            color: var(--text-muted);
            font-size: 0.875rem;
        }

        .entry-menu {
            position: relative;
        }

        .menu-button {
            background: transparent;
            border: none;
            color: var(--text-muted);
            font-size: 1.25rem;
            cursor: pointer;
            padding: 0.25rem 0.5rem;
            border-radius: 4px;
            transition: all 0.2s;
        }

        .menu-button:hover {
            background: var(--bg-tertiary);
            color: var(--text-primary);
        }

        .menu-dropdown {
            display: none;
            position: absolute;
            right: 0;
            top: 100%;
            background: var(--bg-tertiary);
            border: 1px solid var(--border);
            border-radius: 8px;
            min-width: 150px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.3);
            z-index: 10;
        }

        .menu-dropdown.active {
            display: block;
        }

        .menu-item {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            width: 100%;
            padding: 0.75rem 1rem;
            background: transparent;
            border: none;
            color: var(--text-primary);
            font-size: 0.9375rem;
            cursor: pointer;
            text-align: left;
            transition: background 0.2s;
        }

        .menu-item:first-child {
            border-radius: 8px 8px 0 0;
        }

        .menu-item:last-child {
            border-radius: 0 0 8px 8px;
        }

        .menu-item:hover {
            background: var(--bg-secondary);
        }

        .menu-item.delete {
            color: #ef4444;
        }

        .menu-item i {
            width: 16px;
            text-align: center;
        }

        .entry-body {
            margin-top: 0.5rem;
        }

        .entry-text {
            font-size: 1rem;
            line-height: 1.5;
            white-space: pre-wrap;
            word-wrap: break-word;
            overflow-wrap: break-word;
        }

        .entry-text a {
            color: var(--accent);
            text-decoration: none;
        }

        .entry-text a:hover {
            text-decoration: underline;
        }

        .entry-images {
            margin-top: 1rem;
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 0.5rem;
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
            display: block;
            border-radius: 8px;
        }

        .link-preview-wrapper {
            position: relative;
            margin-top: 1rem;
        }

        .preview-source-badge {
            position: absolute;
            top: -8px;
            right: 8px;
            display: inline-flex;
            align-items: center;
            gap: 4px;
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 0.75rem;
            font-weight: 600;
            color: white;
            z-index: 1;
        }

        .link-preview-card {
            display: flex;
            border: 1px solid var(--border);
            border-radius: 8px;
            overflow: hidden;
            text-decoration: none;
            color: inherit;
            transition: all 0.2s;
            background: var(--bg-tertiary);
            margin-top: 1rem;
        }

        .link-preview-card:hover {
            border-color: var(--accent);
            background: rgba(59, 130, 246, 0.05);
        }

        .link-preview-image {
            width: 120px;
            height: 120px;
            object-fit: cover;
            flex-shrink: 0;
        }

        .link-preview-content {
            padding: 12px;
            flex: 1;
            min-width: 0;
        }

        .link-preview-title {
            font-weight: 600;
            font-size: 0.9375rem;
            margin-bottom: 4px;
            overflow: hidden;
            text-overflow: ellipsis;
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
        }

        .link-preview-description {
            font-size: 0.875rem;
            color: var(--text-secondary);
            overflow: hidden;
            text-overflow: ellipsis;
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
            margin-bottom: 8px;
        }

        .link-preview-url {
            display: flex;
            align-items: center;
            gap: 4px;
            font-size: 0.8125rem;
            color: var(--text-muted);
        }

        .entry-footer {
            margin-top: 0.75rem;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 1rem;
        }

        .share-button {
            background: transparent;
            border: none;
            color: var(--text-muted);
            font-size: 0.875rem;
            cursor: pointer;
            padding: 0.5rem;
            border-radius: 4px;
            transition: all 0.2s;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .share-button:hover {
            background: var(--bg-tertiary);
            color: var(--accent);
        }

        .error-message {
            background: rgba(239, 68, 68, 0.1);
            border: 1px solid rgba(239, 68, 68, 0.3);
            color: #ef4444;
            padding: 1rem;
            border-radius: 8px;
            text-align: center;
        }

        .loading {
            text-align: center;
            padding: 2rem;
            color: var(--text-muted);
        }

        @media (max-width: 768px) {
            .header-content {
                padding: 1rem;
            }

            main {
                margin: 1rem auto;
                padding: 0 1rem;
            }

            .entry-card {
                padding: 1rem;
            }

            .link-preview-card {
                flex-direction: column;
            }

            .link-preview-image {
                width: 100%;
                height: 200px;
            }
        }
    </style>
</head>
<body>
    <div class="orb orb-1"></div>
    <div class="orb orb-2"></div>

    <header>
        <div class="header-content">
            <button class="back-button" onclick="window.history.back()" aria-label="Go back">
                <i class="fa-solid fa-arrow-left"></i>
            </button>
            <span class="logo">Trail</span>
        </div>
    </header>

    <main>
        <div id="entry-container" class="entry-container">
            <div class="loading">
                <i class="fa-solid fa-spinner fa-spin"></i>
                <p>Loading entry...</p>
            </div>
        </div>
    </main>

    <script src="/js/card-template.js"></script>
    <script>
        const hashId = <?php echo json_encode($hashId ?? ''); ?>;
        const isLoggedIn = <?php echo json_encode($isLoggedIn ?? false); ?>;
        // JWT token is stored in httpOnly cookie - not accessible to JavaScript for security

        async function loadEntry() {
            const container = document.getElementById('entry-container');
            
            try {
                const response = await fetch(`/api/entries/${hashId}`);
                
                if (!response.ok) {
                    if (response.status === 404) {
                        container.innerHTML = '<div class="error-message">Entry not found</div>';
                    } else {
                        container.innerHTML = '<div class="error-message">Failed to load entry</div>';
                    }
                    return;
                }
                
                const entry = await response.json();
                
                // Update page title and meta tags
                const displayName = entry.user_nickname || entry.user_name;
                const entryText = entry.text.substring(0, 100) + (entry.text.length > 100 ? '...' : '');
                
                document.title = `${displayName} on Trail: "${entryText}"`;
                
                // Update Open Graph meta tags
                updateMetaTag('og:title', `${displayName} on Trail`);
                updateMetaTag('og:description', entryText);
                updateMetaTag('og:url', window.location.href);
                
                if (entry.preview_image) {
                    updateMetaTag('og:image', entry.preview_image);
                }
                
                // Check if current user can modify this entry
                let canModify = false;
                if (isLoggedIn) {
                    try {
                        const profileResponse = await fetch('/api/profile', {
                            credentials: 'same-origin' // Include httpOnly cookie with JWT
                        });
                        
                        if (profileResponse.ok) {
                            const profile = await profileResponse.json();
                            canModify = profile.id === entry.user_id || profile.is_admin === true;
                        }
                    } catch (e) {
                        console.error('Failed to check permissions:', e);
                    }
                }
                
                // Render the entry card (without permalink on status page)
                container.innerHTML = '';
                const card = createEntryCard(entry, {
                    canModify: canModify,
                    enablePermalink: false
                });
                container.appendChild(card);
                
            } catch (error) {
                console.error('Error loading entry:', error);
                container.innerHTML = '<div class="error-message">Failed to load entry</div>';
            }
        }

        function updateMetaTag(property, content) {
            let meta = document.querySelector(`meta[property="${property}"]`);
            if (!meta) {
                meta = document.createElement('meta');
                meta.setAttribute('property', property);
                document.head.appendChild(meta);
            }
            meta.setAttribute('content', content);
        }

        // Load the entry on page load
        loadEntry();
    </script>
</body>
</html>

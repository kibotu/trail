<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="Cache-Control" content="no-cache, no-store, must-revalidate">
    <meta http-equiv="Pragma" content="no-cache">
    <meta http-equiv="Expires" content="0">
    <title>@<?= htmlspecialchars($nickname ?? 'user') ?> - Trail</title>
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
            0%, 100% { transform: translate(0, 0) scale(1) rotate(0deg); }
            25% { transform: translate(120px, -80px) scale(1.15) rotate(5deg); }
            50% { transform: translate(200px, 50px) scale(1.25) rotate(-3deg); }
            75% { transform: translate(80px, -120px) scale(0.9) rotate(8deg); }
        }

        @keyframes float-2 {
            0%, 100% { transform: translate(0, 0) scale(1) rotate(0deg); }
            25% { transform: translate(-100px, 120px) scale(0.85) rotate(-6deg); }
            50% { transform: translate(-180px, -60px) scale(0.75) rotate(4deg); }
            75% { transform: translate(-60px, 140px) scale(1.1) rotate(-7deg); }
        }

        header {
            background: var(--bg-secondary);
            border-bottom: 1px solid var(--border);
            position: sticky;
            top: 0;
            z-index: 100;
        }

        .header-content {
            max-width: 800px;
            margin: 0 auto;
            padding: 1.5rem 2rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .logo {
            font-size: 1.75rem;
            font-weight: 700;
            text-decoration: none;
            color: var(--text-primary);
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .header-actions {
            display: flex;
            gap: 1rem;
            align-items: center;
        }

        .nav-link, .logout-button, .login-button {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.5rem 1rem;
            background: var(--bg-tertiary);
            border: 1px solid var(--border);
            border-radius: 0.5rem;
            color: var(--text-primary);
            text-decoration: none;
            font-size: 0.875rem;
            font-weight: 500;
            transition: all 0.2s;
            cursor: pointer;
        }

        .nav-link:hover, .logout-button:hover, .login-button:hover {
            background: var(--bg-primary);
            border-color: var(--accent);
        }

        .login-button {
            background: var(--accent);
            border-color: var(--accent);
        }

        .login-button:hover {
            background: var(--accent-hover);
            border-color: var(--accent-hover);
        }

        .google-icon {
            flex-shrink: 0;
        }

        main {
            max-width: 800px;
            margin: 0 auto;
            padding: 2rem;
        }

        .user-header {
            background: var(--bg-secondary);
            border: 1px solid var(--border);
            border-radius: 1rem;
            padding: 2rem;
            margin-bottom: 2rem;
            display: flex;
            align-items: center;
            gap: 1.5rem;
        }

        .user-avatar {
            width: 80px;
            height: 80px;
            border-radius: 50%;
            border: 3px solid var(--accent);
        }

        .user-info h1 {
            font-size: 1.75rem;
            margin-bottom: 0.25rem;
        }

        .user-info .nickname {
            color: var(--text-muted);
            font-size: 1rem;
        }

        .entries-container {
            display: flex;
            flex-direction: column;
            gap: 1.5rem;
        }

        .loading {
            text-align: center;
            padding: 3rem 1rem;
            color: var(--text-muted);
        }

        .loading-spinner {
            width: 40px;
            height: 40px;
            border: 3px solid var(--border);
            border-top-color: var(--accent);
            border-radius: 50%;
            animation: spin 0.8s linear infinite;
            margin: 0 auto 1rem;
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

        .empty-state {
            text-align: center;
            padding: 4rem 2rem;
            color: var(--text-muted);
        }

        .empty-state-icon {
            font-size: 4rem;
            margin-bottom: 1rem;
        }

        .empty-state h2 {
            font-size: 1.5rem;
            margin-bottom: 0.5rem;
            color: var(--text-secondary);
        }

        .error-message {
            background: rgba(239, 68, 68, 0.1);
            border: 1px solid #ef4444;
            color: #ef4444;
            padding: 1rem;
            border-radius: 0.5rem;
            margin-bottom: 1rem;
            text-align: center;
        }

        /* Entry Card Styles */
        .entry-card {
            background: var(--bg-secondary);
            border: 1px solid var(--border);
            border-radius: 12px;
            padding: 1rem 1.5rem;
            transition: background 0.2s;
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

        .timestamp {
            color: var(--text-muted);
            font-size: 0.875rem;
            white-space: nowrap;
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

        .entry-footer {
            margin-top: 0.5rem;
            display: flex;
            align-items: center;
            gap: 1rem;
        }

        .entry-footer .timestamp {
            display: flex;
            align-items: center;
            gap: 0.25rem;
            font-style: italic;
        }

        /* Link Preview Card Styles */
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
            margin-bottom: 0.25rem;
            line-height: 1.4;
        }

        .link-preview-description {
            font-size: 0.875rem;
            color: var(--text-secondary);
            line-height: 1.5;
            margin-bottom: 0.5rem;
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
            overflow: hidden;
        }

        .link-preview-url {
            display: flex;
            align-items: center;
            gap: 0.25rem;
            font-size: 0.75rem;
            color: var(--text-muted);
        }

        /* Edit Form Styles */
        .edit-form {
            margin-top: 0.5rem;
        }

        .edit-textarea {
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
            margin-bottom: 0.75rem;
        }

        .edit-textarea:focus {
            outline: none;
            border-color: var(--accent);
        }

        .edit-actions {
            display: flex;
            gap: 0.75rem;
            justify-content: flex-end;
        }

        .action-button {
            padding: 0.5rem 1rem;
            border: none;
            border-radius: 6px;
            font-size: 0.875rem;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.2s;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
        }

        .cancel-button {
            background: var(--bg-tertiary);
            color: var(--text-primary);
        }

        .cancel-button:hover {
            background: var(--bg-primary);
        }

        .save-button {
            background: var(--accent);
            color: white;
        }

        .save-button:hover {
            background: var(--accent-hover);
        }

        @media (max-width: 768px) {
            main {
                padding: 1rem;
            }

            .user-header {
                flex-direction: column;
                text-align: center;
                padding: 1.5rem;
            }

            .header-content {
                padding: 1rem;
            }

            .header-actions {
                gap: 0.5rem;
            }

            .nav-link span:last-child,
            .logout-button span:last-child,
            .login-button span:last-child {
                display: none;
            }
        }
    </style>
</head>
<body>
    <div class="orb orb-1"></div>
    <div class="orb orb-2"></div>

    <header>
        <div class="header-content">
            <a href="/" class="logo">
                <span>🔗</span>
                <span>Trail</span>
            </a>
            <div class="header-actions">
                <?php if (isset($isLoggedIn) && $isLoggedIn): ?>
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
            <p>✨ You've reached the end</p>
        </div>
    </main>

    <script src="/js/card-template.js"></script>
    <script>
        const nickname = <?= json_encode($nickname ?? '') ?>;
        let nextCursor = null;
        let isLoading = false;
        let hasMore = true;
        let userData = null;

        const entriesContainer = document.getElementById('entriesContainer');
        const loadingElement = document.getElementById('loading');
        const endMessage = document.getElementById('endMessage');
        const userHeader = document.getElementById('userHeader');
        const errorContainer = document.getElementById('errorContainer');

        // User session info (from PHP)
        const isLoggedIn = <?= json_encode($isLoggedIn ?? false) ?>;
        const userEmail = <?= json_encode($userName ?? null) ?>;
        const isAdmin = <?= json_encode($isAdmin ?? false) ?>;
        const jwtToken = <?= json_encode($jwtToken ?? null) ?>;

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
            
            allMenus.forEach(m => {
                if (m !== menu) {
                    m.classList.remove('active');
                }
            });
            
            menu.classList.toggle('active');
        }

        // Close menus when clicking outside
        document.addEventListener('click', function(event) {
            if (!event.target.closest('.entry-menu')) {
                const allMenus = document.querySelectorAll('.menu-dropdown');
                allMenus.forEach(m => m.classList.remove('active'));
            }
        });

        // Show error message
        function showError(message) {
            errorContainer.innerHTML = `
                <div class="error-message">
                    ${message}
                </div>
            `;
        }

        // Load entries from API
        async function loadEntries() {
            if (isLoading || !hasMore) return;

            isLoading = true;
            loadingElement.style.display = 'block';

            try {
                const url = new URL(`/api/users/${nickname}/entries`, window.location.origin);
                url.searchParams.set('limit', '100');
                if (nextCursor) {
                    url.searchParams.set('before', nextCursor);
                }

                const response = await fetch(url);
                if (!response.ok) {
                    if (response.status === 404) {
                        throw new Error('User not found');
                    }
                    throw new Error(`HTTP error! status: ${response.status}`);
                }

                const data = await response.json();

                // Store user data and show header
                if (data.user && !userData) {
                    userData = data.user;
                    const avatarUrl = userData.photo_url || 
                        `https://www.gravatar.com/avatar/${userData.gravatar_hash}?s=160&d=mp`;
                    document.getElementById('userAvatar').src = avatarUrl;
                    document.getElementById('userName').textContent = `@${userData.nickname}`;
                    userHeader.style.display = 'flex';
                }

                if (data.entries && data.entries.length > 0) {
                    data.entries.forEach(entry => {
                        const card = createEntryCard(entry, {
                            showSourceBadge: false,
                            canModify: canModifyEntry(entry),
                            isAdmin: false
                        });
                        entriesContainer.appendChild(card);
                    });

                    nextCursor = data.next_cursor;
                    hasMore = data.has_more;

                    if (!hasMore) {
                        endMessage.style.display = 'block';
                    }
                } else if (entriesContainer.children.length === 0) {
                    entriesContainer.innerHTML = `
                        <div class="empty-state">
                            <div class="empty-state-icon">📝</div>
                            <h2>No entries yet</h2>
                            <p>This user hasn't posted anything yet.</p>
                        </div>
                    `;
                    hasMore = false;
                }
            } catch (error) {
                console.error('Error loading entries:', error);
                if (error.message === 'User not found') {
                    showError('User not found. Please check the username and try again.');
                } else {
                    showError('Failed to load entries. Please try again later.');
                }
                hasMore = false;
            } finally {
                isLoading = false;
                loadingElement.style.display = 'none';
            }
        }

        // Infinite scroll handler
        function handleScroll() {
            if (isLoading || !hasMore) return;

            const scrollPosition = window.innerHeight + window.scrollY;
            const threshold = document.documentElement.scrollHeight - 500;

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
            const menu = document.getElementById(`menu-${entryId}`);
            if (menu) menu.classList.remove('active');

            const card = document.querySelector(`[data-entry-id="${entryId}"]`);
            if (!card) return;

            const contentDiv = card.querySelector('.entry-content');
            const textDiv = card.querySelector('.entry-text');
            const currentText = textDiv.textContent;

            const previewCard = contentDiv.querySelector('.iframely-embed, .link-preview-card');
            const previewHtml = previewCard ? previewCard.outerHTML : '';

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

                location.reload();
            } catch (error) {
                console.error('Error updating entry:', error);
                alert(`Failed to update entry: ${error.message}`);
            }
        }

        // Delete entry
        async function deleteEntry(entryId) {
            const menu = document.getElementById(`menu-${entryId}`);
            if (menu) menu.classList.remove('active');

            if (!confirm('Are you sure you want to delete this entry?')) {
                return;
            }

            const token = getAuthToken();
            if (!token) {
                alert('You must be logged in to delete entries');
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

                const card = document.querySelector(`[data-entry-id="${entryId}"]`);
                if (card) {
                    card.remove();
                }

                if (entriesContainer.children.length === 0) {
                    entriesContainer.innerHTML = `
                        <div class="empty-state">
                            <div class="empty-state-icon">📝</div>
                            <h2>No entries yet</h2>
                            <p>This user hasn't posted anything yet.</p>
                        </div>
                    `;
                }
            } catch (error) {
                console.error('Error deleting entry:', error);
                alert(`Failed to delete entry: ${error.message}`);
            }
        }

        // Helper function to escape HTML
        function escapeHtml(text) {
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }

        // Helper function to linkify text
        function linkifyText(text) {
            const urlRegex = /(https?:\/\/[^\s]+)/g;
            return text.replace(urlRegex, '<a href="$1" target="_blank" rel="noopener noreferrer">$1</a>');
        }

        // Load initial entries
        loadEntries();
    </script>
</body>
</html>

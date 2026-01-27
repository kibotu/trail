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
        }

        header {
            background: var(--bg-secondary);
            border-bottom: 1px solid var(--border);
            padding: 1.5rem 2rem;
            position: sticky;
            top: 0;
            z-index: 100;
            backdrop-filter: blur(10px);
        }

        .header-content {
            max-width: 800px;
            margin: 0 auto;
            display: flex;
            align-items: center;
            gap: 1rem;
        }

        .header-left {
            display: flex;
            align-items: center;
            gap: 1rem;
            flex: 1;
        }

        .logo {
            font-size: 2rem;
        }

        h1 {
            font-size: 1.5rem;
            font-weight: 600;
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
        }

        .user-menu {
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }

        .user-avatar {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            border: 2px solid var(--border);
            object-fit: cover;
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

        .entries-container {
            display: flex;
            flex-direction: column;
            gap: 1rem;
        }

        .entry-card {
            background: var(--bg-secondary);
            border: 1px solid var(--border);
            border-radius: 12px;
            padding: 1.5rem;
            transition: transform 0.2s, box-shadow 0.2s;
        }

        .entry-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 16px rgba(0, 0, 0, 0.3);
        }

        .entry-header {
            display: flex;
            align-items: center;
            gap: 1rem;
            margin-bottom: 1rem;
        }

        .avatar {
            width: 48px;
            height: 48px;
            border-radius: 50%;
            border: 2px solid var(--border);
            object-fit: cover;
        }

        .user-info {
            flex: 1;
        }

        .user-name {
            font-weight: 600;
            color: var(--text-primary);
            font-size: 1rem;
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
            display: flex;
            justify-content: space-between;
            align-items: center;
            color: var(--text-muted);
            font-size: 0.875rem;
            padding-top: 1rem;
            border-top: 1px solid var(--border);
        }

        .timestamp {
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .entry-actions {
            display: flex;
            gap: 0.5rem;
        }

        .action-button {
            background: var(--bg-tertiary);
            color: var(--text-secondary);
            border: none;
            padding: 0.375rem 0.75rem;
            border-radius: 6px;
            font-size: 0.8125rem;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.2s;
            display: inline-flex;
            align-items: center;
            gap: 0.375rem;
        }

        .action-button:hover {
            background: var(--bg-secondary);
            color: var(--text-primary);
            transform: translateY(-1px);
        }

        .action-button.delete:hover {
            background: rgba(239, 68, 68, 0.2);
            color: #fca5a5;
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
            header {
                padding: 1rem;
            }

            .header-content {
                flex-wrap: wrap;
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

            .user-avatar {
                width: 36px;
                height: 36px;
            }

            main {
                padding: 1rem 0.5rem;
            }

            .entry-card {
                padding: 1rem;
            }

            .avatar {
                width: 40px;
                height: 40px;
            }
        }
    </style>
</head>
<body>
    <header>
        <div class="header-content">
            <div class="header-left">
                <span class="logo">🔗</span>
                <div>
                    <h1>Trail</h1>
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
                    <div class="user-menu">
                        <?php if (isset($userPhotoUrl) && $userPhotoUrl): ?>
                            <img src="<?= htmlspecialchars($userPhotoUrl) ?>" alt="User" class="user-avatar">
                        <?php endif; ?>
                        <a href="/admin/logout.php" class="logout-button">
                            <span>🚪</span>
                            <span>Logout</span>
                        </a>
                    </div>
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
    </header>

    <main>
        <?php if (isset($_GET['error'])): ?>
            <div class="error-message" style="margin-bottom: 2rem;">
                <?= htmlspecialchars($_GET['error']) ?>
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

        // Format timestamp
        function formatTimestamp(timestamp) {
            const date = new Date(timestamp);
            const now = new Date();
            const diffMs = now - date;
            const diffMins = Math.floor(diffMs / 60000);
            const diffHours = Math.floor(diffMs / 3600000);
            const diffDays = Math.floor(diffMs / 86400000);

            if (diffMins < 1) return 'just now';
            if (diffMins < 60) return `${diffMins}m ago`;
            if (diffHours < 24) return `${diffHours}h ago`;
            if (diffDays < 7) return `${diffDays}d ago`;
            
            return date.toLocaleDateString('en-US', { 
                month: 'short', 
                day: 'numeric',
                year: date.getFullYear() !== now.getFullYear() ? 'numeric' : undefined
            });
        }

        // Convert URLs in text to clickable links
        function linkifyText(text) {
            const urlRegex = /(https?:\/\/[^\s]+)/g;
            return text.replace(urlRegex, '<a href="$1" target="_blank" rel="noopener noreferrer">$1</a>');
        }

        // Escape HTML to prevent XSS
        function escapeHtml(text) {
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }

        // Check if current user can modify this entry
        function canModifyEntry(entry) {
            if (!isLoggedIn) return false;
            if (isAdmin) return true;
            return entry.user_email === userEmail;
        }

        // Create entry card HTML
        function createEntryCard(entry) {
            const card = document.createElement('div');
            card.className = 'entry-card';
            card.dataset.entryId = entry.id;
            
            const escapedText = escapeHtml(entry.text);
            const linkedText = linkifyText(escapedText);
            
            const canModify = canModifyEntry(entry);
            
            card.innerHTML = `
                <div class="entry-header">
                    <img src="${escapeHtml(entry.avatar_url)}" alt="${escapeHtml(entry.user_name)}" class="avatar" loading="lazy">
                    <div class="user-info">
                        <div class="user-name">${escapeHtml(entry.user_name)}</div>
                    </div>
                </div>
                <div class="entry-content">
                    <div class="entry-text">${linkedText}</div>
                </div>
                <div class="entry-footer">
                    <div class="timestamp">
                        <span>📅</span>
                        <span>${formatTimestamp(entry.created_at)}</span>
                    </div>
                    ${entry.updated_at && entry.updated_at !== entry.created_at ? 
                        `<div class="timestamp">
                            <span>✏️</span>
                            <span>edited ${formatTimestamp(entry.updated_at)}</span>
                        </div>` : ''}
                    ${canModify ? `
                        <div class="entry-actions">
                            <button class="action-button edit" onclick="editEntry(${entry.id})">
                                <span>✏️</span>
                                <span>Edit</span>
                            </button>
                            <button class="action-button delete" onclick="deleteEntry(${entry.id})">
                                <span>🗑️</span>
                                <span>Delete</span>
                            </button>
                        </div>
                    ` : ''}
                </div>
            `;
            
            return card;
        }

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
                        const card = createEntryCard(entry);
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
            const card = document.querySelector(`[data-entry-id="${entryId}"]`);
            if (!card) return;

            const contentDiv = card.querySelector('.entry-content');
            const textDiv = card.querySelector('.entry-text');
            const currentText = textDiv.textContent;

            // Create edit form
            contentDiv.innerHTML = `
                <div class="edit-form">
                    <textarea class="edit-textarea" id="edit-text-${entryId}" maxlength="280">${escapeHtml(currentText)}</textarea>
                    <div class="edit-actions">
                        <button class="action-button cancel-button" onclick="cancelEdit(${entryId}, '${escapeHtml(currentText).replace(/'/g, "\\'")}')">
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
        function cancelEdit(entryId, originalText) {
            const card = document.querySelector(`[data-entry-id="${entryId}"]`);
            if (!card) return;

            const contentDiv = card.querySelector('.entry-content');
            const linkedText = linkifyText(originalText);
            contentDiv.innerHTML = `<div class="entry-text">${linkedText}</div>`;
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

                // Update the timestamp
                const timestampDiv = card.querySelector('.entry-footer');
                const existingTimestamp = timestampDiv.querySelector('.timestamp');
                const editedTimestamp = document.createElement('div');
                editedTimestamp.className = 'timestamp';
                editedTimestamp.innerHTML = `
                    <span>✏️</span>
                    <span>edited ${formatTimestamp(data.updated_at)}</span>
                `;
                
                // Remove old edited timestamp if exists
                const oldEditedTimestamp = timestampDiv.querySelectorAll('.timestamp')[1];
                if (oldEditedTimestamp) {
                    oldEditedTimestamp.remove();
                }
                
                // Insert new edited timestamp after the created timestamp
                existingTimestamp.after(editedTimestamp);

            } catch (error) {
                console.error('Error updating entry:', error);
                alert(`Failed to update entry: ${error.message}`);
            }
        }

        // Delete entry
        async function deleteEntry(entryId) {
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

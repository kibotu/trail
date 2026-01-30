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

    <script src="/js/snackbar.js"></script>
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

        // User session info (from PHP) - only non-sensitive data
        const isLoggedIn = <?= json_encode($isLoggedIn ?? false) ?>;
        const userEmail = <?= json_encode($userName ?? null) ?>;
        const isAdmin = <?= json_encode($isAdmin ?? false) ?>;
        // JWT token is stored in httpOnly cookie - not accessible to JavaScript for security

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
                            isAdmin: false,
                            isLoggedIn: isLoggedIn,
                            currentUserId: null
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
                            <div class="empty-state-icon"><i class="fa-solid fa-file-lines"></i></div>
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

            if (!isLoggedIn) {
                alert('You must be logged in to edit entries');
                return;
            }

            try {
                // Use fetch with credentials to send httpOnly cookies
                const response = await fetch(`/api/entries/${entryId}`, {
                    method: 'PUT',
                    credentials: 'same-origin',
                    headers: {
                        'Content-Type': 'application/json'
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

            if (!isLoggedIn) {
                alert('You must be logged in to delete entries');
                return;
            }

            try {
                // Use fetch with credentials to send httpOnly cookies
                const response = await fetch(`/api/entries/${entryId}`, {
                    method: 'DELETE',
                    credentials: 'same-origin'
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
                            <div class="empty-state-icon"><i class="fa-solid fa-file-lines"></i></div>
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

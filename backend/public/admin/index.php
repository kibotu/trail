<?php

declare(strict_types=1);

/**
 * Admin Dashboard
 * 
 * Main admin interface for managing entries and users.
 */

require_once __DIR__ . '/../../vendor/autoload.php';
require_once __DIR__ . '/../helpers/session.php';

use Trail\Config\Config;
use Trail\Database\Database;
use Trail\Services\JwtService;

// Security headers
header('X-Frame-Options: DENY');
header('X-Content-Type-Options: nosniff');
header('X-XSS-Protection: 1; mode=block');
header('Referrer-Policy: strict-origin-when-cross-origin');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');

try {
    $config = Config::load(__DIR__ . '/../../secrets.yml');
    $db = Database::getInstance($config);

    // Require admin authentication
    $session = requireAdminAuthentication($db, '/admin/login.php');

    // Get JWT token from session, or generate one if missing
    $jwtToken = $session['jwt_token'] ?? null;
    
    if ($jwtToken === null) {
        // Generate a new JWT token for this session
        $jwtService = new JwtService($config);
        $jwtToken = $jwtService->generate(
            (int)$session['user_id'],
            $session['email'],
            (bool)$session['is_admin']
        );
        
        // Update the session with the new JWT token
        $sessionId = $_COOKIE[SESSION_COOKIE_NAME] ?? null;
        if ($sessionId) {
            $stmt = $db->prepare("UPDATE trail_sessions SET jwt_token = ? WHERE session_id = ?");
            $stmt->execute([$jwtToken, $sessionId]);
        }
    }

    // Get stats
    $stmt = $db->query("SELECT COUNT(*) as count FROM trail_entries");
    $totalEntries = $stmt->fetch()['count'];

    $stmt = $db->query("SELECT COUNT(*) as count FROM trail_users");
    $totalUsers = $stmt->fetch()['count'];

} catch (Exception $e) {
    error_log("Dashboard error: " . $e->getMessage());
    header('Location: /?error=' . urlencode($e->getMessage()));
    exit;
}

$avatarUrl = getUserAvatarUrl($session['photo_url'] ?? null, $session['email']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Trail - Admin Dashboard</title>
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
            --success: #10b981;
            --warning: #f59e0b;
            --error: #ef4444;
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
            justify-content: space-between;
            gap: 1rem;
        }

        .header-left {
            display: flex;
            align-items: center;
            gap: 1rem;
        }

        .logo {
            font-size: 2rem;
        }

        .header-title {
            font-size: 1.5rem;
            font-weight: 600;
        }

        .header-subtitle {
            color: var(--text-secondary);
            font-size: 0.875rem;
            margin-top: 0.25rem;
        }

        .header-right {
            display: flex;
            align-items: center;
            gap: 1rem;
        }

        .user-info {
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }

        .avatar-small {
            width: 36px;
            height: 36px;
            border-radius: 50%;
            border: 2px solid var(--border);
        }

        .user-email {
            font-size: 0.875rem;
            color: var(--text-secondary);
        }

        .button {
            background: var(--accent);
            color: white;
            border: none;
            padding: 0.625rem 1.25rem;
            border-radius: 8px;
            font-size: 0.9375rem;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.2s;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
        }

        .button:hover {
            background: var(--accent-hover);
            transform: translateY(-1px);
            box-shadow: 0 4px 12px rgba(59, 130, 246, 0.4);
        }

        .button.secondary {
            background: var(--bg-tertiary);
            color: var(--text-primary);
        }

        .button.secondary:hover {
            background: var(--bg-secondary);
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.3);
        }

        .container {
            max-width: 800px;
            margin: 0 auto;
            padding: 2rem;
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
            margin-bottom: 2rem;
        }

        .stat-card {
            background: var(--bg-secondary);
            border: 1px solid var(--border);
            border-radius: 12px;
            padding: 1.5rem;
        }

        .stat-label {
            color: var(--text-secondary);
            font-size: 0.875rem;
            margin-bottom: 0.5rem;
        }

        .stat-value {
            font-size: 2rem;
            font-weight: 700;
            color: var(--accent);
        }

        .section-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1.5rem;
        }

        .section-title {
            font-size: 1.5rem;
            font-weight: 600;
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
            transition: transform 0.2s, box-shadow 0.2s, opacity 0.3s;
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

        .user-info-entry {
            flex: 1;
        }

        .user-name {
            font-weight: 600;
            color: var(--text-primary);
            font-size: 1rem;
        }

        .entry-meta {
            color: var(--text-muted);
            font-size: 0.875rem;
        }

        .entry-content {
            margin-bottom: 1rem;
        }

        .entry-text {
            color: var(--text-primary);
            font-size: 1rem;
            line-height: 1.6;
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
            border: none;
            padding: 0.5rem 1rem;
            border-radius: 6px;
            font-size: 0.875rem;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.2s;
        }

        .save-button:hover {
            background: var(--accent-hover);
            transform: translateY(-1px);
        }

        .cancel-button {
            background: var(--bg-tertiary);
            color: var(--text-primary);
            border: none;
            padding: 0.5rem 1rem;
            border-radius: 6px;
            font-size: 0.875rem;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.2s;
        }

        .cancel-button:hover {
            background: var(--bg-secondary);
        }

        .char-counter {
            color: var(--text-muted);
            font-size: 0.875rem;
            margin-bottom: 0.75rem;
        }

        .char-counter.warning {
            color: var(--warning);
        }

        .char-counter.error {
            color: var(--error);
        }

        .loading {
            text-align: center;
            padding: 2rem;
            color: var(--text-secondary);
        }

        .spinner {
            display: inline-block;
            width: 24px;
            height: 24px;
            border: 3px solid var(--border);
            border-top-color: var(--accent);
            border-radius: 50%;
            animation: spin 1s linear infinite;
        }

        @keyframes spin {
            to { transform: rotate(360deg); }
        }

        .empty-state {
            text-align: center;
            padding: 3rem;
            color: var(--text-secondary);
        }

        .empty-state-icon {
            font-size: 3rem;
            margin-bottom: 1rem;
        }

        @media (max-width: 768px) {
            header {
                padding: 1rem;
            }

            .header-content {
                flex-direction: column;
                align-items: flex-start;
            }

            .header-right {
                width: 100%;
                justify-content: space-between;
            }

            .container {
                padding: 1rem;
            }

            .stats-grid {
                grid-template-columns: 1fr;
            }

            .user-email {
                display: none;
            }
        }
    </style>
</head>
<body>
    <header>
        <div class="header-content">
            <div class="header-left">
                <div class="logo">🔗</div>
                <div>
                    <div class="header-title">Trail Admin</div>
                    <div class="header-subtitle">Manage entries and users</div>
                </div>
            </div>
            <div class="header-right">
                <button class="button secondary" onclick="window.location.href='/admin/users.php'">
                    <span>👥</span>
                    <span>Users</span>
                </button>
                <div class="user-info">
                    <img src="<?= $avatarUrl ?>" alt="Avatar" class="avatar-small">
                    <div class="user-email"><?= htmlspecialchars($session['email']) ?></div>
                </div>
                <button class="button secondary" onclick="window.location.href='/admin/logout.php'">Logout</button>
            </div>
        </div>
    </header>

    <div class="container">
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-label">Total Entries</div>
                <div class="stat-value"><?= number_format($totalEntries) ?></div>
            </div>
            <div class="stat-card">
                <div class="stat-label">Total Users</div>
                <div class="stat-value"><?= number_format($totalUsers) ?></div>
            </div>
            <div class="stat-card">
                <div class="stat-label">Your Role</div>
                <div class="stat-value" style="font-size: 1.5rem;">👑 Admin</div>
            </div>
        </div>

        <div class="section-header">
            <h2 class="section-title">All Entries</h2>
        </div>

        <div id="entries-container" class="entries-container">
            <!-- Entries will be loaded here -->
        </div>

        <div id="loading" class="loading" style="display: none;">
            <div class="spinner"></div>
            <p style="margin-top: 1rem;">Loading more entries...</p>
        </div>

        <div id="empty-state" class="empty-state" style="display: none;">
            <div class="empty-state-icon">📭</div>
            <p>No entries yet. Start sharing links!</p>
        </div>
    </div>

    <script>
        // Store JWT token from session
        const jwtToken = <?= json_encode($jwtToken ?? null) ?>;
        
        let currentPage = 0;
        let loading = false;
        let hasMore = true;
        const pageSize = 20;

        // Load initial entries
        loadEntries();

        // Infinite scroll
        window.addEventListener('scroll', () => {
            if (loading || !hasMore) return;
            
            const scrollPosition = window.innerHeight + window.scrollY;
            const threshold = document.documentElement.scrollHeight - 500;
            
            if (scrollPosition >= threshold) {
                loadEntries();
            }
        });

        async function loadEntries() {
            if (loading || !hasMore) return;
            
            loading = true;
            document.getElementById('loading').style.display = 'block';
            
            try {
                const response = await fetch(`/api/admin/entries?page=${currentPage}&limit=${pageSize}`, {
                    headers: {
                        'Authorization': 'Bearer ' + (jwtToken || localStorage.getItem('trail_jwt'))
                    }
                });

                if (!response.ok) {
                    throw new Error(`HTTP ${response.status}`);
                }

                const data = await response.json();
                
                if (data.entries && data.entries.length > 0) {
                    renderEntries(data.entries);
                    currentPage++;
                    hasMore = data.entries.length === pageSize;
                } else {
                    hasMore = false;
                    if (currentPage === 0) {
                        document.getElementById('empty-state').style.display = 'block';
                    }
                }
            } catch (error) {
                console.error('Error loading entries:', error);
                alert('Failed to load entries. Please refresh the page.');
            } finally {
                loading = false;
                document.getElementById('loading').style.display = 'none';
            }
        }

        function renderEntries(entries) {
            const container = document.getElementById('entries-container');
            
            entries.forEach(entry => {
                const card = createEntryCard(entry);
                container.appendChild(card);
            });
        }

        function createEntryCard(entry) {
            const card = document.createElement('div');
            card.className = 'entry-card';
            card.id = `entry-${entry.id}`;
            card.dataset.entryId = entry.id;
            
            const avatarUrl = entry.photo_url || `https://www.gravatar.com/avatar/${entry.gravatar_hash || '00000000000000000000000000000000'}?s=96&d=mp`;
            const userName = entry.user_name || entry.user_email || 'Unknown';
            const escapedText = escapeHtml(entry.text);
            const linkedText = linkifyText(escapedText);
            const previewCard = createLinkPreviewCard(entry);
            
            card.innerHTML = `
                <div class="entry-header">
                    <img src="${escapeHtml(avatarUrl)}" alt="${escapeHtml(userName)}" class="avatar" loading="lazy">
                    <div class="user-info">
                        <div class="user-name">${escapeHtml(userName)}</div>
                    </div>
                </div>
                <div class="entry-content" id="content-${entry.id}">
                    <div class="entry-text">${linkedText}</div>
                    ${previewCard}
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
                </div>
            `;
            
            return card;
        }

        function linkifyText(text) {
            const urlRegex = /(https?:\/\/[^\s]+)/g;
            return text.replace(urlRegex, (url) => {
                return `<a href="${url}" target="_blank" rel="noopener noreferrer">${url}</a>`;
            });
        }

        function createLinkPreviewCard(entry) {
            if (!entry.preview_url) {
                return '';
            }

            const domain = extractDomain(entry.preview_url);
            
            return `
                <a href="${escapeHtml(entry.preview_url)}" target="_blank" rel="noopener noreferrer" class="link-preview-card">
                    ${entry.preview_image ? `<img src="${escapeHtml(entry.preview_image)}" alt="${escapeHtml(entry.preview_title || '')}" class="link-preview-image" loading="lazy">` : ''}
                    <div class="link-preview-content">
                        ${entry.preview_title ? `<div class="link-preview-title">${escapeHtml(entry.preview_title)}</div>` : ''}
                        ${entry.preview_description ? `<div class="link-preview-description">${escapeHtml(entry.preview_description)}</div>` : ''}
                        <div class="link-preview-url">
                            <span>🔗</span>
                            <span>${escapeHtml(domain)}</span>
                        </div>
                    </div>
                </a>
            `;
        }

        function extractDomain(url) {
            try {
                const urlObj = new URL(url);
                return urlObj.hostname.replace('www.', '');
            } catch {
                return url;
            }
        }

        function formatTimestamp(timestamp) {
            const date = new Date(timestamp);
            const now = new Date();
            const diffInSeconds = Math.floor((now - date) / 1000);
            
            if (diffInSeconds < 60) return 'just now';
            if (diffInSeconds < 3600) return `${Math.floor(diffInSeconds / 60)}m ago`;
            if (diffInSeconds < 86400) return `${Math.floor(diffInSeconds / 3600)}h ago`;
            if (diffInSeconds < 604800) return `${Math.floor(diffInSeconds / 86400)}d ago`;
            
            return date.toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' });
        }

        function escapeHtml(text) {
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }

        function editEntry(entryId) {
            const card = document.querySelector(`[data-entry-id="${entryId}"]`);
            const contentDiv = card.querySelector('.entry-content');
            const entryText = contentDiv.querySelector('.entry-text').textContent;
            
            const editForm = document.createElement('div');
            editForm.className = 'edit-form';
            editForm.innerHTML = `
                <textarea id="edit-text-${entryId}" class="edit-textarea" maxlength="280">${escapeHtml(entryText)}</textarea>
                <div class="char-counter" id="char-count-${entryId}">${entryText.length}/280 characters</div>
                <div class="edit-actions">
                    <button class="cancel-button" onclick="cancelEdit(${entryId})">Cancel</button>
                    <button class="save-button" onclick="saveEdit(${entryId})">Save</button>
                </div>
            `;
            
            contentDiv.appendChild(editForm);
            
            const textarea = document.getElementById(`edit-text-${entryId}`);
            textarea.addEventListener('input', () => updateCharCount(entryId));
            textarea.focus();
        }

        function cancelEdit(entryId) {
            const card = document.querySelector(`[data-entry-id="${entryId}"]`);
            const editForm = card.querySelector('.edit-form');
            if (editForm) {
                editForm.remove();
            }
        }

        function updateCharCount(entryId) {
            const textarea = document.getElementById(`edit-text-${entryId}`);
            const charCount = document.getElementById(`char-count-${entryId}`);
            const length = textarea.value.length;
            charCount.textContent = `${length}/280 characters`;
            
            charCount.className = 'char-counter';
            if (length > 260) {
                charCount.classList.add('error');
            } else if (length > 240) {
                charCount.classList.add('warning');
            }
        }

        async function saveEdit(entryId) {
            const textarea = document.getElementById(`edit-text-${entryId}`);
            const newText = textarea.value.trim();

            if (!newText) {
                alert('Entry text cannot be empty');
                return;
            }

            const token = jwtToken || localStorage.getItem('trail_jwt');
            if (!token) {
                alert('You must be logged in to edit entries');
                return;
            }

            try {
                const response = await fetch(`/api/admin/entries/${entryId}`, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Authorization': `Bearer ${token}`
                    },
                    body: JSON.stringify({ text: newText })
                });

                if (!response.ok) {
                    const errorData = await response.json().catch(() => ({}));
                    throw new Error(errorData.error || `HTTP ${response.status}`);
                }

                const data = await response.json();

                // Update the entry content
                const card = document.querySelector(`[data-entry-id="${entryId}"]`);
                const contentDiv = card.querySelector('.entry-content');
                const escapedText = escapeHtml(newText);
                const linkedText = linkifyText(escapedText);
                
                contentDiv.innerHTML = `<div class="entry-text">${linkedText}</div>`;

                // Update the timestamp
                const footer = card.querySelector('.entry-footer');
                const timestampDiv = footer.querySelector('.timestamp');
                const editedTimestamp = document.createElement('div');
                editedTimestamp.className = 'timestamp';
                editedTimestamp.innerHTML = `
                    <span>✏️</span>
                    <span>edited ${formatTimestamp(data.updated_at || new Date().toISOString())}</span>
                `;
                
                // Remove old edited timestamp if exists
                const oldEditedTimestamp = footer.querySelectorAll('.timestamp')[1];
                if (oldEditedTimestamp && oldEditedTimestamp.textContent.includes('edited')) {
                    oldEditedTimestamp.remove();
                }
                
                // Insert new edited timestamp after the created timestamp
                timestampDiv.after(editedTimestamp);

            } catch (error) {
                console.error('Error updating entry:', error);
                alert(`Failed to update entry: ${error.message}`);
            }
        }

        async function deleteEntry(id) {
            if (!confirm('Are you sure you want to delete this entry?')) {
                return;
            }

            const token = jwtToken || localStorage.getItem('trail_jwt');
            if (!token) {
                alert('Authentication token not found. Please refresh the page and log in again.');
                return;
            }

            try {
                const response = await fetch(`/api/admin/entries/${id}`, {
                    method: 'DELETE',
                    headers: {
                        'Authorization': 'Bearer ' + token
                    }
                });

                if (response.ok) {
                    const entryCard = document.getElementById(`entry-${id}`);
                    entryCard.style.opacity = '0';
                    entryCard.style.transform = 'scale(0.95)';
                    setTimeout(() => {
                        entryCard.remove();
                        
                        // Check if there are no more entries
                        const container = document.getElementById('entries-container');
                        if (container.children.length === 0 && currentPage === 0) {
                            document.getElementById('empty-state').style.display = 'block';
                        }
                    }, 300);
                } else {
                    const data = await response.json().catch(() => ({ error: 'Unknown error' }));
                    alert('Failed to delete entry: ' + (data.error || `HTTP ${response.status}`));
                }
            } catch (error) {
                console.error('Error deleting entry:', error);
                alert('Error: ' + error.message);
            }
        }
    </script>
</body>
</html>

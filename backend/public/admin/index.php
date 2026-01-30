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
use Trail\Services\IframelyUsageTracker;
use Trail\Services\StorageService;

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

    // Get iframe.ly usage stats
    $adminEmail = $config['production']['admin_email'] ?? 'cloudgazer3d@gmail.com';
    $usageTracker = new IframelyUsageTracker($db, $adminEmail);
    $iframelyUsage = $usageTracker->getCurrentMonthUsage();
    $iframelyLimit = IframelyUsageTracker::getMonthlyLimit();
    $iframelyRemaining = $usageTracker->getRemainingCalls();
    $iframelyPercentage = ($iframelyUsage / $iframelyLimit) * 100;

    // Get storage stats (with fallback if trail_images table doesn't exist)
    $totalImages = 0;
    $totalImageSize = 0;
    $totalImageSizeFormatted = '0 B';
    $totalDiskSize = 0;
    $totalDiskSizeFormatted = '0 B';
    $tempSize = 0;
    $tempSizeFormatted = '0 B';
    
    try {
        $uploadBasePath = __DIR__ . '/../uploads/images';
        $tempBasePath = __DIR__ . '/../../storage/temp';
        $storageService = new StorageService($db, $uploadBasePath, $tempBasePath);
        $storageSummary = $storageService->getStorageSummary();
        $totalImages = $storageSummary['total_images'];
        $totalImageSize = $storageSummary['total_image_size'];
        $totalImageSizeFormatted = $storageSummary['total_image_size_formatted'];
        $totalDiskSize = $storageSummary['total_disk_size'];
        $totalDiskSizeFormatted = $storageSummary['total_disk_size_formatted'];
        $tempSize = $storageSummary['temp_size'];
        $tempSizeFormatted = $storageSummary['temp_size_formatted'];
    } catch (Exception $e) {
        // Silently handle if trail_images table doesn't exist yet
        error_log("Storage stats error (table may not exist): " . $e->getMessage());
    }

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
    <link rel="icon" type="image/x-icon" href="/assets/favicon.ico">
    <link rel="stylesheet" href="/assets/fonts/fonts.css">
    <link rel="stylesheet" href="/assets/fontawesome/css/all.min.css">
    <link rel="stylesheet" href="/assets/css/main.css">
</head>
<body class="page-admin-dashboard">
    <div class="orb orb-1"></div>
    <div class="orb orb-2"></div>

    <header>
        <div class="header-content">
            <div class="header-left">
                <div class="logo"><i class="fa-solid fa-link"></i></div>
                <div>
                    <div class="header-title">Trail Admin</div>
                    <div class="header-subtitle">Manage entries and users</div>
                </div>
            </div>
            <div class="header-right">
                <button class="button secondary" onclick="window.location.href='/'">
                    <i class="fa-solid fa-home"></i>
                    <span>Home</span>
                </button>
                <button class="button secondary" onclick="window.location.href='/admin/users.php'">
                    <i class="fa-solid fa-users"></i>
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
                <div class="stat-label">Total Images</div>
                <div class="stat-value"><?= number_format($totalImages) ?></div>
                <div class="stat-label" style="margin-top: 0.5rem; font-size: 0.875rem;">DB Size: <?= $totalImageSizeFormatted ?></div>
            </div>
            <div class="stat-card">
                <div class="stat-label">Storage Size</div>
                <div class="stat-value" style="font-size: 1.5rem;"><?= $totalDiskSizeFormatted ?></div>
                <div class="stat-label" style="margin-top: 0.5rem; font-size: 0.875rem;">Temp: <?= $tempSizeFormatted ?></div>
                <div style="display: flex; gap: 0.5rem; margin-top: 0.5rem;">
                    <?php if ($tempSize > 0): ?>
                    <button onclick="clearCache()" class="btn-clear-cache" style="padding: 0.25rem 0.75rem; font-size: 0.75rem; background: var(--bg-tertiary); border: 1px solid var(--border); border-radius: 4px; color: var(--text-secondary); cursor: pointer;">Clear Temp</button>
                    <?php endif; ?>
                    <button onclick="pruneImages()" class="btn-prune-images" style="padding: 0.25rem 0.75rem; font-size: 0.75rem; background: var(--bg-tertiary); border: 1px solid var(--border); border-radius: 4px; color: var(--text-secondary); cursor: pointer;">
                        <i class="fa-solid fa-broom"></i> Prune Images
                    </button>
                </div>
            </div>
            <div class="stat-card iframely">
                <div class="stat-label"><i class="fa-solid fa-link"></i> iframe.ly API Usage (<?= date('F Y') ?>)</div>
                <div class="stat-value">
                    <?= number_format($iframelyUsage) ?> <span style="font-size: 1rem; color: var(--text-secondary);">/ <?= number_format($iframelyLimit) ?></span>
                </div>
                <div class="usage-bar-container">
                    <div class="usage-bar <?= $iframelyPercentage >= 90 ? 'danger' : ($iframelyPercentage >= 75 ? 'warning' : '') ?>" 
                         style="width: <?= min(100, $iframelyPercentage) ?>%"></div>
                </div>
                <div class="usage-details">
                    <span><?= number_format($iframelyRemaining) ?> remaining</span>
                    <span><?= number_format($iframelyPercentage, 1) ?>% used</span>
                </div>
                <?php if ($iframelyPercentage >= 90): ?>
                    <div class="usage-status danger">
                        <i class="fa-solid fa-triangle-exclamation"></i> Limit almost reached
                    </div>
                <?php elseif ($iframelyPercentage >= 75): ?>
                    <div class="usage-status warning">
                        <i class="fa-solid fa-bolt"></i> High usage
                    </div>
                <?php else: ?>
                    <div class="usage-status ok">
                        <i class="fa-solid fa-check"></i> Healthy
                    </div>
                <?php endif; ?>
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
            <div class="empty-state-icon"><i class="fa-solid fa-inbox"></i></div>
            <p>No entries yet. Start sharing links!</p>
        </div>
    </div>

    <script src="/js/snackbar.js"></script>
    <script src="/js/card-template.js"></script>
    <script>
        // JWT token for admin operations
        const jwtToken = '<?= htmlspecialchars($jwtToken ?? '', ENT_QUOTES, 'UTF-8') ?>';
        
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
                    credentials: 'same-origin' // Include httpOnly cookie with JWT
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
                // Use shared card template with admin options
                const card = createEntryCard(entry, {
                    showSourceBadge: true,  // Show source badges in admin
                    canModify: true,        // Admin can modify all entries
                    isAdmin: true,          // Admin context
                    isLoggedIn: true,       // Admin is always logged in
                    currentUserId: null     // Not needed for admin
                });
                container.appendChild(card);
            });
        }
        
        // Toggle menu dropdown (same as landing page)
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
                    <i class="fa-solid fa-pen"></i>
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
        
        // Clear cache function
        async function clearCache() {
            if (!jwtToken) {
                alert('Authentication token not found. Please refresh the page and log in again.');
                return;
            }
            
            if (!confirm('Clear all temporary upload cache files older than 1 hour?')) {
                return;
            }
            
            try {
                const response = await fetch('/api/admin/cache/clear', {
                    method: 'POST',
                    headers: {
                        'Authorization': 'Bearer ' + jwtToken
                    }
                });
                
                const data = await response.json();
                
                if (data.success) {
                    alert(data.message);
                    location.reload();
                } else {
                    alert('Failed to clear cache: ' + (data.error || 'Unknown error'));
                }
            } catch (error) {
                console.error('Error clearing cache:', error);
                alert('Failed to clear cache: ' + error.message);
            }
        }
        
        // Prune orphaned images function
        async function pruneImages() {
            if (!jwtToken) {
                alert('Authentication token not found. Please refresh the page and log in again.');
                return;
            }
            
            if (!confirm('Prune orphaned images?\n\nThis will:\n• Delete orphaned image files not referenced by entries, comments, or users\n• Remove database records for images where files no longer exist\n\nThis action cannot be undone.')) {
                return;
            }
            
            const button = event.target.closest('button');
            const originalHTML = button.innerHTML;
            button.disabled = true;
            button.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Pruning...';
            
            try {
                const response = await fetch('/api/admin/images/prune', {
                    method: 'POST',
                    headers: {
                        'Authorization': 'Bearer ' + jwtToken
                    }
                });
                
                const data = await response.json();
                
                if (data.success) {
                    let message = data.message;
                    if (data.details && data.details.length > 0) {
                        console.log('Prune details:', data.details);
                        message += '\n\nSee console for details.';
                    }
                    alert(message);
                    location.reload();
                } else {
                    alert('Failed to prune images: ' + (data.error || 'Unknown error'));
                }
            } catch (error) {
                console.error('Error pruning images:', error);
                alert('Failed to prune images: ' + error.message);
            } finally {
                button.disabled = false;
                button.innerHTML = originalHTML;
            }
        }
    </script>
</body>
</html>

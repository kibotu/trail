<?php

declare(strict_types=1);

/**
 * Admin Users Management
 * 
 * View and manage all users.
 */

require_once __DIR__ . '/../../vendor/autoload.php';
require_once __DIR__ . '/../helpers/session.php';

use Trail\Config\Config;
use Trail\Database\Database;
use Trail\Models\User;
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

    // Get all users
    $userModel = new User($db);
    $users = $userModel->getAll(100, null);
    
    // Debug: Log user count and IDs
    error_log("Retrieved " . count($users) . " users from database");
    $userIds = array_map(function($u) { return $u['id']; }, $users);
    error_log("User IDs: " . implode(', ', $userIds));
    
    // Check for duplicates in the raw query result
    $uniqueIds = array_unique($userIds);
    if (count($userIds) !== count($uniqueIds)) {
        error_log("WARNING: Duplicate user IDs found in query result!");
    }

    // Add avatar URLs
    foreach ($users as &$user) {
        $user['avatar_url'] = getUserAvatarFromData($user, 96);
    }
    unset($user); // Break the reference to avoid issues in subsequent loops

} catch (Exception $e) {
    error_log("Admin users error: " . $e->getMessage());
    header('Location: /admin?error=' . urlencode($e->getMessage()));
    exit;
}

$avatarUrl = getUserAvatarUrl($session['photo_url'] ?? null, $session['email']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Trail - Users Management</title>
    <link rel="stylesheet" href="/assets/fonts/fonts.css">
    <link rel="stylesheet" href="/assets/fontawesome/css/all.min.css">
    <link rel="stylesheet" href="/assets/css/main.css">
</head>
<body class="page-admin-users">
    <div class="orb orb-1"></div>
    <div class="orb orb-2"></div>

    <header>
        <div class="header-content">
            <div class="header-left">
                <div class="logo"><i class="fa-solid fa-link"></i></div>
                <div>
                    <div class="header-title">Users Management</div>
                    <div class="header-subtitle">Manage user accounts</div>
                </div>
            </div>
            <div class="header-right">
                <button class="button secondary" onclick="window.location.href='/'">
                    <i class="fa-solid fa-home"></i>
                    <span>Home</span>
                </button>
                <button class="button secondary" onclick="window.location.href='/admin/'">
                    <span>←</span>
                    <span>Dashboard</span>
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
        <div class="section-header">
            <h2 class="section-title">All Users (<?= count($users) ?>)</h2>
        </div>

        <?php 
        // Check for actual duplicate emails (which would be a data integrity issue)
        $emailCounts = [];
        $userIdsSeen = [];
        
        foreach ($users as $user) {
            $userId = $user['id'];
            $email = $user['email'];
            
            // Track if we've seen this user ID before (indicates duplicate in array)
            if (in_array($userId, $userIdsSeen)) {
                error_log("WARNING: User ID {$userId} appears multiple times in users array");
            }
            $userIdsSeen[] = $userId;
            
            if (!isset($emailCounts[$email])) {
                $emailCounts[$email] = [];
            }
            $emailCounts[$email][] = $userId;
        }
        
        // Only show warning if there are actual duplicate emails (different user IDs with same email)
        $duplicateEmails = [];
        foreach ($emailCounts as $email => $userIds) {
            $uniqueIds = array_unique($userIds);
            if (count($uniqueIds) > 1) {
                $duplicateEmails[$email] = $uniqueIds;
            }
        }
        
        if (!empty($duplicateEmails)): ?>
            <div style="background: var(--error); color: white; padding: 1rem; border-radius: 8px; margin-bottom: 1rem;">
                <strong>🚨 Data Integrity Error:</strong> Found duplicate email addresses in the database:
                <ul style="margin: 0.5rem 0 0 1.5rem;">
                    <?php foreach ($duplicateEmails as $email => $userIds): ?>
                        <li><?= htmlspecialchars($email) ?> (User IDs: <?= implode(', ', $userIds) ?>)</li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>

        <?php if (empty($users)): ?>
            <div class="empty-state">
                <div class="empty-state-icon"><i class="fa-solid fa-users"></i></div>
                <p>No users found.</p>
            </div>
        <?php else: ?>
            <div class="users-grid">
                <?php foreach ($users as $user): ?>
                    <div class="user-card" id="user-<?= $user['id'] ?>">
                        <div class="user-header">
                            <img src="<?= htmlspecialchars($user['avatar_url']) ?>" alt="<?= htmlspecialchars($user['name']) ?>" class="avatar">
                            <div class="user-info-card">
                                <div class="user-name"><?= htmlspecialchars($user['name']) ?></div>
                                <div class="user-email-text"><?= htmlspecialchars($user['email']) ?></div>
                            </div>
                        </div>
                        
                        <div class="user-meta">
                            <div class="meta-item">
                                <div class="meta-label">User ID</div>
                                <div class="meta-value">#<?= $user['id'] ?></div>
                            </div>
                            <div class="meta-item">
                                <div class="meta-label">Google ID</div>
                                <div class="meta-value" style="font-size: 0.75rem; font-family: monospace;" title="<?= htmlspecialchars($user['google_id']) ?>"><?= htmlspecialchars(substr($user['google_id'], 0, 12)) ?>...</div>
                            </div>
                            <div class="meta-item">
                                <div class="meta-label">Role</div>
                                <div class="meta-value">
                                    <?php if ($user['is_admin']): ?>
                                        <span class="admin-badge"><i class="fa-solid fa-crown"></i> Admin</span>
                                    <?php else: ?>
                                        User
                                    <?php endif; ?>
                                </div>
                            </div>
                            <div class="meta-item">
                                <div class="meta-label">Joined</div>
                                <div class="meta-value"><?= date('M j, Y', strtotime($user['created_at'])) ?></div>
                            </div>
                        </div>

                        <div class="user-actions">
                            <button class="action-button delete" onclick="deleteUser(<?= $user['id'] ?>)">
                                <i class="fa-solid fa-trash"></i>
                                <span>Delete User</span>
                            </button>
                        </div>

                        <div class="debug-section">
                            <button class="debug-toggle" onclick="toggleDebug(<?= $user['id'] ?>)">
                                <i id="debug-icon-<?= $user['id'] ?>" class="fa-solid fa-caret-right"></i>
                                <span>Show Raw Data</span>
                            </button>
                            <div class="debug-content" id="debug-<?= $user['id'] ?>">
                                <pre><?= htmlspecialchars(json_encode([
                                    'id' => $user['id'],
                                    'google_id' => $user['google_id'],
                                    'email' => $user['email'],
                                    'name' => $user['name'],
                                    'is_admin' => $user['is_admin'],
                                    'created_at' => $user['created_at'],
                                    'updated_at' => $user['updated_at']
                                ], JSON_PRETTY_PRINT)) ?></pre>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>

    <script>
        // JWT token is stored in httpOnly cookie - not accessible to JavaScript for security

        function toggleDebug(userId) {
            const debugContent = document.getElementById(`debug-${userId}`);
            const debugIcon = document.getElementById(`debug-icon-${userId}`);
            
            if (debugContent.classList.contains('show')) {
                debugContent.classList.remove('show');
                debugIcon.className = 'fa-solid fa-caret-right';
            } else {
                debugContent.classList.add('show');
                debugIcon.className = 'fa-solid fa-caret-down';
            }
        }

        async function deleteUser(id) {
            if (!confirm('Are you sure you want to delete this user? This will also delete all their entries.')) {
                return;
            }

            const token = jwtToken || localStorage.getItem('trail_jwt');
            if (!token) {
                alert('Authentication token not found. Please refresh the page and log in again.');
                return;
            }

            try {
                const response = await fetch(`/api/admin/users/${id}`, {
                    method: 'DELETE',
                    headers: {
                        'Authorization': 'Bearer ' + token
                    }
                });

                if (response.ok) {
                    const userCard = document.getElementById(`user-${id}`);
                    userCard.style.opacity = '0';
                    userCard.style.transform = 'scale(0.95)';
                    setTimeout(() => {
                        userCard.remove();
                        
                        // Check if there are no more users
                        const grid = document.querySelector('.users-grid');
                        if (grid && grid.children.length === 0) {
                            location.reload();
                        }
                    }, 300);
                } else {
                    const data = await response.json().catch(() => ({ error: 'Unknown error' }));
                    alert('Failed to delete user: ' + (data.error || `HTTP ${response.status}`));
                }
            } catch (error) {
                console.error('Error deleting user:', error);
                alert('Error: ' + error.message);
            }
        }
    </script>
</body>
</html>

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
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;
            background: var(--bg-primary);
            color: var(--text-primary);
            line-height: 1.6;
            min-height: 100vh;
            overflow-x: hidden;
            position: relative;
        }

        h1, h2, h3, h4, h5, h6 {
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
            padding: 1.5rem 2rem;
            position: sticky;
            top: 0;
            z-index: 100;
            backdrop-filter: blur(10px);
        }

        .header-content {
            max-width: 1000px;
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
            max-width: 1000px;
            margin: 0 auto;
            padding: 2rem;
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

        .users-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(320px, 1fr));
            gap: 1.5rem;
        }

        .user-card {
            background: var(--bg-secondary);
            border: 1px solid var(--border);
            border-radius: 12px;
            padding: 1.5rem;
            transition: transform 0.2s, box-shadow 0.2s, opacity 0.3s;
        }

        .user-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 16px rgba(0, 0, 0, 0.3);
        }

        .user-header {
            display: flex;
            align-items: center;
            gap: 1rem;
            margin-bottom: 1rem;
        }

        .avatar {
            width: 64px;
            height: 64px;
            border-radius: 50%;
            border: 2px solid var(--border);
            object-fit: cover;
        }

        .user-info-card {
            flex: 1;
            min-width: 0; /* Allow flex item to shrink below content size */
            overflow: hidden;
        }

        .user-name {
            font-weight: 600;
            color: var(--text-primary);
            font-size: 1.125rem;
            margin-bottom: 0.25rem;
            word-wrap: break-word;
            overflow-wrap: break-word;
            hyphens: auto;
        }

        .user-email-text {
            color: var(--text-secondary);
            font-size: 0.875rem;
            word-wrap: break-word;
            overflow-wrap: break-word;
            hyphens: auto;
        }

        .user-meta {
            display: flex;
            flex-wrap: wrap;
            gap: 1rem;
            margin-bottom: 1rem;
            padding-top: 1rem;
            border-top: 1px solid var(--border);
        }

        .meta-item {
            display: flex;
            flex-direction: column;
            min-width: 0; /* Allow flex item to shrink */
            flex: 1 1 auto;
        }

        .meta-label {
            color: var(--text-muted);
            font-size: 0.75rem;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            white-space: nowrap;
        }

        .meta-value {
            color: var(--text-primary);
            font-size: 0.875rem;
            margin-top: 0.25rem;
            word-wrap: break-word;
            overflow-wrap: break-word;
        }

        .admin-badge {
            display: inline-flex;
            align-items: center;
            gap: 0.25rem;
            background: rgba(59, 130, 246, 0.2);
            color: var(--accent);
            padding: 0.25rem 0.75rem;
            border-radius: 12px;
            font-size: 0.75rem;
            font-weight: 600;
        }

        .user-actions {
            display: flex;
            gap: 0.5rem;
        }

        .action-button {
            background: var(--bg-tertiary);
            color: var(--text-primary);
            border: 1px solid var(--border);
            padding: 0.5rem 1rem;
            border-radius: 6px;
            font-size: 0.875rem;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.2s;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            flex: 1;
            justify-content: center;
        }

        .action-button:hover {
            background: var(--bg-secondary);
            transform: translateY(-1px);
        }

        .action-button.delete {
            background: var(--error);
            border-color: var(--error);
        }

        .action-button.delete:hover {
            background: #dc2626;
        }

        .debug-section {
            margin-top: 1rem;
            padding-top: 1rem;
            border-top: 1px solid var(--border);
        }

        .debug-toggle {
            background: var(--bg-tertiary);
            color: var(--text-secondary);
            border: 1px solid var(--border);
            padding: 0.5rem 1rem;
            border-radius: 6px;
            font-size: 0.75rem;
            cursor: pointer;
            width: 100%;
            text-align: left;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .debug-toggle:hover {
            background: var(--bg-secondary);
        }

        .debug-content {
            display: none;
            margin-top: 0.5rem;
            padding: 0.75rem;
            background: var(--bg-primary);
            border-radius: 6px;
            font-family: monospace;
            font-size: 0.75rem;
            overflow-x: auto;
            color: var(--text-secondary);
        }

        .debug-content.show {
            display: block;
        }

        .debug-content pre {
            margin: 0;
            white-space: pre-wrap;
            word-wrap: break-word;
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

            .users-grid {
                grid-template-columns: 1fr;
            }

            .user-email {
                display: none;
            }

            .user-header {
                flex-direction: column;
                align-items: flex-start;
            }

            .avatar {
                width: 48px;
                height: 48px;
            }
        }

        @media (max-width: 480px) {
            .user-meta {
                flex-direction: column;
            }

            .meta-item {
                width: 100%;
            }
        }
    </style>
</head>
<body>
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
                <button class="button secondary" onclick="window.location.href='/admin/'">
                    <span>←</span>
                    <span>Back to Dashboard</span>
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
        // Store JWT token from session
        const jwtToken = <?= json_encode($jwtToken ?? null) ?>;

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

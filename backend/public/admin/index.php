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

// Load environment variables
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/../../..');
$dotenv->safeLoad();

// Security headers
header('X-Frame-Options: DENY');
header('X-Content-Type-Options: nosniff');
header('X-XSS-Protection: 1; mode=block');
header('Referrer-Policy: strict-origin-when-cross-origin');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');

try {
    $config = Config::load(__DIR__ . '/../../config.yml');
    $db = Database::getInstance($config);

    // Require authentication
    $session = requireAuthentication($db);

    // Get stats
    $stmt = $db->query("SELECT COUNT(*) as count FROM trail_entries");
    $totalEntries = $stmt->fetch()['count'];

    $stmt = $db->query("SELECT COUNT(*) as count FROM trail_users");
    $totalUsers = $stmt->fetch()['count'];

    // Get recent entries
    $stmt = $db->prepare("
        SELECT e.*, u.email, u.name, u.gravatar_hash
        FROM trail_entries e
        JOIN trail_users u ON e.user_id = u.id
        ORDER BY e.created_at DESC
        LIMIT 10
    ");
    $stmt->execute();
    $recentEntries = $stmt->fetchAll();

} catch (Exception $e) {
    error_log("Dashboard error: " . $e->getMessage());
    header('Location: /admin/login.php?error=' . urlencode($e->getMessage()));
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
            --accent: #3b82f6;
            --accent-hover: #2563eb;
            --border: rgba(255, 255, 255, 0.1);
            --success: #10b981;
            --warning: #f59e0b;
        }

        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;
            background: var(--bg-primary);
            color: var(--text-primary);
            line-height: 1.6;
        }

        .header {
            background: var(--bg-secondary);
            border-bottom: 1px solid var(--border);
            padding: 1rem 2rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .header-left {
            display: flex;
            align-items: center;
            gap: 1rem;
        }

        .logo {
            font-size: 1.5rem;
        }

        .header-title {
            font-size: 1.25rem;
            font-weight: 600;
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

        .avatar {
            width: 36px;
            height: 36px;
            border-radius: 50%;
            border: 2px solid var(--border);
        }

        .user-email {
            font-size: 0.875rem;
            color: var(--text-secondary);
        }

        .logout-btn {
            background: var(--bg-tertiary);
            color: var(--text-primary);
            padding: 0.5rem 1rem;
            border-radius: 6px;
            text-decoration: none;
            font-size: 0.875rem;
            transition: all 0.2s;
            border: 1px solid var(--border);
        }

        .logout-btn:hover {
            background: var(--bg-primary);
        }

        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 2rem;
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 1.5rem;
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
            font-size: 2.5rem;
            font-weight: 700;
            color: var(--accent);
        }

        .section-title {
            font-size: 1.5rem;
            font-weight: 600;
            margin-bottom: 1rem;
        }

        .entries-list {
            background: var(--bg-secondary);
            border: 1px solid var(--border);
            border-radius: 12px;
            overflow: hidden;
        }

        .entry-item {
            padding: 1.25rem;
            border-bottom: 1px solid var(--border);
            display: flex;
            gap: 1rem;
            align-items: start;
        }

        .entry-item:last-child {
            border-bottom: none;
        }

        .entry-avatar {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            flex-shrink: 0;
        }

        .entry-content {
            flex: 1;
        }

        .entry-message {
            margin-bottom: 0.5rem;
        }

        .entry-url {
            color: var(--accent);
            text-decoration: none;
            font-size: 0.875rem;
            word-break: break-all;
        }

        .entry-url:hover {
            text-decoration: underline;
        }

        .entry-meta {
            display: flex;
            gap: 1rem;
            margin-top: 0.5rem;
            font-size: 0.75rem;
            color: var(--text-secondary);
        }

        .empty-state {
            text-align: center;
            padding: 3rem;
            color: var(--text-secondary);
        }

        @media (max-width: 768px) {
            .header {
                flex-direction: column;
                gap: 1rem;
                align-items: flex-start;
            }

            .container {
                padding: 1rem;
            }

            .stats-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <div class="header">
        <div class="header-left">
            <div class="logo">🔗</div>
            <div class="header-title">Trail Admin</div>
        </div>
        <div class="header-right">
            <div class="user-info">
                <img src="<?= $avatarUrl ?>" alt="Avatar" class="avatar">
                <div class="user-email"><?= htmlspecialchars($session['email']) ?></div>
            </div>
            <a href="/admin/logout.php" class="logout-btn">Logout</a>
        </div>
    </div>

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
                <div class="stat-value" style="font-size: 1.5rem;">
                    <?= $session['is_admin'] ? '👑 Admin' : '👤 User' ?>
                </div>
            </div>
        </div>

        <h2 class="section-title">Recent Entries</h2>
        <div class="entries-list">
            <?php if (empty($recentEntries)): ?>
                <div class="empty-state">
                    <p>No entries yet. Start sharing links!</p>
                </div>
            <?php else: ?>
                <?php foreach ($recentEntries as $entry): ?>
                    <?php
                        $gravatarUrl = "https://www.gravatar.com/avatar/" . $entry['gravatar_hash'] . "?s=80&d=mp";
                        $createdAt = new DateTime($entry['created_at']);
                    ?>
                    <div class="entry-item">
                        <img src="<?= $gravatarUrl ?>" alt="Avatar" class="entry-avatar">
                        <div class="entry-content">
                            <div class="entry-message"><?= htmlspecialchars($entry['message']) ?></div>
                            <a href="<?= htmlspecialchars($entry['url']) ?>" target="_blank" class="entry-url">
                                <?= htmlspecialchars($entry['url']) ?>
                            </a>
                            <div class="entry-meta">
                                <span><?= htmlspecialchars($entry['name'] ?: $entry['email']) ?></span>
                                <span>•</span>
                                <span><?= $createdAt->format('M d, Y H:i') ?></span>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>

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

        .stat-card.iframely {
            grid-column: span 2;
        }

        .usage-bar-container {
            margin-top: 1rem;
            background: var(--bg-tertiary);
            border-radius: 8px;
            height: 8px;
            overflow: hidden;
        }

        .usage-bar {
            height: 100%;
            background: linear-gradient(90deg, var(--accent), var(--accent));
            transition: width 0.3s ease, background 0.3s ease;
        }

        .usage-bar.warning {
            background: linear-gradient(90deg, var(--warning), #fbbf24);
        }

        .usage-bar.danger {
            background: linear-gradient(90deg, var(--error), #fca5a5);
        }

        .usage-details {
            display: flex;
            justify-content: space-between;
            margin-top: 0.75rem;
            font-size: 0.875rem;
            color: var(--text-secondary);
        }

        .usage-status {
            display: inline-flex;
            align-items: center;
            gap: 0.375rem;
            padding: 0.25rem 0.625rem;
            border-radius: 6px;
            font-size: 0.75rem;
            font-weight: 600;
            margin-top: 0.5rem;
        }

        .usage-status.ok {
            background: rgba(16, 185, 129, 0.1);
            color: var(--success);
        }

        .usage-status.warning {
            background: rgba(245, 158, 11, 0.1);
            color: var(--warning);
        }

        .usage-status.danger {
            background: rgba(239, 68, 68, 0.1);
            color: var(--error);
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
            padding: 1rem 1.5rem;
            transition: background 0.2s, opacity 0.3s;
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

        /* Preview source badge styling */
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
            justify-content: space-between;
            gap: 1rem;
            color: var(--text-muted);
            font-size: 0.8125rem;
            padding-top: 0.75rem;
        }

        .entry-footer-left {
            flex: 1;
        }

        .share-button {
            background: transparent;
            border: none;
            color: var(--text-muted);
            font-size: 1rem;
            cursor: pointer;
            padding: 0.5rem;
            border-radius: 4px;
            transition: all 0.2s;
            display: flex;
            align-items: center;
            justify-content: center;
            min-width: 44px;
            min-height: 44px;
        }

        .share-button:hover {
            background: var(--bg-tertiary);
            color: var(--accent);
        }

        .share-button:active {
            transform: scale(0.95);
        }

        .timestamp {
            display: flex;
            align-items: center;
            gap: 0.375rem;
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

            .stat-card.iframely {
                grid-column: span 1;
            }

            .user-email {
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
            <div class="header-left">
                <div class="logo"><i class="fa-solid fa-link"></i></div>
                <div>
                    <div class="header-title">Trail Admin</div>
                    <div class="header-subtitle">Manage entries and users</div>
                </div>
            </div>
            <div class="header-right">
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
                <?php if ($tempSize > 0): ?>
                <button onclick="clearCache()" class="btn-clear-cache" style="margin-top: 0.5rem; padding: 0.25rem 0.75rem; font-size: 0.75rem; background: var(--bg-tertiary); border: 1px solid var(--border); border-radius: 4px; color: var(--text-secondary); cursor: pointer;">Clear Temp Files</button>
                <?php endif; ?>
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
    </script>
</body>
</html>

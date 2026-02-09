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
            <div class="filter-controls">
                <label for="source-filter" style="margin-right: 0.5rem; color: var(--text-secondary); font-size: 0.875rem;">Filter by source:</label>
                <select id="source-filter" class="source-filter-select">
                    <option value="">All Sources</option>
                    <option value="iframely">Iframely</option>
                    <option value="embed">Fallback</option>
                    <option value="medium">Medium</option>
                </select>
            </div>
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

    <script src="/js/config.js"></script>
    <script src="/js/snackbar.js"></script>
    <script src="/js/card-template.js"></script>
    <script src="/js/admin-dashboard.js"></script>
    <script>
        // Initialize admin dashboard with JWT token
        initAdminDashboard('<?= htmlspecialchars($jwtToken ?? '', ENT_QUOTES, 'UTF-8') ?>');
    </script>
</body>
</html>

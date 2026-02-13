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
use Trail\Models\Entry;

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
    $adminEmail = $config['production']['admin_email'] ?? 'admin@example.com';
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

    // Get duplicate entry stats
    $duplicateStats = ['total_duplicate_groups' => 0, 'total_extra_entries' => 0, 'text_duplicate_groups' => 0, 'url_duplicate_groups' => 0];
    try {
        $entryModel = new Entry($db);
        $duplicateStats = $entryModel->getDuplicateStats();
    } catch (Exception $e) {
        error_log("Duplicate stats error: " . $e->getMessage());
    }

    // Get broken link stats
    $brokenLinkStats = ['total_urls' => 0, 'checked' => 0, 'healthy' => 0, 'broken' => 0, 'dismissed' => 0, 'unchecked' => 0];
    try {
        $linkHealthModel = new \Trail\Models\LinkHealth($db);
        $brokenLinkStats = $linkHealthModel->getStats();
    } catch (Exception $e) {
        error_log("Broken link stats error: " . $e->getMessage());
    }

    // Get link health config for JavaScript
    $linkHealthConfig = [
        'batch_size' => $config['link_health']['batch_size'] ?? 50,
        'rate_limit_ms' => $config['link_health']['rate_limit_ms'] ?? 500
    ];

    // Get short link stats
    $shortLinkStats = ['total' => 0, 'pending' => 0, 'failed' => 0, 'oldest_failure' => null];
    try {
        $urlPreviewModel = new \Trail\Models\UrlPreview($db);
        $shortenerDomains = \Trail\Services\ShortLinkResolver::getShortenerDomains();
        $shortLinkStats = $urlPreviewModel->getShortLinkStats($shortenerDomains);
    } catch (Exception $e) {
        error_log("Short link stats error: " . $e->getMessage());
    }

    // Get short link resolver config for JavaScript
    $shortLinkConfig = [
        'batch_size' => $config['short_link_resolver']['batch_size'] ?? 1000,
        'rate_limit_ms' => $config['short_link_resolver']['rate_limit_ms'] ?? 500
    ];

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
                <button class="button secondary icon-only" onclick="window.location.href='/'" title="Home">
                    <i class="fa-solid fa-home"></i>
                </button>
                <button class="button secondary icon-only" onclick="window.location.href='/admin/users.php'" title="Users">
                    <i class="fa-solid fa-users"></i>
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
                <div style="display: flex; flex-direction: column; gap: 0.5rem; margin-top: 0.5rem;">
                    <?php if ($tempSize > 0): ?>
                    <button onclick="clearCache()" class="btn-clear-cache" style="width: 100%; padding: 0.25rem 0.75rem; font-size: 0.75rem; background: var(--bg-tertiary); border: 1px solid var(--border); border-radius: 4px; color: var(--text-secondary); cursor: pointer;">Clear Temp</button>
                    <?php endif; ?>
                    <button onclick="pruneImages()" class="btn-prune-images" style="width: 100%; padding: 0.25rem 0.75rem; font-size: 0.75rem; background: var(--bg-tertiary); border: 1px solid var(--border); border-radius: 4px; color: var(--text-secondary); cursor: pointer;">
                        <i class="fa-solid fa-broom"></i> Prune Images
                    </button>
                    <button onclick="pruneViews()" class="btn-prune-views" style="width: 100%; padding: 0.25rem 0.75rem; font-size: 0.75rem; background: var(--bg-tertiary); border: 1px solid var(--border); border-radius: 4px; color: var(--text-secondary); cursor: pointer;">
                        <i class="fa-solid fa-eye-slash"></i> Prune Views
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
            <?php if ($duplicateStats['total_duplicate_groups'] > 0): ?>
            <div class="stat-card duplicates">
                <div class="stat-label"><i class="fa-solid fa-clone"></i> Duplicate Entries</div>
                <div class="stat-value"><?= number_format($duplicateStats['total_duplicate_groups']) ?></div>
                <div class="stat-label" style="margin-top: 0.5rem; font-size: 0.875rem;">
                    <?= number_format($duplicateStats['total_extra_entries']) ?> extra entries to clean up
                </div>
                <div style="display: flex; gap: 0.5rem; margin-top: 0.5rem; font-size: 0.75rem; color: var(--text-muted); flex-wrap: wrap;">
                    <span><i class="fa-solid fa-font"></i> <?= $duplicateStats['text_duplicate_groups'] ?> text</span>
                    <span><i class="fa-solid fa-link"></i> <?= $duplicateStats['url_duplicate_groups'] ?> url</span>
                    <span><i class="fa-solid fa-file-lines"></i> <?= $duplicateStats['text_url_duplicate_groups'] ?> url-in-text</span>
                </div>
                <button onclick="switchView('duplicates')" style="margin-top: 0.5rem; padding: 0.25rem 0.75rem; font-size: 0.75rem; background: var(--bg-tertiary); border: 1px solid var(--border); border-radius: 4px; color: var(--text-secondary); cursor: pointer;">
                    <i class="fa-solid fa-magnifying-glass"></i> View Duplicates
                </button>
            </div>
            <?php endif; ?>
            <div class="stat-card broken-links">
                <div class="stat-label"><i class="fa-solid fa-link-slash"></i> Link Health</div>
                <div class="stat-value">
                    <?php if ($brokenLinkStats['broken'] > 0): ?>
                        <span style="color: var(--error);"><?= number_format($brokenLinkStats['broken']) ?></span>
                    <?php else: ?>
                        <span style="color: var(--success);">All Healthy</span>
                    <?php endif; ?>
                </div>
                <?php 
                    $checkProgress = $brokenLinkStats['total_urls'] > 0 
                        ? ($brokenLinkStats['checked'] / $brokenLinkStats['total_urls']) * 100 
                        : 0;
                ?>
                <div class="usage-bar-container" style="margin-top: 0.5rem;">
                    <div class="usage-bar <?= $checkProgress >= 100 ? '' : 'warning' ?>" 
                         style="width: <?= min(100, $checkProgress) ?>%"></div>
                </div>
                <div class="usage-details" style="margin-top: 0.25rem;">
                    <span><?= number_format($brokenLinkStats['checked']) ?> / <?= number_format($brokenLinkStats['total_urls']) ?> checked</span>
                    <span><?= number_format($checkProgress, 1) ?>%</span>
                </div>
                <?php if ($brokenLinkStats['unchecked'] > 0): ?>
                <div class="stat-label" style="margin-top: 0.25rem; font-size: 0.75rem; color: var(--text-muted);">
                    <?= number_format($brokenLinkStats['unchecked']) ?> unchecked
                </div>
                <?php endif; ?>
                <div style="display: flex; flex-direction: column; gap: 0.5rem; margin-top: 0.5rem;">
                    <button onclick="checkBrokenLinks()" class="btn-check-links" style="width: 100%; padding: 0.25rem 0.75rem; font-size: 0.75rem; background: var(--bg-tertiary); border: 1px solid var(--border); border-radius: 4px; color: var(--text-secondary); cursor: pointer;">
                        <i class="fa-solid fa-rotate"></i> Check Links
                    </button>
                    <?php 
                    // Count links with at least 1 failure (not just fully broken)
                    $failingLinksCount = 0;
                    try {
                        $stmt = $db->query("SELECT COUNT(*) as count FROM trail_link_health WHERE consecutive_failures >= 1 AND is_dismissed = 0");
                        $failingLinksCount = (int) $stmt->fetch()['count'];
                    } catch (Exception $e) {
                        // Ignore
                    }
                    ?>
                    <?php if ($failingLinksCount > 0): ?>
                    <button onclick="recheckBrokenLinks()" class="btn-recheck-broken" style="width: 100%; padding: 0.25rem 0.75rem; font-size: 0.75rem; background: var(--bg-tertiary); border: 1px solid var(--border); border-radius: 4px; color: var(--text-secondary); cursor: pointer;">
                        <i class="fa-solid fa-arrows-rotate"></i> Recheck Failing (<?= $failingLinksCount ?>)
                    </button>
                    <button onclick="switchView('broken-links')" style="width: 100%; padding: 0.25rem 0.75rem; font-size: 0.75rem; background: var(--bg-tertiary); border: 1px solid var(--border); border-radius: 4px; color: var(--text-secondary); cursor: pointer;">
                        <i class="fa-solid fa-magnifying-glass"></i> View Failing
                    </button>
                    <?php endif; ?>
                </div>
            </div>
            <?php if ($shortLinkStats['total'] > 0): ?>
            <div class="stat-card short-links">
                <div class="stat-label"><i class="fa-solid fa-compress"></i> Short Links</div>
                <div class="stat-value"><?= number_format($shortLinkStats['total']) ?></div>
                <div class="stat-label" style="margin-top: 0.5rem; font-size: 0.875rem;">
                    <?= number_format($shortLinkStats['pending']) ?> pending, <?= number_format($shortLinkStats['failed']) ?> failed
                </div>
                <?php if ($shortLinkStats['oldest_failure']): ?>
                <div class="stat-label" style="margin-top: 0.25rem; font-size: 0.75rem; color: var(--text-muted);">
                    Oldest failure: <?= date('M j, Y', strtotime($shortLinkStats['oldest_failure'])) ?>
                </div>
                <?php endif; ?>
                <div style="display: flex; flex-direction: column; gap: 0.5rem; margin-top: 0.5rem;">
                    <button onclick="resolveShortLinks()" class="btn-resolve-short-links" style="width: 100%; padding: 0.25rem 0.75rem; font-size: 0.75rem; background: var(--bg-tertiary); border: 1px solid var(--border); border-radius: 4px; color: var(--text-secondary); cursor: pointer;">
                        <i class="fa-solid fa-wand-magic-sparkles"></i> Resolve Short Links
                    </button>
                    <button onclick="switchView('short-links')" style="width: 100%; padding: 0.25rem 0.75rem; font-size: 0.75rem; background: var(--bg-tertiary); border: 1px solid var(--border); border-radius: 4px; color: var(--text-secondary); cursor: pointer;">
                        <i class="fa-solid fa-magnifying-glass"></i> View Short Links
                    </button>
                </div>
            </div>
            <?php endif; ?>
        </div>

        <div class="section-header">
            <h2 class="section-title" id="section-title">All Entries</h2>
            <div class="filter-controls">
                <div class="view-mode-toggle">
                    <button id="view-all" class="view-mode-btn active" onclick="switchView('all')">
                        <i class="fa-solid fa-list"></i>
                    </button>
                    <button id="view-duplicates" class="view-mode-btn" onclick="switchView('duplicates')">
                        <i class="fa-solid fa-clone"></i> Duplicates
                        <?php if ($duplicateStats['total_duplicate_groups'] > 0): ?>
                        <span class="dupe-badge"><?= $duplicateStats['total_duplicate_groups'] ?></span>
                        <?php endif; ?>
                    </button>
                    <button id="view-broken-links" class="view-mode-btn" onclick="switchView('broken-links')">
                        <i class="fa-solid fa-link-slash"></i> Broken Links
                        <?php if ($brokenLinkStats['broken'] > 0): ?>
                        <span class="dupe-badge"><?= $brokenLinkStats['broken'] ?></span>
                        <?php endif; ?>
                    </button>
                    <button id="view-tags" class="view-mode-btn" onclick="switchView('tags')">
                        <i class="fa-solid fa-tags"></i> Tags
                    </button>
                    <?php if ($shortLinkStats['total'] > 0): ?>
                    <button id="view-short-links" class="view-mode-btn" onclick="switchView('short-links')">
                        <i class="fa-solid fa-compress"></i> Short Links
                        <span class="dupe-badge"><?= $shortLinkStats['total'] ?></span>
                    </button>
                    <?php endif; ?>
                </div>
                <div id="entries-filters">
                    <label for="source-filter" style="margin-right: 0.5rem; color: var(--text-secondary); font-size: 0.875rem;">Source:</label>
                    <select id="source-filter" class="source-filter-select">
                        <option value="">All Sources</option>
                        <option value="iframely">Iframely</option>
                        <option value="embed">Fallback</option>
                        <option value="medium">Medium</option>
                    </select>
                    <label for="tag-filter" style="margin-left: 1rem; margin-right: 0.5rem; color: var(--text-secondary); font-size: 0.875rem;">Tag:</label>
                    <select id="tag-filter" class="source-filter-select">
                        <option value="">All Tags</option>
                        <!-- Tags will be populated via JavaScript -->
                    </select>
                </div>
                <div id="duplicates-filters" style="display: none;">
                    <label for="dupe-match-filter" style="margin-right: 0.5rem; color: var(--text-secondary); font-size: 0.875rem;">Match type:</label>
                    <select id="dupe-match-filter" class="source-filter-select">
                        <option value="all">All Duplicates</option>
                        <option value="text">Same Text</option>
                        <option value="url">Same URL Preview</option>
                        <option value="text_url">Same URL in Text</option>
                    </select>
                </div>
                <div id="broken-links-filters" style="display: none;">
                    <label for="error-type-filter" style="margin-right: 0.5rem; color: var(--text-secondary); font-size: 0.875rem;">Error type:</label>
                    <select id="error-type-filter" class="source-filter-select">
                        <option value="">All Types</option>
                        <option value="http_error">HTTP Error</option>
                        <option value="timeout">Timeout</option>
                        <option value="dns_error">DNS Error</option>
                        <option value="ssl_error">SSL Error</option>
                        <option value="connection_refused">Connection Refused</option>
                        <option value="redirect_loop">Redirect Loop</option>
                    </select>
                    <label style="margin-left: 1rem; color: var(--text-secondary); font-size: 0.875rem;">
                        <input type="checkbox" id="hide-dismissed" checked> Hide dismissed
                    </label>
                </div>
                <div id="tags-filters" style="display: none;">
                    <label for="tags-search" style="margin-right: 0.5rem; color: var(--text-secondary); font-size: 0.875rem;">Search tags:</label>
                    <input type="text" id="tags-search" class="source-filter-select" placeholder="Search..." style="width: 200px;">
                </div>
                <div id="short-links-filters" style="display: none;">
                    <label style="color: var(--text-secondary); font-size: 0.875rem;">
                        Short URLs (t.co, bit.ly, etc.) that need to be resolved to their final destinations
                    </label>
                </div>
            </div>
        </div>

        <!-- Bulk actions for duplicates view -->
        <div id="bulk-actions" class="bulk-actions" style="display: none;">
            <div class="bulk-actions-info">
                <i class="fa-solid fa-circle-info"></i>
                <span id="dupe-summary"></span>
            </div>
            <div class="bulk-actions-buttons">
                <button onclick="resolveAllDuplicates('oldest')" class="button primary small">
                    <i class="fa-solid fa-broom"></i> Resolve All (Keep Oldest)
                </button>
                <button onclick="resolveAllDuplicates('newest')" class="button secondary small">
                    <i class="fa-solid fa-broom"></i> Resolve All (Keep Newest)
                </button>
            </div>
        </div>

        <!-- Bulk actions for broken links view -->
        <div id="broken-links-bulk-actions" class="bulk-actions" style="display: none;">
            <div class="bulk-actions-info">
                <i class="fa-solid fa-check-square"></i>
                <span id="broken-links-selected-count">0 broken links selected</span>
            </div>
            <div class="bulk-actions-buttons">
                <button onclick="selectAllBrokenLinks()" class="button secondary small">
                    <i class="fa-solid fa-check-double"></i> Select Visible
                </button>
                <button onclick="selectAllFilteredBrokenLinks()" class="button secondary small">
                    <i class="fa-solid fa-list-check"></i> Select All (Filtered)
                </button>
                <button onclick="deselectAllBrokenLinks()" class="button secondary small">
                    <i class="fa-solid fa-times"></i> Deselect All
                </button>
                <button onclick="deleteSelectedBrokenLinkEntries()" class="button danger small btn-delete-selected-entries">
                    <i class="fa-solid fa-trash"></i> Delete Selected Entries
                </button>
            </div>
        </div>

        <div id="entries-container" class="entries-container">
            <!-- Entries will be loaded here -->
        </div>

        <div id="duplicates-container" class="entries-container" style="display: none;">
            <!-- Duplicate groups will be loaded here -->
        </div>

        <div id="broken-links-container" class="entries-container" style="display: none;">
            <!-- Broken links will be loaded here -->
        </div>

        <div id="tags-container" class="entries-container" style="display: none;">
            <!-- Tags will be loaded here -->
        </div>

        <div id="short-links-container" class="entries-container" style="display: none;">
            <!-- Short links will be loaded here -->
        </div>

        <div id="loading" class="loading" style="display: none;">
            <div class="spinner"></div>
            <p style="margin-top: 1rem;">Loading...</p>
        </div>

        <div id="empty-state" class="empty-state" style="display: none;">
            <div class="empty-state-icon"><i class="fa-solid fa-inbox"></i></div>
            <p>No entries yet. Start sharing links!</p>
        </div>

        <div id="empty-duplicates-state" class="empty-state" style="display: none;">
            <div class="empty-state-icon"><i class="fa-solid fa-check-circle"></i></div>
            <p>No duplicate entries found. Everything is clean!</p>
        </div>

        <div id="empty-broken-links-state" class="empty-state" style="display: none;">
            <div class="empty-state-icon"><i class="fa-solid fa-check-circle"></i></div>
            <p>No broken links found. All links are healthy!</p>
        </div>

        <div id="empty-tags-state" class="empty-state" style="display: none;">
            <div class="empty-state-icon"><i class="fa-solid fa-tags"></i></div>
            <p>No tags found.</p>
        </div>

        <div id="empty-short-links-state" class="empty-state" style="display: none;">
            <div class="empty-state-icon"><i class="fa-solid fa-check-circle"></i></div>
            <p>No short links found. All URLs are already resolved!</p>
        </div>
    </div>

    <script>
        // Link health config from backend
        window.LINK_HEALTH_CONFIG = <?= json_encode($linkHealthConfig) ?>;
        // Short link resolver config from backend
        window.SHORT_LINK_CONFIG = <?= json_encode($shortLinkConfig) ?>;
    </script>
    <script src="/assets/js/config.js"></script>
    <script src="/assets/js/snackbar.js"></script>
    <script src="/assets/js/card-template.js"></script>
    <script src="/assets/js/admin-dashboard.js"></script>
    <script src="/assets/js/admin-broken-links.js"></script>
    <script src="/assets/js/admin-tags.js"></script>
    <script src="/assets/js/admin-short-links.js"></script>
</body>
</html>

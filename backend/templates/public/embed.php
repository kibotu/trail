<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex, nofollow">
    <?php
    $displayName = $user['nickname'] ?? $user['name'] ?? 'User';
    $pageTitle = htmlspecialchars("@{$displayName} on Trail");
    ?>
    <title><?= $pageTitle ?></title>
    <link rel="icon" type="image/x-icon" href="/assets/favicon.ico">
    <link rel="stylesheet" href="/assets/fonts/fonts.css">
    <link rel="stylesheet" href="/assets/fontawesome/css/all.min.css">
    <link rel="stylesheet" href="/assets/css/main.css">
    <link rel="stylesheet" href="/assets/css/embed.css">
</head>
<body class="page-embed"
      data-nickname="<?= htmlspecialchars($nickname ?? '') ?>"
      data-theme="<?= htmlspecialchars($theme) ?>"
      data-show-header="<?= htmlspecialchars($showHeader) ?>"
      data-show-search="<?= htmlspecialchars($showSearch) ?>"
      data-limit="<?= (int) $limit ?>"
      data-base-url="<?= htmlspecialchars($baseUrl) ?>">

    <?php if ($showHeader === '1'): ?>
    <div class="profile-banner-container" id="profileBannerContainer" style="display: none;">
        <div class="profile-info-section">
            <div class="profile-avatar-container">
                <img class="profile-avatar" id="profileAvatar" src="" alt="Profile avatar">
            </div>
            <div class="profile-details">
                <h1 class="profile-name" id="profileName">Loading...</h1>
                <p class="profile-nickname" id="profileNickname">@<?= htmlspecialchars($nickname ?? '') ?></p>
                <p class="profile-bio" id="profileBio"></p>
                <div class="profile-stats" id="profileStats" style="display:none;">
                    <a class="profile-stat" id="statEntries" title="Entries">
                        <span class="profile-stat-value">0</span>
                        <span class="profile-stat-label">Entries</span>
                    </a>
                    <a class="profile-stat" id="statComments" title="Comments">
                        <span class="profile-stat-value">0</span>
                        <span class="profile-stat-label">Comments</span>
                    </a>
                    <span class="profile-stat" id="statTotalViews" title="Total Views">
                        <span class="profile-stat-value">0</span>
                        <span class="profile-stat-label">Views</span>
                    </span>
                    <span class="profile-stat" id="statTotalClaps" title="Total Claps">
                        <span class="profile-stat-value">0</span>
                        <span class="profile-stat-label">Claps</span>
                    </span>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <?php if ($showSearch === '1'): ?>
    <div class="search-section" id="searchSection"></div>
    <?php endif; ?>

    <div class="entries-container" id="entriesContainer"></div>
    <div class="loading" id="loading">
        <div class="loading-spinner"></div>
        <p>Loading entries...</p>
    </div>
    <div class="end-message" id="endMessage" style="display: none;">
        <p><i class="fa-solid fa-sparkles"></i> You've reached the end</p>
    </div>

    <div class="embed-footer">
        <a href="<?= htmlspecialchars($baseUrl) ?>/@<?= htmlspecialchars($nickname ?? '') ?>" target="_blank" rel="noopener noreferrer">
            <i class="fa-solid fa-link"></i> Powered by Trail
        </a>
    </div>

    <script src="/assets/dist/embed.bundle.js"></script>
</body>
</html>

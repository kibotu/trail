<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="Cache-Control" content="no-cache, no-store, must-revalidate">
    <meta http-equiv="Pragma" content="no-cache">
    <meta http-equiv="Expires" content="0">
    
    <?php
    // Generate meta tags from entry data
    $displayName = $entry['user_nickname'] ?? $entry['user_name'] ?? 'User';
    $entryText = $entry['text'] ?? '';
    $description = mb_strlen($entryText) > 200 
        ? mb_substr($entryText, 0, 200) . '...' 
        : $entryText;
    $pageTitle = htmlspecialchars("@{$displayName} on Trail" . ($entryText ? ": \"{$description}\"" : ''));
    $ogDescription = htmlspecialchars($description);
    $ogUrl = htmlspecialchars("{$baseUrl}/status/{$hashId}");
    
    // Determine OG image
    $ogImage = null;
    $twitterCard = 'summary';
    
    // Priority: preview image endpoint > entry images > app icon
    if (!empty($entry['text']) || !empty($entry['images'])) {
        $ogImage = htmlspecialchars("{$baseUrl}/api/preview-image/{$hashId}.png");
        $twitterCard = 'summary_large_image';
    } elseif (!empty($entry['images']) && is_array($entry['images'])) {
        $firstImage = $entry['images'][0];
        $ogImage = htmlspecialchars($baseUrl . $firstImage['url']);
        $twitterCard = 'summary_large_image';
    } else {
        $ogImage = htmlspecialchars("{$baseUrl}/assets/app-icon.webp");
    }
    ?>
    
    <!-- Open Graph meta tags -->
    <meta property="og:type" content="article">
    <meta property="og:site_name" content="Trail">
    <meta property="og:title" content="<?= $pageTitle ?>">
    <meta property="og:description" content="<?= $ogDescription ?>">
    <meta property="og:url" content="<?= $ogUrl ?>">
    <meta property="og:image" content="<?= $ogImage ?>">
    <meta property="og:image:width" content="1200">
    <meta property="og:image:height" content="630">
    
    <!-- Twitter Card meta tags -->
    <meta name="twitter:card" content="<?= htmlspecialchars($twitterCard) ?>">
    <meta name="twitter:title" content="<?= $pageTitle ?>">
    <meta name="twitter:description" content="<?= $ogDescription ?>">
    <meta name="twitter:image" content="<?= $ogImage ?>">
    
    <!-- JSON-LD structured data -->
    <script type="application/ld+json">
    {
      "@context": "https://schema.org",
      "@type": "SocialMediaPosting",
      "headline": <?= json_encode($description) ?>,
      "author": {
        "@type": "Person",
        "name": <?= json_encode($displayName) ?>
      },
      "datePublished": <?= json_encode($entry['created_at']) ?>,
      "url": <?= json_encode($ogUrl) ?>
    }
    </script>
    
    <title><?= $pageTitle ?></title>
    <link rel="icon" type="image/x-icon" href="/assets/favicon.ico">
    <link rel="stylesheet" href="/assets/fonts/fonts.css">
    <link rel="stylesheet" href="/assets/fontawesome/css/all.min.css">
    <link rel="stylesheet" href="/assets/css/main.css">
</head>
<body class="page-status"
      data-hash-id="<?= htmlspecialchars($hashId ?? '') ?>"
      data-is-logged-in="<?= json_encode($isLoggedIn ?? false) ?>"
      data-user-id="<?= json_encode($userId ?? null) ?>"
      data-is-admin="<?= json_encode($isAdmin ?? false) ?>">
    <div class="orb orb-1"></div>
    <div class="orb orb-2"></div>

    <header>
        <div class="header-content">
            <button class="back-button" onclick="window.history.back()" aria-label="Go back">
                <i class="fa-solid fa-arrow-left"></i>
            </button>
            <span class="logo">Trail</span>
        </div>
    </header>

    <main>
        <div id="entry-container" class="entry-container">
            <div class="loading">
                <i class="fa-solid fa-spinner fa-spin"></i>
                <p>Loading entry...</p>
            </div>
        </div>
    </main>

    <!-- Core JavaScript Modules -->
    <script src="/assets/js/config.js"></script>
    <script src="/assets/js/snackbar.js"></script>
    <script src="/assets/js/card-template.js"></script>
    <script src="/assets/js/ui-interactions.js"></script>
    <script src="/assets/js/entries-manager.js"></script>
    <script src="/assets/js/image-upload.js"></script>
    <script src="/assets/js/comments-manager.js"></script>
    <script src="/assets/js/meta-updater.js"></script>
    
    <!-- Page Initialization -->
    <script src="/assets/js/status-page.js"></script>
</body>
</html>

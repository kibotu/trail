<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="Cache-Control" content="no-cache, no-store, must-revalidate">
    <meta http-equiv="Pragma" content="no-cache">
    <meta http-equiv="Expires" content="0">
    
    <!-- Dynamic Open Graph meta tags will be set by JavaScript -->
    <meta property="og:type" content="article">
    <meta property="og:site_name" content="Trail">
    <meta name="twitter:card" content="summary">
    
    <title>Entry - Trail</title>
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

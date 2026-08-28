<?php
/**
 * Collections directory - server-rendered list of all collections.
 * Expects $collections (array) and $config from the including scope.
 */
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Collections on Trail">
    <title>Collections on Trail</title>
    <link rel="icon" type="image/x-icon" href="/assets/favicon.ico">
    <link rel="stylesheet" href="/assets/fonts/fonts.css">
    <link rel="stylesheet" href="/assets/fontawesome/css/fontawesome.min.css">
    <link rel="stylesheet" href="/assets/fontawesome/css/solid.min.css">
    <link rel="stylesheet" href="/assets/fontawesome/css/regular.min.css">
    <link rel="stylesheet" href="/assets/dist/main.bundle.css">
</head>
<body class="page-collections">
    <div class="orb orb-1"></div>
    <div class="orb orb-2"></div>

    <header>
        <div class="header-content">
            <a href="/" class="logo">
                <i class="fa-solid fa-link"></i>
                <span>Trail</span>
            </a>
            <div class="header-actions">
                <a href="/api" class="nav-link" aria-label="API Documentation">
                    <i class="fa-solid fa-book"></i>
                </a>
                <a href="/admin/login.php" class="login-button">
                    <i class="fa-solid fa-lock"></i>
                    <span>Login</span>
                </a>
            </div>
        </div>
    </header>

    <main>
        <h1 style="font-size: 1.5rem; margin-bottom: 1rem;">Collections</h1>
        <?php if (empty($collections)): ?>
            <p style="color: var(--text-muted);">No collections yet.</p>
        <?php endif; ?>

        <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(280px, 1fr)); gap: 1rem;">
            <?php foreach ($collections as $collection): ?>
                <a href="/collection/<?= htmlspecialchars($collection['slug'], ENT_QUOTES) ?>"
                   style="display: flex; gap: 0.75rem; align-items: center; padding: 0.75rem 1rem; background: var(--bg-secondary, #1e293b); border: 1px solid var(--border, rgba(255,255,255,0.1)); border-radius: 8px; text-decoration: none; color: var(--text-primary, #f8fafc);">
                    <img src="<?= htmlspecialchars($collection['avatar_url'] ?? '/assets/app-icon.webp', ENT_QUOTES) ?>"
                         alt="<?= htmlspecialchars($collection['name'], ENT_QUOTES) ?>"
                         class="avatar" width="48" height="48" loading="lazy"
                         style="width: 48px; height: 48px; border-radius: 50%;">
                    <div>
                        <div style="font-weight: 600;"><?= htmlspecialchars($collection['name'], ENT_QUOTES) ?></div>
                        <div style="font-size: 0.8rem; color: var(--text-muted);">/collection/<?= htmlspecialchars($collection['slug'], ENT_QUOTES) ?></div>
                        <div style="font-size: 0.75rem; color: var(--text-secondary); margin-top: 0.25rem;">
                            <?= (int) $collection['entry_count'] ?> entries &middot; <?= (int) $collection['view_count'] ?> views
                        </div>
                    </div>
                </a>
            <?php endforeach; ?>
        </div>
    </main>

    <footer class="site-footer">
        <div class="site-footer-links">
            <a href="/data-privacy/">Data Privacy</a>
            <a href="/terms-and-conditions/">Terms &amp; Conditions</a>
        </div>
    </footer>
</body>
</html>

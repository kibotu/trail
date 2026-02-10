<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Notifications - Trail</title>
    <link rel="icon" type="image/x-icon" href="/assets/favicon.ico">
    <link rel="stylesheet" href="/assets/fonts/fonts.css">
    <link rel="stylesheet" href="/assets/css/main.css">
    <link rel="stylesheet" href="/assets/css/notifications.css">
    <link rel="stylesheet" href="/assets/fontawesome/css/all.min.css">
</head>
<body class="page-notifications">
    <div class="orb orb-1"></div>
    <div class="orb orb-2"></div>

    <header>
        <div class="header-content">
            <a href="javascript:history.back()" class="back-button" aria-label="Go back">
                <i class="fa-solid fa-arrow-left"></i>
            </a>
            <a href="/" class="logo">
                <i class="fa-solid fa-link"></i>
                <span>Trail</span>
            </a>
            <div class="header-actions">
                <a href="/profile" class="nav-link" aria-label="Profile">
                    <i class="fa-solid fa-user"></i>
                </a>
            </div>
        </div>
    </header>

    <div class="notifications-page">
        <div class="notifications-header">
            <h1>Notifications</h1>
            <?php if ($data['unread_count'] > 0): ?>
                <button onclick="markAllAsRead()" class="btn-secondary">
                    Mark all as read (<?= $data['unread_count'] ?>)
                </button>
            <?php endif; ?>
        </div>
        
        <?php if (empty($data['notifications'])): ?>
            <div class="empty-state">
                <i class="fas fa-bell empty-icon"></i>
                <p>No notifications yet</p>
                <p class="empty-subtitle">When someone mentions you or interacts with your posts, you'll see it here.</p>
            </div>
        <?php else: ?>
            <?php foreach ($data['notifications'] as $dateLabel => $items): ?>
                <div class="notification-group">
                    <h2 class="date-header"><?= htmlspecialchars($dateLabel) ?></h2>
                    <?php foreach ($items as $notification): ?>
                        <div class="notification-item <?= $notification['is_read'] ? '' : 'unread' ?>"
                             data-id="<?= $notification['id'] ?>"
                             onclick="handleNotificationClick(<?= $notification['id'] ?>, '<?= htmlspecialchars($notification['link'], ENT_QUOTES) ?>')">
                            
                            <?php if (isset($notification['actors'])): ?>
                                <!-- Grouped clap notification with multiple avatars -->
                                <div class="notification-avatars">
                                    <?php foreach (array_slice($notification['actors'], 0, 3) as $actor): ?>
                                        <img src="<?= htmlspecialchars($actor['avatar_url']) ?>" 
                                             alt="<?= htmlspecialchars($actor['name']) ?>"
                                             class="avatar-stacked">
                                    <?php endforeach; ?>
                                </div>
                                
                                <div class="notification-content">
                                    <p class="notification-text">
                                        <?= htmlspecialchars($notification['action_text']) ?>
                                        <?php if ($notification['clap_count'] > 1): ?>
                                            <span class="clap-count-badge"><?= $notification['clap_count'] ?></span>
                                        <?php endif; ?>
                                    </p>
                                    
                                    <?php if ($notification['preview_text']): ?>
                                        <p class="notification-preview">
                                            "<?= htmlspecialchars($notification['preview_text']) ?>"
                                        </p>
                                    <?php endif; ?>
                                    
                                    <span class="notification-time"><?= htmlspecialchars($notification['relative_time']) ?></span>
                                </div>
                            <?php else: ?>
                                <!-- Regular notification -->
                                <img src="<?= htmlspecialchars($notification['actor_avatar_url']) ?>" 
                                     alt="<?= htmlspecialchars($notification['actor_display_name']) ?>"
                                     class="avatar">
                                
                                <div class="notification-content">
                                    <p class="notification-text">
                                        <strong><?= htmlspecialchars($notification['actor_display_name']) ?></strong>
                                        <?= htmlspecialchars($notification['action_text']) ?>
                                    </p>
                                    
                                    <?php if ($notification['preview_text']): ?>
                                        <p class="notification-preview">
                                            "<?= htmlspecialchars($notification['preview_text']) ?>"
                                        </p>
                                    <?php endif; ?>
                                    
                                    <span class="notification-time"><?= htmlspecialchars($notification['relative_time']) ?></span>
                                </div>
                            <?php endif; ?>
                            
                            <?php if (!$notification['is_read']): ?>
                                <span class="unread-dot"></span>
                            <?php endif; ?>
                            
                            <button class="delete-btn" 
                                    onclick="deleteNotification(event, <?= $notification['id'] ?>)"
                                    title="Delete notification">
                                <i class="fas fa-trash"></i>
                            </button>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
    
    <script src="/js/notifications.js"></script>
</body>
</html>

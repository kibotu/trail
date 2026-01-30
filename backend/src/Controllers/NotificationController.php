<?php

declare(strict_types=1);

namespace Trail\Controllers;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Trail\Database\Database;
use Trail\Models\Notification;
use Trail\Models\NotificationPreference;
use Trail\Config\Config;
use Trail\Services\HashIdService;

class NotificationController
{
    /**
     * Render notifications page
     */
    public static function page(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $userId = $request->getAttribute('user_id');
        
        $config = Config::load(__DIR__ . '/../../secrets.yml');
        $db = Database::getInstance($config);
        $notificationModel = new Notification($db);
        
        // Get notifications with clap grouping
        $notifications = $notificationModel->getByUserGrouped($userId, 50);
        $unreadCount = $notificationModel->getUnreadCount($userId);
        
        // Format notifications for display
        $formattedNotifications = self::formatNotifications($notifications, $config);
        
        // Group by date
        $groupedNotifications = self::groupNotificationsByDate($formattedNotifications);
        
        // Render template
        ob_start();
        $data = [
            'notifications' => $groupedNotifications,
            'unread_count' => $unreadCount
        ];
        require __DIR__ . '/../../templates/public/notifications.php';
        $html = ob_get_clean();
        
        $response->getBody()->write($html);
        return $response->withHeader('Content-Type', 'text/html');
    }

    /**
     * Render preferences page
     */
    public static function preferencesPage(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $userId = $request->getAttribute('user_id');
        
        $config = Config::load(__DIR__ . '/../../secrets.yml');
        $db = Database::getInstance($config);
        $prefsModel = new NotificationPreference($db);
        
        $preferences = $prefsModel->get($userId);
        
        // Render template
        ob_start();
        $data = ['preferences' => $preferences];
        require __DIR__ . '/../../templates/public/notification_preferences.php';
        $html = ob_get_clean();
        
        $response->getBody()->write($html);
        return $response->withHeader('Content-Type', 'text/html');
    }

    /**
     * API: Get notifications (JSON)
     */
    public static function list(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $userId = $request->getAttribute('user_id');
        $queryParams = $request->getQueryParams();
        
        $limit = min(50, max(1, (int) ($queryParams['limit'] ?? 20)));
        $before = $queryParams['before'] ?? null;
        
        $config = Config::load(__DIR__ . '/../../secrets.yml');
        $db = Database::getInstance($config);
        $notificationModel = new Notification($db);
        
        $notifications = $notificationModel->getByUserGrouped($userId, $limit, $before);
        $unreadCount = $notificationModel->getUnreadCount($userId);
        $hasMore = count($notifications) === $limit;
        
        // Format notifications
        $formattedNotifications = self::formatNotifications($notifications, $config);
        
        $response->getBody()->write(json_encode([
            'notifications' => $formattedNotifications,
            'unread_count' => $unreadCount,
            'has_more' => $hasMore
        ]));
        
        return $response->withHeader('Content-Type', 'application/json');
    }

    /**
     * API: Mark notification as read
     */
    public static function markRead(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $userId = $request->getAttribute('user_id');
        $notificationId = (int) ($args['id'] ?? 0);
        
        if ($notificationId <= 0) {
            $response->getBody()->write(json_encode(['error' => 'Invalid notification ID']));
            return $response->withStatus(400)->withHeader('Content-Type', 'application/json');
        }
        
        $config = Config::load(__DIR__ . '/../../secrets.yml');
        $db = Database::getInstance($config);
        $notificationModel = new Notification($db);
        
        // Check if user owns notification
        if (!$notificationModel->canAccess($notificationId, $userId)) {
            $response->getBody()->write(json_encode(['error' => 'Notification not found']));
            return $response->withStatus(404)->withHeader('Content-Type', 'application/json');
        }
        
        $success = $notificationModel->markAsRead($notificationId, $userId);
        
        $response->getBody()->write(json_encode(['success' => $success]));
        return $response->withHeader('Content-Type', 'application/json');
    }

    /**
     * API: Mark all as read
     */
    public static function markAllRead(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $userId = $request->getAttribute('user_id');
        
        $config = Config::load(__DIR__ . '/../../secrets.yml');
        $db = Database::getInstance($config);
        $notificationModel = new Notification($db);
        
        $success = $notificationModel->markAllAsRead($userId);
        
        $response->getBody()->write(json_encode(['success' => $success]));
        return $response->withHeader('Content-Type', 'application/json');
    }

    /**
     * API: Delete notification
     */
    public static function delete(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $userId = $request->getAttribute('user_id');
        $notificationId = (int) ($args['id'] ?? 0);
        
        if ($notificationId <= 0) {
            $response->getBody()->write(json_encode(['error' => 'Invalid notification ID']));
            return $response->withStatus(400)->withHeader('Content-Type', 'application/json');
        }
        
        $config = Config::load(__DIR__ . '/../../secrets.yml');
        $db = Database::getInstance($config);
        $notificationModel = new Notification($db);
        
        // Check if user owns notification
        if (!$notificationModel->canAccess($notificationId, $userId)) {
            $response->getBody()->write(json_encode(['error' => 'Notification not found']));
            return $response->withStatus(404)->withHeader('Content-Type', 'application/json');
        }
        
        $success = $notificationModel->delete($notificationId, $userId);
        
        $response->getBody()->write(json_encode(['success' => $success]));
        return $response->withHeader('Content-Type', 'application/json');
    }

    /**
     * API: Get preferences (JSON)
     */
    public static function getPreferences(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $userId = $request->getAttribute('user_id');
        
        $config = Config::load(__DIR__ . '/../../secrets.yml');
        $db = Database::getInstance($config);
        $prefsModel = new NotificationPreference($db);
        
        $preferences = $prefsModel->get($userId);
        
        $response->getBody()->write(json_encode($preferences));
        return $response->withHeader('Content-Type', 'application/json');
    }

    /**
     * API: Update preferences
     */
    public static function updatePreferences(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $userId = $request->getAttribute('user_id');
        $data = json_decode((string) $request->getBody(), true);
        
        if (!is_array($data)) {
            $response->getBody()->write(json_encode(['error' => 'Invalid request data']));
            return $response->withStatus(400)->withHeader('Content-Type', 'application/json');
        }
        
        $config = Config::load(__DIR__ . '/../../secrets.yml');
        $db = Database::getInstance($config);
        $prefsModel = new NotificationPreference($db);
        
        $success = $prefsModel->update($userId, $data);
        
        $response->getBody()->write(json_encode(['success' => $success]));
        return $response->withHeader('Content-Type', 'application/json');
    }

    /**
     * Format notifications for display
     */
    private static function formatNotifications(array $notifications, array $config): array
    {
        $formatted = [];
        
        foreach ($notifications as $notification) {
            $type = $notification['type'] ?? 'unknown';
            
            // Check if this is a grouped clap notification
            if (isset($notification['actors']) && ($type === 'clap_entry' || $type === 'clap_comment')) {
                $formatted[] = self::formatGroupedClap($notification, $config);
            } else {
                // Regular notification
                $actorDisplayName = $notification['actor_nickname'] ?? $notification['actor_name'] ?? 'Unknown User';
                $actorAvatarUrl = self::getAvatarUrl($notification);
                
                // Generate action text based on type
                $actionText = self::getActionText($type, $actorDisplayName);
                
                // Generate link to entry/comment
                $link = self::getNotificationLink($notification, $config);
                
                // Get preview text
                $previewText = self::getPreviewText($notification);
                
                // Format relative time
                $createdAt = $notification['created_at'] ?? date('Y-m-d H:i:s');
                $relativeTime = self::getRelativeTime($createdAt);
                
                $formatted[] = [
                    'id' => $notification['id'] ?? 0,
                    'type' => $type,
                    'actor_display_name' => $actorDisplayName,
                    'actor_avatar_url' => $actorAvatarUrl,
                    'action_text' => $actionText,
                    'preview_text' => $previewText,
                    'link' => $link,
                    'is_read' => (bool) ($notification['is_read'] ?? false),
                    'relative_time' => $relativeTime,
                    'created_at' => $createdAt
                ];
            }
        }
        
        return $formatted;
    }

    /**
     * Format grouped clap notification
     */
    private static function formatGroupedClap(array $notification, array $config): array
    {
        $actors = $notification['actors'] ?? [];
        $count = count($actors);
        $type = $notification['type'];
        
        // Generate action text
        if ($count === 1) {
            $actionText = $actors[0]['name'] . ' clapped for your ' . ($type === 'clap_entry' ? 'post' : 'comment');
        } elseif ($count === 2) {
            $actionText = $actors[0]['name'] . ' and ' . $actors[1]['name'] . ' clapped for your ' . ($type === 'clap_entry' ? 'post' : 'comment');
        } else {
            $actionText = $actors[0]['name'] . ' and ' . ($count - 1) . ' others clapped for your ' . ($type === 'clap_entry' ? 'post' : 'comment');
        }
        
        // Generate link
        $link = self::getNotificationLink($notification, $config);
        
        // Get preview text
        $previewText = self::getPreviewText($notification);
        
        // Format relative time
        $createdAt = $notification['created_at'] ?? date('Y-m-d H:i:s');
        $relativeTime = self::getRelativeTime($createdAt);
        
        return [
            'id' => $notification['id'] ?? 0,
            'type' => $type,
            'actors' => array_slice($actors, 0, 3), // Show up to 3 avatars
            'clap_count' => $count,
            'action_text' => $actionText,
            'preview_text' => $previewText,
            'link' => $link,
            'is_read' => (bool) ($notification['is_read'] ?? false),
            'relative_time' => $relativeTime,
            'created_at' => $createdAt
        ];
    }

    /**
     * Get action text based on notification type
     */
    private static function getActionText(string $type, string $actorName): string
    {
        switch ($type) {
            case 'mention_entry':
                return "mentioned you in a post";
            case 'mention_comment':
                return "mentioned you in a comment";
            case 'comment_on_entry':
                return "commented on your post";
            case 'clap_entry':
                return "clapped for your post";
            case 'clap_comment':
                return "clapped for your comment";
            default:
                return "interacted with your content";
        }
    }

    /**
     * Get notification link
     */
    private static function getNotificationLink(array $notification, array $config): string
    {
        $baseUrl = $config['app']['base_url'] ?? '';
        
        if (!empty($notification['entry_id'])) {
            // Generate hash_id dynamically (not stored in database)
            $hashId = null;
            $hashSalt = $config['app']['entry_hash_salt'] ?? 'default_entry_salt_change_me';
            
            try {
                $hashIdService = new HashIdService($hashSalt);
                $hashId = $hashIdService->encode((int) $notification['entry_id']);
            } catch (\Throwable $e) {
                error_log("Failed to encode entry ID {$notification['entry_id']} for notification: " . $e->getMessage());
            }
            
            // Fall back to numeric ID if hash generation fails
            $identifier = $hashId ?? $notification['entry_id'];
            
            return $baseUrl . '/status/' . $identifier;
        }
        
        return $baseUrl;
    }

    /**
     * Get preview text
     */
    private static function getPreviewText(array $notification): ?string
    {
        $type = $notification['type'] ?? '';
        
        if ($type === 'mention_comment' || $type === 'comment_on_entry') {
            $commentText = $notification['comment_text'] ?? null;
            return $commentText ? substr($commentText, 0, 100) : null;
        }
        
        $entryText = $notification['entry_text'] ?? null;
        return $entryText ? substr($entryText, 0, 100) : null;
    }

    /**
     * Get avatar URL
     */
    private static function getAvatarUrl(array $notification): string
    {
        if (!empty($notification['actor_photo_url'])) {
            return $notification['actor_photo_url'];
        }
        
        $hash = $notification['actor_gravatar_hash'] ?? md5(strtolower(trim($notification['actor_email'] ?? '')));
        return "https://www.gravatar.com/avatar/{$hash}?d=identicon&s=200";
    }

    /**
     * Get relative time
     */
    private static function getRelativeTime(string $timestamp): string
    {
        $time = strtotime($timestamp);
        $diff = time() - $time;
        
        if ($diff < 60) {
            return 'just now';
        } elseif ($diff < 3600) {
            $minutes = floor($diff / 60);
            return $minutes . 'm ago';
        } elseif ($diff < 86400) {
            $hours = floor($diff / 3600);
            return $hours . 'h ago';
        } elseif ($diff < 604800) {
            $days = floor($diff / 86400);
            return $days . 'd ago';
        } else {
            return date('M j, Y', $time);
        }
    }

    /**
     * Group notifications by date
     */
    private static function groupNotificationsByDate(array $notifications): array
    {
        $grouped = [
            'Today' => [],
            'Yesterday' => [],
            'This Week' => [],
            'Older' => []
        ];
        
        $today = date('Y-m-d');
        $yesterday = date('Y-m-d', strtotime('-1 day'));
        $weekAgo = strtotime('-7 days');
        
        foreach ($notifications as $notification) {
            $createdAt = $notification['created_at'] ?? date('Y-m-d H:i:s');
            $notificationDate = date('Y-m-d', strtotime($createdAt));
            $notificationTime = strtotime($createdAt);
            
            if ($notificationDate === $today) {
                $grouped['Today'][] = $notification;
            } elseif ($notificationDate === $yesterday) {
                $grouped['Yesterday'][] = $notification;
            } elseif ($notificationTime >= $weekAgo) {
                $grouped['This Week'][] = $notification;
            } else {
                $grouped['Older'][] = $notification;
            }
        }
        
        // Remove empty groups
        return array_filter($grouped, function($group) {
            return !empty($group);
        });
    }
}

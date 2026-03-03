<?php

declare(strict_types=1);

namespace Trail\Controllers;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Trail\Database\Database;
use Trail\Models\User;
use Trail\Models\Entry;
use Trail\Models\Comment;
use Trail\Config\Config;
use Trail\Services\HashIdService;
use Trail\Services\EmailService;

class ProfileController
{
    /**
     * Get current user's profile
     */
    public static function getProfile(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $userId = $request->getAttribute('user_id');
        
        if (!$userId) {
            $response->getBody()->write(json_encode(['error' => 'Unauthorized']));
            return $response->withStatus(401)->withHeader('Content-Type', 'application/json');
        }

        $config = Config::load(__DIR__ . '/../../secrets.yml');
        $db = Database::getInstance($config);
        $userModel = new User($db);
        
        $user = $userModel->findByIdWithImages($userId);
        
        if (!$user) {
            $response->getBody()->write(json_encode(['error' => 'User not found']));
            return $response->withStatus(404)->withHeader('Content-Type', 'application/json');
        }

        // Get or generate nickname if not set
        $nickname = $user['nickname'];
        if (empty($nickname)) {
            $salt = $config['app']['nickname_salt'] ?? 'default_salt_change_me';
            $nickname = $userModel->getOrGenerateNickname($userId, $user['google_id'], $salt);
        }

        // Gather profile statistics
        $stats = $userModel->getProfileStats($userId);

        // Add hash_id to top entries
        $hashSalt = $config['app']['entry_hash_salt'] ?? 'default_entry_salt_change_me';
        $hashIdService = new HashIdService($hashSalt);
        
        if (!empty($stats['top_entries_by_claps'])) {
            foreach ($stats['top_entries_by_claps'] as &$entry) {
                $entry['hash_id'] = $hashIdService->encode((int) $entry['id']);
            }
        }
        
        if (!empty($stats['top_entries_by_views'])) {
            foreach ($stats['top_entries_by_views'] as &$entry) {
                $entry['hash_id'] = $hashIdService->encode((int) $entry['id']);
            }
        }

        $profileData = [
            'id' => $user['id'],
            'email' => $user['email'],
            'name' => $user['name'],
            'nickname' => $nickname,
            'bio' => $user['bio'] ?? null,
            'photo_url' => $user['photo_url'],
            'gravatar_hash' => $user['gravatar_hash'],
            'profile_image_id' => $user['profile_image_id'],
            'profile_image_url' => $user['profile_image_url'] ?? null,
            'header_image_id' => $user['header_image_id'],
            'header_image_url' => $user['header_image_url'] ?? null,
            'is_admin' => (bool) $user['is_admin'],
            'created_at' => $user['created_at'],
            'updated_at' => $user['updated_at'],
            'stats' => $stats,
            'deletion_requested_at' => $user['deletion_requested_at'] ?? null,
        ];

        $response->getBody()->write(json_encode($profileData));
        return $response->withHeader('Content-Type', 'application/json');
    }

    /**
     * Update current user's profile (nickname and images)
     */
    public static function updateProfile(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $userId = $request->getAttribute('user_id');
        
        if (!$userId) {
            $response->getBody()->write(json_encode(['error' => 'Unauthorized']));
            return $response->withStatus(401)->withHeader('Content-Type', 'application/json');
        }

        $data = json_decode((string) $request->getBody(), true);
        $nickname = $data['nickname'] ?? null;
        $bio = $data['bio'] ?? null;
        $profileImageId = isset($data['profile_image_id']) ? (int) $data['profile_image_id'] : null;
        $headerImageId = isset($data['header_image_id']) ? (int) $data['header_image_id'] : null;

        if (empty($nickname)) {
            $response->getBody()->write(json_encode(['error' => 'Nickname is required']));
            return $response->withStatus(400)->withHeader('Content-Type', 'application/json');
        }

        // Validate bio length if provided
        if ($bio !== null && strlen($bio) > 160) {
            $response->getBody()->write(json_encode(['error' => 'Bio must be 160 characters or less']));
            return $response->withStatus(400)->withHeader('Content-Type', 'application/json');
        }

        // Validate nickname format
        if (!preg_match('/^[a-zA-Z0-9_-]{3,50}$/', $nickname)) {
            $response->getBody()->write(json_encode([
                'error' => 'Invalid nickname format. Use 3-50 characters (letters, numbers, underscore, hyphen only)'
            ]));
            return $response->withStatus(400)->withHeader('Content-Type', 'application/json');
        }

        $config = Config::load(__DIR__ . '/../../secrets.yml');
        $db = Database::getInstance($config);
        $userModel = new User($db);

        // Check if nickname is available
        if (!$userModel->isNicknameAvailable($nickname, $userId)) {
            $response->getBody()->write(json_encode(['error' => 'Nickname is already taken']));
            return $response->withStatus(409)->withHeader('Content-Type', 'application/json');
        }

        // Update nickname
        $success = $userModel->updateNickname($userId, $nickname);

        if (!$success) {
            $response->getBody()->write(json_encode(['error' => 'Failed to update nickname']));
            return $response->withStatus(500)->withHeader('Content-Type', 'application/json');
        }

        // Update bio if provided
        if ($bio !== null) {
            $userModel->updateBio($userId, $bio);
        }

        // Update profile image if provided
        if ($profileImageId !== null) {
            $userModel->updateProfileImage($userId, $profileImageId);
        }

        // Update header image if provided
        if ($headerImageId !== null) {
            $userModel->updateHeaderImage($userId, $headerImageId);
        }

        // Return updated profile
        $user = $userModel->findByIdWithImages($userId);
        $profileData = [
            'id' => $user['id'],
            'email' => $user['email'],
            'name' => $user['name'],
            'nickname' => $user['nickname'],
            'bio' => $user['bio'] ?? null,
            'photo_url' => $user['photo_url'],
            'gravatar_hash' => $user['gravatar_hash'],
            'profile_image_id' => $user['profile_image_id'],
            'profile_image_url' => $user['profile_image_url'] ?? null,
            'header_image_id' => $user['header_image_id'],
            'header_image_url' => $user['header_image_url'] ?? null,
            'is_admin' => (bool) $user['is_admin'],
            'created_at' => $user['created_at'],
            'updated_at' => $user['updated_at']
        ];

        $response->getBody()->write(json_encode($profileData));
        return $response->withHeader('Content-Type', 'application/json');
    }

    /**
     * Get public profile by nickname
     */
    public static function getPublicProfile(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $nickname = $args['nickname'] ?? null;

        if (empty($nickname)) {
            $response->getBody()->write(json_encode(['error' => 'Nickname is required']));
            return $response->withStatus(400)->withHeader('Content-Type', 'application/json');
        }

        $config = Config::load(__DIR__ . '/../../secrets.yml');
        $db = Database::getInstance($config);
        $userModel = new User($db);
        
        $user = $userModel->findByNickname($nickname);
        
        if (!$user || !empty($user['deletion_requested_at'])) {
            $response->getBody()->write(json_encode(['error' => 'User not found']));
            return $response->withStatus(404)->withHeader('Content-Type', 'application/json');
        }

        // Gather profile statistics
        $stats = $userModel->getProfileStats((int) $user['id']);

        // Add hash_id to top entries
        $hashSalt = $config['app']['entry_hash_salt'] ?? 'default_entry_salt_change_me';
        $hashIdService = new HashIdService($hashSalt);
        
        if (!empty($stats['top_entries_by_claps'])) {
            foreach ($stats['top_entries_by_claps'] as &$entry) {
                $entry['hash_id'] = $hashIdService->encode((int) $entry['id']);
            }
        }
        
        if (!empty($stats['top_entries_by_views'])) {
            foreach ($stats['top_entries_by_views'] as &$entry) {
                $entry['hash_id'] = $hashIdService->encode((int) $entry['id']);
            }
        }

        // Return only public profile data
        $profileData = [
            'id' => $user['id'],
            'nickname' => $user['nickname'],
            'name' => $user['name'],
            'bio' => $user['bio'] ?? null,
            'photo_url' => $user['photo_url'],
            'gravatar_hash' => $user['gravatar_hash'],
            'profile_image_url' => $user['profile_image_url'] ?? null,
            'header_image_url' => $user['header_image_url'] ?? null,
            'created_at' => $user['created_at'],
            'stats' => $stats,
        ];

        $response->getBody()->write(json_encode($profileData));
        return $response->withHeader('Content-Type', 'application/json');
    }

    /**
     * Request account deletion (soft-delete with 14-day grace period)
     */
    public static function requestDeletion(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $userId = $request->getAttribute('user_id');

        if (!$userId) {
            $response->getBody()->write(json_encode(['error' => 'Unauthorized']));
            return $response->withStatus(401)->withHeader('Content-Type', 'application/json');
        }

        $config = Config::load(__DIR__ . '/../../secrets.yml');
        $db = Database::getInstance($config);
        $userModel = new User($db);

        $user = $userModel->findById($userId);

        if (!$user) {
            $response->getBody()->write(json_encode(['error' => 'User not found']));
            return $response->withStatus(404)->withHeader('Content-Type', 'application/json');
        }

        if (!empty($user['is_admin'])) {
            $response->getBody()->write(json_encode(['error' => 'Admin accounts cannot be deleted via this endpoint. Please contact another admin.']));
            return $response->withStatus(403)->withHeader('Content-Type', 'application/json');
        }

        if (!empty($user['deletion_requested_at'])) {
            $response->getBody()->write(json_encode(['error' => 'Account deletion has already been requested']));
            return $response->withStatus(409)->withHeader('Content-Type', 'application/json');
        }

        $success = $userModel->requestDeletion($userId);

        if (!$success) {
            $response->getBody()->write(json_encode(['error' => 'Failed to process deletion request']));
            return $response->withStatus(500)->withHeader('Content-Type', 'application/json');
        }

        // Invalidate all sessions for this user
        $stmt = $db->prepare("DELETE FROM trail_sessions WHERE user_id = ?");
        $stmt->execute([$userId]);

        // Send notification emails
        $adminEmail = $config['production']['admin_email'] ?? '';
        $baseUrl = $config['app']['base_url'] ?? '';
        $contactEmail = 'contact@kibotu.net';

        if (!empty($adminEmail) && !empty($baseUrl)) {
            $emailService = new EmailService($adminEmail, $baseUrl);
            $emailService->sendDeletionRequestNotification($user);
            $emailService->sendDeletionConfirmation($user, $contactEmail);
        }

        $response->getBody()->write(json_encode([
            'success' => true,
            'message' => 'Your account deletion request has been received. Your content is now hidden from public view. Your account will be permanently deleted within 14 days. You can reverse this by contacting contact@kibotu.net.',
        ]));
        return $response->withHeader('Content-Type', 'application/json');
    }

    /**
     * Revert a pending account deletion request (self-service from blocker page)
     */
    public static function revertDeletion(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $userId = $request->getAttribute('user_id');

        if (!$userId) {
            $response->getBody()->write(json_encode(['error' => 'Unauthorized']));
            return $response->withStatus(401)->withHeader('Content-Type', 'application/json');
        }

        $config = Config::load(__DIR__ . '/../../secrets.yml');
        $db = Database::getInstance($config);
        $userModel = new User($db);

        $user = $userModel->findById($userId);

        if (!$user) {
            $response->getBody()->write(json_encode(['error' => 'User not found']));
            return $response->withStatus(404)->withHeader('Content-Type', 'application/json');
        }

        if (empty($user['deletion_requested_at'])) {
            $response->getBody()->write(json_encode(['error' => 'No pending deletion request']));
            return $response->withStatus(409)->withHeader('Content-Type', 'application/json');
        }

        $success = $userModel->revertDeletion($userId);

        if (!$success) {
            $response->getBody()->write(json_encode(['error' => 'Failed to restore account']));
            return $response->withStatus(500)->withHeader('Content-Type', 'application/json');
        }

        $nickname = $user['nickname'] ?? $user['name'] ?? 'Unknown';
        error_log("Account restored: User {$nickname} ({$user['email']}) reversed their deletion request");

        $response->getBody()->write(json_encode([
            'success' => true,
            'message' => 'Welcome back! Your account has been restored and your content is visible again.',
        ]));
        return $response->withHeader('Content-Type', 'application/json');
    }

    /**
     * Export all user data as a self-contained HTML file (GDPR Art. 20 data portability)
     */
    public static function exportData(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $userId = $request->getAttribute('user_id');

        if (!$userId) {
            $response->getBody()->write(json_encode(['error' => 'Unauthorized']));
            return $response->withStatus(401)->withHeader('Content-Type', 'application/json');
        }

        $config = Config::load(__DIR__ . '/../../secrets.yml');
        $db = Database::getInstance($config);
        $userModel = new User($db);
        $entryModel = new Entry($db);
        $commentModel = new Comment($db);

        $user = $userModel->findByIdWithImages($userId);

        if (!$user) {
            $response->getBody()->write(json_encode(['error' => 'User not found']));
            return $response->withStatus(404)->withHeader('Content-Type', 'application/json');
        }

        // Gather all user data
        $stats = $userModel->getProfileStats($userId);
        $entries = $entryModel->getByUser($userId, 10000);

        // Gather all comments by this user
        $stmt = $db->prepare(
            "SELECT c.*, e.text as entry_text
             FROM trail_comments c
             LEFT JOIN trail_entries e ON c.entry_id = e.id
             WHERE c.user_id = ?
             ORDER BY c.created_at DESC"
        );
        $stmt->execute([$userId]);
        $comments = $stmt->fetchAll();

        // Gather images
        $stmt = $db->prepare(
            "SELECT id, filename, width, height, file_size, mime_type, created_at
             FROM trail_images
             WHERE user_id = ?
             ORDER BY created_at DESC"
        );
        $stmt->execute([$userId]);
        $images = $stmt->fetchAll();

        // Gather notification preferences
        $stmt = $db->prepare("SELECT * FROM trail_notification_preferences WHERE user_id = ?");
        $stmt->execute([$userId]);
        $notifPrefs = $stmt->fetch();

        // Gather claps received
        $stmt = $db->prepare(
            "SELECT c.clap_count, c.created_at, e.text as entry_text
             FROM trail_claps c
             JOIN trail_entries e ON c.entry_id = e.id
             WHERE e.user_id = ?
             ORDER BY c.created_at DESC"
        );
        $stmt->execute([$userId]);
        $clapsReceived = $stmt->fetchAll();

        $baseUrl = htmlspecialchars($config['app']['base_url'] ?? '', ENT_QUOTES, 'UTF-8');
        $nickname = htmlspecialchars($user['nickname'] ?? '', ENT_QUOTES, 'UTF-8');
        $userName = htmlspecialchars($user['name'] ?? '', ENT_QUOTES, 'UTF-8');
        $userEmail = htmlspecialchars($user['email'] ?? '', ENT_QUOTES, 'UTF-8');
        $exportDate = date('F j, Y \a\t g:i A T');
        $memberSince = date('F j, Y', strtotime($user['created_at']));
        $bioHtml = htmlspecialchars($user['bio'] ?? 'No bio set.', ENT_QUOTES, 'UTF-8');

        // Build entries HTML
        $entriesHtml = '';
        if (!empty($entries)) {
            foreach ($entries as $entry) {
                $entryText = htmlspecialchars($entry['text'] ?? '', ENT_QUOTES, 'UTF-8');
                $entryDate = date('M j, Y g:i A', strtotime($entry['created_at']));
                $previewUrl = !empty($entry['preview_url']) ? htmlspecialchars($entry['preview_url'], ENT_QUOTES, 'UTF-8') : '';
                $previewTitle = !empty($entry['preview_title']) ? htmlspecialchars($entry['preview_title'], ENT_QUOTES, 'UTF-8') : '';

                $linkHtml = '';
                if ($previewUrl) {
                    $linkHtml = "<div class=\"entry-link\"><a href=\"{$previewUrl}\">{$previewTitle}</a></div>";
                }

                $entriesHtml .= "<div class=\"entry\"><div class=\"entry-date\">{$entryDate}</div><div class=\"entry-text\">{$entryText}</div>{$linkHtml}</div>";
            }
        } else {
            $entriesHtml = '<p class="empty">No entries.</p>';
        }

        // Build comments HTML
        $commentsHtml = '';
        if (!empty($comments)) {
            foreach ($comments as $comment) {
                $commentText = htmlspecialchars($comment['text'] ?? '', ENT_QUOTES, 'UTF-8');
                $commentDate = date('M j, Y g:i A', strtotime($comment['created_at']));
                $entryContext = !empty($comment['entry_text']) ? htmlspecialchars(mb_substr($comment['entry_text'], 0, 100), ENT_QUOTES, 'UTF-8') : 'Unknown entry';

                $commentsHtml .= "<div class=\"comment\"><div class=\"comment-date\">{$commentDate}</div><div class=\"comment-context\">On: \"{$entryContext}...\"</div><div class=\"comment-text\">{$commentText}</div></div>";
            }
        } else {
            $commentsHtml = '<p class="empty">No comments.</p>';
        }

        // Build images HTML
        $imagesHtml = '';
        if (!empty($images)) {
            foreach ($images as $image) {
                $imgFilename = htmlspecialchars($image['filename'] ?? '', ENT_QUOTES, 'UTF-8');
                $imgDate = date('M j, Y', strtotime($image['created_at']));
                $imgSize = number_format(((int)($image['file_size'] ?? 0)) / 1024, 1);
                $imgDims = ($image['width'] ?? '?') . ' x ' . ($image['height'] ?? '?');
                $imgUrl = "{$baseUrl}/uploads/images/{$userId}/{$imgFilename}";

                $imagesHtml .= "<div class=\"image-item\"><a href=\"{$imgUrl}\">{$imgFilename}</a> &mdash; {$imgDims}px, {$imgSize} KB <span class=\"meta\">({$imgDate})</span></div>";
            }
        } else {
            $imagesHtml = '<p class="empty">No images uploaded.</p>';
        }

        // Build claps HTML
        $clapsHtml = '';
        $totalClaps = 0;
        if (!empty($clapsReceived)) {
            foreach ($clapsReceived as $clap) {
                $clapCount = (int) ($clap['clap_count'] ?? 0);
                $totalClaps += $clapCount;
                $clapEntry = htmlspecialchars(mb_substr($clap['entry_text'] ?? '', 0, 80), ENT_QUOTES, 'UTF-8');
                $clapDate = date('M j, Y', strtotime($clap['created_at']));
                $clapsHtml .= "<div class=\"clap-item\">{$clapCount} clap(s) on \"{$clapEntry}...\" <span class=\"meta\">({$clapDate})</span></div>";
            }
        } else {
            $clapsHtml = '<p class="empty">No claps received.</p>';
        }

        // Notification preferences
        $notifHtml = '<p class="empty">Default notification settings.</p>';
        if ($notifPrefs) {
            $notifHtml = '<ul>';
            $notifHtml .= '<li>Email on mention: ' . (($notifPrefs['email_on_mention'] ?? 1) ? 'Yes' : 'No') . '</li>';
            $notifHtml .= '<li>Email on comment: ' . (($notifPrefs['email_on_comment'] ?? 1) ? 'Yes' : 'No') . '</li>';
            $notifHtml .= '<li>Email on clap: ' . (($notifPrefs['email_on_clap'] ?? 0) ? 'Yes' : 'No') . '</li>';
            $notifHtml .= '<li>Email digest: ' . htmlspecialchars($notifPrefs['email_digest_frequency'] ?? 'instant', ENT_QUOTES, 'UTF-8') . '</li>';
            $notifHtml .= '</ul>';
        }

        $entryCount = count($entries);
        $commentCount = count($comments);
        $imageCount = count($images);

        $html = <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Trail Data Export - {$nickname}</title>
    <style>
        :root { --bg: #ffffff; --text: #1a1a1a; --muted: #555; --border: #e0e0e0; --accent: #4f46e5; --section-bg: #f9fafb; --card-bg: #fff; }
        @media (prefers-color-scheme: dark) {
            :root { --bg: #111; --text: #e0e0e0; --muted: #999; --border: #333; --accent: #818cf8; --section-bg: #1a1a1a; --card-bg: #222; }
        }
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif; line-height: 1.7; color: var(--text); background: var(--bg); max-width: 800px; margin: 0 auto; padding: 2rem 1.5rem 4rem; }
        h1 { font-size: 1.8rem; margin-bottom: 0.25rem; }
        h2 { font-size: 1.3rem; margin: 2.5rem 0 1rem; padding-bottom: 0.3rem; border-bottom: 2px solid var(--accent); color: var(--accent); }
        h3 { font-size: 1.05rem; margin: 1.25rem 0 0.5rem; }
        .subtitle { color: var(--muted); font-size: 0.95rem; margin-bottom: 2rem; }
        .profile-card { background: var(--section-bg); padding: 1.5rem; border-radius: 12px; margin: 1.5rem 0; }
        .profile-card dt { font-weight: 600; color: var(--muted); font-size: 0.85rem; text-transform: uppercase; letter-spacing: 0.5px; margin-top: 0.75rem; }
        .profile-card dd { margin-left: 0; margin-bottom: 0.25rem; }
        .stats-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(120px, 1fr)); gap: 1rem; margin: 1rem 0; }
        .stat-box { background: var(--section-bg); padding: 1rem; border-radius: 8px; text-align: center; }
        .stat-box .value { font-size: 1.5rem; font-weight: 700; color: var(--accent); }
        .stat-box .label { font-size: 0.8rem; color: var(--muted); text-transform: uppercase; letter-spacing: 0.5px; }
        .entry, .comment { background: var(--section-bg); padding: 1rem 1.25rem; margin: 0.75rem 0; border-radius: 8px; border-left: 3px solid var(--accent); }
        .entry-date, .comment-date { font-size: 0.8rem; color: var(--muted); margin-bottom: 0.25rem; }
        .entry-text, .comment-text { font-size: 0.95rem; white-space: pre-wrap; word-wrap: break-word; }
        .entry-link { margin-top: 0.5rem; }
        .entry-link a { color: var(--accent); text-decoration: none; font-size: 0.9rem; }
        .entry-link a:hover { text-decoration: underline; }
        .comment-context { font-size: 0.85rem; color: var(--muted); font-style: italic; margin-bottom: 0.25rem; }
        .image-item, .clap-item { padding: 0.5rem 0; border-bottom: 1px solid var(--border); font-size: 0.9rem; }
        .image-item a { color: var(--accent); text-decoration: none; }
        .meta { color: var(--muted); font-size: 0.8rem; }
        .empty { color: var(--muted); font-style: italic; padding: 0.5rem 0; }
        footer { margin-top: 3rem; padding-top: 1.5rem; border-top: 1px solid var(--border); color: var(--muted); font-size: 0.85rem; text-align: center; }
        a { color: var(--accent); }
    </style>
</head>
<body>
    <h1>Trail Data Export</h1>
    <p class="subtitle">Exported on {$exportDate} for @{$nickname}</p>

    <h2>Profile</h2>
    <div class="profile-card">
        <dl>
            <dt>Name</dt><dd>{$userName}</dd>
            <dt>Email</dt><dd>{$userEmail}</dd>
            <dt>Nickname</dt><dd>@{$nickname}</dd>
            <dt>Bio</dt><dd>{$bioHtml}</dd>
            <dt>Member Since</dt><dd>{$memberSince}</dd>
            <dt>Profile URL</dt><dd><a href="{$baseUrl}/@{$nickname}">{$baseUrl}/@{$nickname}</a></dd>
        </dl>
    </div>

    <h2>Statistics</h2>
    <div class="stats-grid">
        <div class="stat-box"><div class="value">{$entryCount}</div><div class="label">Entries</div></div>
        <div class="stat-box"><div class="value">{$commentCount}</div><div class="label">Comments</div></div>
        <div class="stat-box"><div class="value">{$imageCount}</div><div class="label">Images</div></div>
        <div class="stat-box"><div class="value">{$totalClaps}</div><div class="label">Claps Received</div></div>
    </div>

    <h2>Entries ({$entryCount})</h2>
    {$entriesHtml}

    <h2>Comments ({$commentCount})</h2>
    {$commentsHtml}

    <h2>Images ({$imageCount})</h2>
    {$imagesHtml}

    <h2>Claps Received</h2>
    {$clapsHtml}

    <h2>Notification Preferences</h2>
    {$notifHtml}

    <footer>
        <p>This file contains all personal data associated with your Trail account (@{$nickname}).</p>
        <p>Exported from <a href="{$baseUrl}">{$baseUrl}</a> on {$exportDate}.</p>
        <p>For questions, contact <a href="mailto:contact@kibotu.net">contact@kibotu.net</a>.</p>
    </footer>
</body>
</html>
HTML;

        $filename = 'trail-data-export-' . ($user['nickname'] ?? 'user') . '-' . date('Y-m-d') . '.html';

        $response->getBody()->write($html);
        return $response
            ->withHeader('Content-Type', 'text/html; charset=UTF-8')
            ->withHeader('Content-Disposition', 'attachment; filename="' . $filename . '"');
    }
}

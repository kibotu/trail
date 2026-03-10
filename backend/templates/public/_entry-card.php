<?php
/**
 * Server-side entry card partial.
 *
 * Expects $entry (array) and $isLoggedIn / $userId / $isAdmin from the
 * including scope.  The HTML structure mirrors createEntryCard() in
 * card-template.js so CSS and hydration work identically.
 */

$hashId       = $entry['hash_id'] ?? $entry['id'];
$displayName  = $entry['user_nickname'] ?? $entry['user_name'] ?? '';
$profileLink  = !empty($entry['user_nickname']) ? ('/@' . $entry['user_nickname']) : null;
$avatarUrl    = htmlspecialchars($entry['avatar_url'] ?? '', ENT_QUOTES);
$escapedName  = htmlspecialchars($displayName, ENT_QUOTES);
$canModify    = !empty($entry['can_edit']);
$showMenu     = $canModify || ($isLoggedIn && $userId && (int) $userId !== (int) $entry['user_id']);
$clapCount    = (int) ($entry['clap_count'] ?? 0);
$commentCount = (int) ($entry['comment_count'] ?? 0);
$viewCount    = (int) ($entry['view_count'] ?? 0);
$userClaps    = (int) ($entry['user_clap_count'] ?? 0);
$isOwnEntry   = $userId && (int) $userId === (int) $entry['user_id'];

// --- helpers (guarded to survive multiple includes) ---

if (!function_exists('_ssr_format_count')) {
    function _ssr_format_count(int $n): string {
        if ($n >= 1_000_000) return rtrim(number_format($n / 1_000_000, 1), '0.') . 'M';
        if ($n >= 1_000)     return rtrim(number_format($n / 1_000, 1), '0.') . 'k';
        return (string) $n;
    }

    function _ssr_relative_time(string $ts): string {
        $diff = time() - strtotime($ts);
        if ($diff < 60)    return 'just now';
        if ($diff < 3600)  return intdiv($diff, 60) . 'm ago';
        if ($diff < 86400) return intdiv($diff, 3600) . 'h ago';
        $days = intdiv($diff, 86400);
        if ($days < 7) return $days . 'd ago';
        $date = new DateTime($ts);
        $now  = new DateTime();
        $fmt  = $date->format('Y') !== $now->format('Y') ? 'M j, Y' : 'M j';
        return $date->format($fmt);
    }

    function _ssr_linkify(string $escaped): string {
        $escaped = preg_replace(
            '/(https?:\/\/[^\s]+)/',
            '<a href="$1" target="_blank" rel="noopener noreferrer">$1</a>',
            $escaped
        );
        $escaped = preg_replace(
            '/(?<!:\/\/)(?<!\/)@(\w+)/',
            '<a href="/@$1" class="mention-link" data-no-navigate>@$1</a>',
            $escaped
        );
        return $escaped;
    }

    function _ssr_extract_domain(string $url): string {
        $host = parse_url($url, PHP_URL_HOST);
        if ($host === null || $host === false) return $url;
        return preg_replace('/^www\./', '', $host);
    }
}

$escapedText = htmlspecialchars($entry['text'] ?? '', ENT_QUOTES);
$linkedText  = _ssr_linkify($escapedText);
$timestamp   = _ssr_relative_time($entry['created_at']);
$isEdited    = !empty($entry['updated_at']) && $entry['updated_at'] !== $entry['created_at'];
?>
<div class="entry-card"
     data-entry-id="<?= (int) $entry['id'] ?>"
     data-hash-id="<?= htmlspecialchars((string) $hashId, ENT_QUOTES) ?>"
     style="cursor:pointer" role="article" tabindex="0">
    <div class="entry-header">
        <?php if ($profileLink): ?>
            <a href="<?= htmlspecialchars($profileLink, ENT_QUOTES) ?>" data-no-navigate>
                <img src="<?= $avatarUrl ?>" alt="<?= $escapedName ?>" class="avatar" width="48" height="48" loading="lazy">
            </a>
        <?php else: ?>
            <img src="<?= $avatarUrl ?>" alt="<?= $escapedName ?>" class="avatar" width="48" height="48" loading="lazy">
        <?php endif; ?>
        <div class="entry-header-content">
            <div class="entry-header-top">
                <div class="user-info">
                    <?php if ($profileLink): ?>
                        <a href="<?= htmlspecialchars($profileLink, ENT_QUOTES) ?>" class="user-name-link" data-no-navigate><?= $escapedName ?></a>
                    <?php else: ?>
                        <span class="user-name"><?= $escapedName ?></span>
                    <?php endif; ?>
                    <span style="color: var(--text-muted);">&middot;</span>
                    <span class="timestamp"><?= $timestamp ?></span>
                </div>
                <?php if ($showMenu): ?>
                <div class="entry-menu">
                    <button class="menu-button" data-entry-id="<?= (int) $entry['id'] ?>" data-action="toggle-menu" data-no-navigate aria-label="More options">&#x22EF;</button>
                    <div class="menu-dropdown" id="menu-<?= (int) $entry['id'] ?>">
                        <?php if ($canModify): ?>
                        <button class="menu-item" data-entry-id="<?= (int) $entry['id'] ?>" data-action="edit" data-no-navigate>
                            <i class="fa-solid fa-pen"></i><span>Edit</span>
                        </button>
                        <button class="menu-item delete" data-entry-id="<?= (int) $entry['id'] ?>" data-action="delete" data-no-navigate>
                            <i class="fa-solid fa-trash"></i><span>Delete</span>
                        </button>
                        <?php endif; ?>
                        <?php if ($isLoggedIn && $userId && !$isOwnEntry): ?>
                        <button class="menu-item" data-entry-id="<?= (int) $entry['id'] ?>" data-user-id="<?= (int) $entry['user_id'] ?>" data-action="report" data-no-navigate>
                            <i class="fa-solid fa-flag"></i><span>Report Post</span>
                        </button>
                        <button class="menu-item" data-entry-id="<?= (int) $entry['id'] ?>" data-user-id="<?= (int) $entry['user_id'] ?>" data-action="mute" data-no-navigate>
                            <i class="fa-solid fa-volume-xmark"></i><span>Mute User</span>
                        </button>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <div class="entry-body">
        <div class="entry-content">
            <div class="entry-text"><?= $linkedText ?></div>
<?php
// --- Images ---
if (!empty($entry['images']) && is_array($entry['images'])): ?>
            <div class="entry-images">
<?php   foreach ($entry['images'] as $idx => $media):
            $mediaUrl = htmlspecialchars($media['url'] ?? '', ENT_QUOTES);
            $isVideo  = preg_match('/\.(mp4|webm|mov)(\?|$)/i', $media['url'] ?? '');
            if ($isVideo): ?>
                <div class="entry-media-wrapper entry-video-wrapper" data-media-index="<?= $idx ?>">
                    <video class="entry-video" src="<?= $mediaUrl ?>" muted playsinline preload="metadata" onerror="this.parentElement.style.display='none'">
                        Your browser does not support the video tag.
                    </video>
                    <div class="video-play-overlay" data-no-navigate>
                        <button class="video-play-button" aria-label="Play video" data-no-navigate><i class="fa-solid fa-play"></i></button>
                    </div>
                    <div class="video-controls" data-no-navigate>
                        <button class="video-playpause-btn" aria-label="Play/Pause" data-no-navigate><i class="fa-solid fa-pause"></i></button>
                        <div class="video-progress-container" data-no-navigate>
                            <div class="video-progress-bar" data-no-navigate>
                                <div class="video-progress-filled" data-no-navigate></div>
                                <div class="video-progress-handle" data-no-navigate></div>
                            </div>
                        </div>
                        <span class="video-time" data-no-navigate>0:00</span>
                        <button class="video-mute-button" aria-label="Unmute video" data-no-navigate><i class="fa-solid fa-volume-xmark"></i></button>
                        <button class="video-fullscreen-btn" aria-label="Fullscreen" data-no-navigate><i class="fa-solid fa-expand"></i></button>
                    </div>
                </div>
<?php       else:
                $imgW = (int) ($media['width'] ?? 600);
                $imgH = (int) ($media['height'] ?? 400);
                $srcset = '';
                if (!empty($media['srcset'])) {
                    $srcset = 'srcset="' . htmlspecialchars($media['srcset'], ENT_QUOTES) . '" sizes="(max-width: 600px) 300px, (max-width: 1200px) 600px, 1200px"';
                }
?>
                <div class="entry-image-wrapper">
                    <a href="<?= $mediaUrl ?>" target="_blank" rel="noopener noreferrer">
                        <img src="<?= $mediaUrl ?>" <?= $srcset ?> alt="Post image" class="entry-image" width="<?= $imgW ?>" height="<?= $imgH ?>" loading="lazy" onerror="this.parentElement.parentElement.style.display='none'">
                    </a>
                </div>
<?php       endif;
        endforeach; ?>
            </div>
<?php endif; ?>
<?php
// --- Tags ---
if (!empty($entry['tags']) && is_array($entry['tags'])): ?>
            <div class="entry-tags" data-no-navigate>
<?php   foreach ($entry['tags'] as $tag):
            $tagName = htmlspecialchars($tag['name'] ?? '', ENT_QUOTES);
            $tagSlug = htmlspecialchars($tag['slug'] ?? '', ENT_QUOTES);
?>
                <a href="/?q=%23<?= urlencode($tag['slug'] ?? '') ?>" class="entry-tag" data-tag-slug="<?= $tagSlug ?>" data-no-navigate>#<?= $tagName ?></a>
<?php   endforeach; ?>
            </div>
<?php endif; ?>
<?php
// --- Link preview ---
if (!empty($entry['preview_url'])):
    $hasTitle = !empty($entry['preview_title']) && strlen($entry['preview_title']) > 3
        && stripos($entry['preview_title'], 'just a moment') === false
        && stripos($entry['preview_title'], 'please wait') === false;
    $hasDesc  = !empty($entry['preview_description']) && strlen($entry['preview_description']) > 10;

    if ($hasTitle || $hasDesc || !empty($entry['preview_image'])):
        $previewUrl  = htmlspecialchars($entry['preview_url'], ENT_QUOTES);
        $siteName    = htmlspecialchars($entry['preview_site_name'] ?? _ssr_extract_domain($entry['preview_url']), ENT_QUOTES);
?>
            <a href="<?= $previewUrl ?>" class="link-preview-card" target="_blank" rel="noopener noreferrer">
<?php       if (!empty($entry['preview_image'])):
                $previewImg = $entry['preview_image'];
                try {
                    $parsed = parse_url($previewImg);
                    $ownDomains = ['trail.services.kibotu.net', 'trail.kibotu.net', 'localhost', '127.0.0.1'];
                    $host = $parsed['host'] ?? '';
                    if (!in_array($host, $ownDomains, true) && !str_starts_with($previewImg, 'data:')) {
                        $encoded = rtrim(strtr(base64_encode($previewImg), '+/', '-_'), '=');
                        $previewImg = '/api/image-proxy/' . $encoded . '?w=600';
                    }
                } catch (\Throwable $e) { /* use original */ }
?>
                <img src="<?= htmlspecialchars($previewImg, ENT_QUOTES) ?>" alt="Preview" class="link-preview-image" width="600" height="315" loading="lazy" onerror="this.style.display='none'">
<?php       endif; ?>
                <div class="link-preview-content">
<?php       if ($hasTitle): ?>
                    <div class="link-preview-title"><?= htmlspecialchars($entry['preview_title'], ENT_QUOTES) ?></div>
<?php       endif;
            if ($hasDesc): ?>
                    <div class="link-preview-description"><?= htmlspecialchars($entry['preview_description'], ENT_QUOTES) ?></div>
<?php       endif; ?>
                    <div class="link-preview-url"><i class="fa-solid fa-link"></i><span><?= $siteName ?></span></div>
                </div>
            </a>
<?php   endif;
endif; ?>
        </div>
        <div class="entry-footer">
            <div class="entry-footer-left">
                <?php if ($isEdited): ?>
                <div class="timestamp"><i class="fa-solid fa-pen"></i><span>edited <?= _ssr_relative_time($entry['updated_at']) ?></span></div>
                <?php else: ?>
                <div></div>
                <?php endif; ?>
            </div>
            <div class="entry-footer-actions">
                <div class="entry-footer-left">
                    <button class="comment-button"
                            data-entry-id="<?= (int) $entry['id'] ?>"
                            data-hash-id="<?= htmlspecialchars((string) $hashId, ENT_QUOTES) ?>"
                            data-comment-count="<?= $commentCount ?>"
                            aria-label="<?= $commentCount ?> comments">
                        <i class="fa-regular fa-comment" aria-hidden="true"></i>
                        <span class="comment-count"><?= $commentCount ?></span>
                    </button>
                    <button class="clap-button <?= $userClaps > 0 ? 'clapped' : '' ?> <?= $isOwnEntry ? 'own-entry' : '' ?>"
                            data-no-navigate
                            data-entry-id="<?= (int) $entry['id'] ?>"
                            data-hash-id="<?= htmlspecialchars((string) $hashId, ENT_QUOTES) ?>"
                            data-user-claps="<?= $userClaps ?>"
                            data-total-claps="<?= $clapCount ?>"
                            data-is-own="<?= $isOwnEntry ? 'true' : 'false' ?>"
                            aria-label="<?= _ssr_format_count($clapCount) ?> likes">
                        <i class="fa-<?= $userClaps > 0 ? 'solid' : 'regular' ?> fa-heart" aria-hidden="true"></i>
                        <span class="clap-count"><?= _ssr_format_count($clapCount) ?></span>
                    </button>
                    <span class="view-counter"
                          data-entry-id="<?= (int) $entry['id'] ?>"
                          data-hash-id="<?= htmlspecialchars((string) $hashId, ENT_QUOTES) ?>"
                          aria-label="Views">
                        <i class="fa-solid fa-chart-simple"></i>
                        <span class="view-count"><?= _ssr_format_count($viewCount) ?></span>
                    </span>
                    <button class="share-button" data-no-navigate data-hash-id="<?= htmlspecialchars((string) $hashId, ENT_QUOTES) ?>" aria-label="Share entry">
                        <i class="fa-solid fa-share-nodes"></i>
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

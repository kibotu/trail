<?php

declare(strict_types=1);

/**
 * Session Management Helpers
 * 
 * Shared session validation and management functions for web-based authentication.
 */

// Session Constants
const SESSION_EXPIRY_HOURS = 8760; // 1 year (365 days * 24 hours)
const SESSION_COOKIE_NAME = 'trail_session_id';

/**
 * Generate a secure random session ID.
 */
function generateSessionId(): string
{
    return bin2hex(random_bytes(32));
}

/**
 * Validate session ID format.
 */
function validateSessionIdFormat(string $sessionId): bool
{
    return preg_match('/^[a-f0-9]{64}$/', $sessionId) === 1;
}

/**
 * Set secure session cookie.
 */
function setSecureSessionCookie(string $sessionId, int $expiresAt): void
{
    setcookie(
        SESSION_COOKIE_NAME,
        $sessionId,
        [
            'expires' => $expiresAt,
            'path' => '/',
            'domain' => '',
            'secure' => true,
            'httponly' => true,
            'samesite' => 'Lax'
        ]
    );
}

/**
 * Clear session cookie.
 */
function clearSessionCookie(): void
{
    setcookie(
        SESSION_COOKIE_NAME,
        '',
        [
            'expires' => time() - 3600,
            'path' => '/',
            'domain' => '',
            'secure' => true,
            'httponly' => true,
            'samesite' => 'Lax'
        ]
    );
}

/**
 * Create a new session in the database.
 */
function createSession(
    PDO $db,
    string $sessionId,
    int $userId,
    string $email,
    ?string $photoUrl,
    bool $isAdmin,
    DateTime $expiresAt,
    ?string $jwtToken = null
): void {
    $stmt = $db->prepare("
        INSERT INTO trail_sessions (session_id, user_id, email, photo_url, is_admin, jwt_token, expires_at) 
        VALUES (?, ?, ?, ?, ?, ?, ?)
        ON DUPLICATE KEY UPDATE 
            user_id = VALUES(user_id),
            email = VALUES(email),
            photo_url = VALUES(photo_url),
            is_admin = VALUES(is_admin),
            jwt_token = VALUES(jwt_token),
            expires_at = VALUES(expires_at)
    ");
    $stmt->execute([
        $sessionId,
        $userId,
        $email,
        $photoUrl,
        $isAdmin ? 1 : 0,
        $jwtToken,
        $expiresAt->format('Y-m-d H:i:s')
    ]);
}

/**
 * Validate a session and return user data if valid.
 */
function validateUserSession(PDO $db, string $sessionId): ?array
{
    // Check if jwt_token column exists
    try {
        $stmt = $db->prepare("
            SELECT user_id, email, photo_url, is_admin, jwt_token, expires_at 
            FROM trail_sessions 
            WHERE session_id = ? AND expires_at > NOW()
        ");
        $stmt->execute([$sessionId]);
        return $stmt->fetch() ?: null;
    } catch (PDOException $e) {
        // Fallback if jwt_token column doesn't exist yet
        if (strpos($e->getMessage(), 'jwt_token') !== false) {
            $stmt = $db->prepare("
                SELECT user_id, email, photo_url, is_admin, expires_at 
                FROM trail_sessions 
                WHERE session_id = ? AND expires_at > NOW()
            ");
            $stmt->execute([$sessionId]);
            $result = $stmt->fetch();
            if ($result) {
                $result['jwt_token'] = null;
            }
            return $result ?: null;
        }
        throw $e;
    }
}

/**
 * Get authenticated user from session cookie.
 */
function getAuthenticatedUser(PDO $db): ?array
{
    $sessionId = $_COOKIE[SESSION_COOKIE_NAME] ?? null;

    if ($sessionId === null) {
        return null;
    }

    if (!validateSessionIdFormat($sessionId)) {
        clearSessionCookie();
        return null;
    }

    $session = validateUserSession($db, $sessionId);

    if ($session === null) {
        clearSessionCookie();
        return null;
    }

    return $session;
}

/**
 * Require authentication - redirect to login if not authenticated.
 */
function requireAuthentication(PDO $db, string $redirectUrl = '/'): array
{
    $session = getAuthenticatedUser($db);

    if ($session === null) {
        header('Location: ' . $redirectUrl);
        exit;
    }

    return $session;
}

/**
 * Require admin authentication.
 */
function requireAdminAuthentication(PDO $db, string $redirectUrl = '/'): array
{
    $session = requireAuthentication($db, $redirectUrl);

    if (!$session['is_admin']) {
        header('HTTP/1.1 403 Forbidden');
        echo 'Access denied: Admin privileges required';
        exit;
    }

    return $session;
}

/**
 * Delete a session from the database.
 */
function deleteSession(PDO $db, string $sessionId): void
{
    $stmt = $db->prepare("DELETE FROM trail_sessions WHERE session_id = ?");
    $stmt->execute([$sessionId]);
}

/**
 * Build Google OAuth authorization URL.
 */
function buildGoogleAuthUrl(array $googleOAuth): string
{
    $redirectUri = $_ENV['GOOGLE_CLIENT_REDIRECT_URI'] ?? $googleOAuth['redirect_uri'] ?? '';
    
    return 'https://accounts.google.com/o/oauth2/v2/auth?' . http_build_query([
        'client_id' => $googleOAuth['client_id'],
        'redirect_uri' => $redirectUri,
        'response_type' => 'code',
        'scope' => 'email profile',
        'access_type' => 'online',
        'prompt' => 'select_account'
    ]);
}

/**
 * Get user avatar URL with fallback to Gravatar.
 * 
 * @param string|null $photoUrl Google profile photo URL
 * @param string $email User email for Gravatar fallback
 * @param int $size Avatar size in pixels
 * @return string Avatar URL (already HTML-escaped)
 */
function getUserAvatarUrl(?string $photoUrl, string $email, int $size = 96): string
{
    // Use Google photo if available
    if ($photoUrl !== null && $photoUrl !== '') {
        return htmlspecialchars($photoUrl);
    }

    // Fallback to Gravatar
    $gravatarHash = md5(strtolower(trim($email)));
    return "https://www.gravatar.com/avatar/{$gravatarHash}?s={$size}&d=mp";
}

/**
 * Get user avatar URL from user data with fallback.
 * 
 * @param array $user User data array with photo_url, gravatar_hash, and email
 * @param int $size Avatar size in pixels
 * @return string Avatar URL (already HTML-escaped)
 */
function getUserAvatarFromData(array $user, int $size = 96): string
{
    // Use Google photo if available
    if (!empty($user['photo_url'])) {
        return htmlspecialchars($user['photo_url']);
    }

    // Fallback to Gravatar using hash if available, otherwise email
    if (!empty($user['gravatar_hash'])) {
        return "https://www.gravatar.com/avatar/{$user['gravatar_hash']}?s={$size}&d=mp";
    }

    if (!empty($user['email'])) {
        $gravatarHash = md5(strtolower(trim($user['email'])));
        return "https://www.gravatar.com/avatar/{$gravatarHash}?s={$size}&d=mp";
    }

    // Ultimate fallback
    return "https://www.gravatar.com/avatar/00000000000000000000000000000000?s={$size}&d=mp";
}

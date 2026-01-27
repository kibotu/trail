<?php

declare(strict_types=1);

/**
 * Google OAuth Callback Handler
 * 
 * Exchanges authorization code for access token and creates a new session.
 */

require_once __DIR__ . '/../../vendor/autoload.php';
require_once __DIR__ . '/../helpers/session.php';

use Trail\Config\Config;
use Trail\Database\Database;


// Security headers
header('X-Frame-Options: DENY');
header('X-Content-Type-Options: nosniff');
header('X-XSS-Protection: 1; mode=block');
header('Referrer-Policy: strict-origin-when-cross-origin');

try {
    $config = Config::load(__DIR__ . '/../../config.yml');
    $db = Database::getInstance($config);
    
    $googleOAuth = $config['google_oauth'] ?? throw new Exception("Google OAuth not configured");

    // Validate authorization code
    $code = $_GET['code'] ?? null;
    if ($code === null) {
        throw new Exception("Missing authorization code");
    }

    // Exchange code for access token
    $tokenData = exchangeCodeForToken($code, $googleOAuth);

    // Get user info from Google
    $userInfo = getUserInfo($tokenData['access_token']);
    $email = $userInfo['email'] ?? throw new Exception("Email not provided by Google");
    $name = $userInfo['name'] ?? '';
    $photoUrl = $userInfo['picture'] ?? null;
    $googleId = $userInfo['id'] ?? throw new Exception("Google ID not provided");

    // Validate email format
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        throw new Exception("Invalid email address");
    }

    // Find or create user in database
    $stmt = $db->prepare("SELECT id, is_admin FROM trail_users WHERE google_id = ?");
    $stmt->execute([$googleId]);
    $user = $stmt->fetch();

    if ($user) {
        // Update existing user
        $stmt = $db->prepare("
            UPDATE trail_users 
            SET email = ?, name = ?, updated_at = CURRENT_TIMESTAMP 
            WHERE google_id = ?
        ");
        $stmt->execute([$email, $name, $googleId]);
        $userId = $user['id'];
        $isAdmin = (bool) $user['is_admin'];
    } else {
        // Create new user
        $stmt = $db->prepare("
            INSERT INTO trail_users (google_id, email, name, gravatar_hash) 
            VALUES (?, ?, ?, ?)
        ");
        $gravatarHash = md5(strtolower(trim($email)));
        $stmt->execute([$googleId, $email, $name, $gravatarHash]);
        $userId = (int) $db->lastInsertId();
        $isAdmin = false;
    }

    // Create new session
    $sessionId = generateSessionId();
    $expiresAt = (new DateTime())->modify('+24 hours');

    createSession($db, $sessionId, $userId, $email, $photoUrl, $isAdmin, $expiresAt);

    // Set secure session cookie
    setSecureSessionCookie($sessionId, $expiresAt->getTimestamp());

    // Redirect to admin dashboard
    header('Location: /admin/');
    exit;

} catch (Exception $e) {
    error_log("OAuth callback error: " . $e->getMessage());
    header('Location: /admin/login.php?error=' . urlencode($e->getMessage()));
    exit;
}

/**
 * Exchange OAuth authorization code for access token.
 */
function exchangeCodeForToken(string $code, array $googleOAuth): array
{
    $tokenUrl = 'https://oauth2.googleapis.com/token';

    $postData = [
        'code' => $code,
        'client_id' => $googleOAuth['client_id'],
        'client_secret' => $googleOAuth['client_secret'],
        'redirect_uri' => $_ENV['GOOGLE_CLIENT_REDIRECT_URI'] ?? $googleOAuth['redirect_uri'] ?? '',
        'grant_type' => 'authorization_code'
    ];

    $ch = curl_init($tokenUrl);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($postData));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);

    $response = curl_exec($ch);
    
    if ($response === false) {
        $error = curl_error($ch);
        curl_close($ch);
        throw new Exception("Token exchange failed: " . $error);
    }
    
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($httpCode !== 200) {
        throw new Exception("Failed to exchange code for token: HTTP $httpCode");
    }

    $data = json_decode($response, true);
    if (!isset($data['access_token'])) {
        throw new Exception("Access token not received");
    }

    return $data;
}

/**
 * Fetch user profile information from Google.
 */
function getUserInfo(string $accessToken): array
{
    $userInfoUrl = 'https://www.googleapis.com/oauth2/v2/userinfo';

    $ch = curl_init($userInfoUrl);
    curl_setopt($ch, CURLOPT_HTTPHEADER, ["Authorization: Bearer $accessToken"]);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);

    $response = curl_exec($ch);
    
    if ($response === false) {
        $error = curl_error($ch);
        curl_close($ch);
        throw new Exception("Failed to get user info: " . $error);
    }
    
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($httpCode !== 200) {
        throw new Exception("Failed to get user info: HTTP $httpCode");
    }

    return json_decode($response, true);
}

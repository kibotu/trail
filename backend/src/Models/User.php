<?php

declare(strict_types=1);

namespace Trail\Models;

use PDO;

class User
{
    private PDO $db;
    private string $table = 'trail_users';

    public function __construct(PDO $db)
    {
        $this->db = $db;
    }

    public function findByGoogleId(string $googleId): ?array
    {
        $stmt = $this->db->prepare("SELECT * FROM {$this->table} WHERE google_id = ?");
        $stmt->execute([$googleId]);
        $user = $stmt->fetch();
        
        return $user ?: null;
    }

    public function findById(int $id): ?array
    {
        $stmt = $this->db->prepare("SELECT * FROM {$this->table} WHERE id = ?");
        $stmt->execute([$id]);
        $user = $stmt->fetch();
        
        return $user ?: null;
    }

    public function findByNickname(string $nickname): ?array
    {
        try {
            $stmt = $this->db->prepare("
                SELECT u.*,
                       pi.filename as profile_image_filename,
                       hi.filename as header_image_filename
                FROM {$this->table} u
                LEFT JOIN trail_images pi ON u.profile_image_id = pi.id
                LEFT JOIN trail_images hi ON u.header_image_id = hi.id
                WHERE u.nickname = ?
            ");
            $stmt->execute([$nickname]);
            $user = $stmt->fetch();
            
            if (!$user) {
                return null;
            }
            
            // Add image URLs
            if (!empty($user['profile_image_filename'])) {
                $user['profile_image_url'] = '/uploads/images/' . $user['id'] . '/' . $user['profile_image_filename'];
            }
            if (!empty($user['header_image_filename'])) {
                $user['header_image_url'] = '/uploads/images/' . $user['id'] . '/' . $user['header_image_filename'];
            }
            
            return $user;
        } catch (\PDOException $e) {
            // Fallback if trail_images table doesn't exist yet
            error_log("findByNickname error (table may not exist): " . $e->getMessage());
            $stmt = $this->db->prepare("SELECT * FROM {$this->table} WHERE nickname = ?");
            $stmt->execute([$nickname]);
            $user = $stmt->fetch();
            return $user ?: null;
        }
    }

    public function create(string $googleId, string $email, string $name, string $gravatarHash, ?string $photoUrl = null): int
    {
        $stmt = $this->db->prepare(
            "INSERT INTO {$this->table} (google_id, email, name, gravatar_hash, photo_url) 
             VALUES (?, ?, ?, ?, ?)"
        );
        $stmt->execute([$googleId, $email, $name, $gravatarHash, $photoUrl]);
        
        return (int) $this->db->lastInsertId();
    }

    public function update(int $id, string $email, string $name, string $gravatarHash, ?string $photoUrl = null): bool
    {
        $stmt = $this->db->prepare(
            "UPDATE {$this->table} 
             SET email = ?, name = ?, gravatar_hash = ?, photo_url = ?, updated_at = CURRENT_TIMESTAMP 
             WHERE id = ?"
        );
        
        return $stmt->execute([$email, $name, $gravatarHash, $photoUrl, $id]);
    }

    public function delete(int $id): bool
    {
        $stmt = $this->db->prepare("DELETE FROM {$this->table} WHERE id = ?");
        return $stmt->execute([$id]);
    }

    public function getAll(int $limit = 50, ?string $before = null): array
    {
        if ($before !== null) {
            // Cursor-based pagination: get users created before the cursor timestamp
            $stmt = $this->db->prepare(
                "SELECT * FROM {$this->table} 
                 WHERE created_at < ? 
                 ORDER BY created_at DESC 
                 LIMIT ?"
            );
            $stmt->execute([$before, $limit]);
        } else {
            // Initial load: get most recent users
            $stmt = $this->db->prepare(
                "SELECT * FROM {$this->table} 
                 ORDER BY created_at DESC 
                 LIMIT ?"
            );
            $stmt->execute([$limit]);
        }
        
        return $stmt->fetchAll();
    }

    public function count(): int
    {
        $stmt = $this->db->query("SELECT COUNT(*) as count FROM {$this->table}");
        $result = $stmt->fetch();
        
        return (int) $result['count'];
    }

    public function setAdminStatus(int $id, bool $isAdmin): bool
    {
        $stmt = $this->db->prepare(
            "UPDATE {$this->table} 
             SET is_admin = ?, updated_at = CURRENT_TIMESTAMP 
             WHERE id = ?"
        );
        
        return $stmt->execute([$isAdmin ? 1 : 0, $id]);
    }

    public function updateNickname(int $id, string $nickname): bool
    {
        $stmt = $this->db->prepare(
            "UPDATE {$this->table} 
             SET nickname = ?, updated_at = CURRENT_TIMESTAMP 
             WHERE id = ?"
        );
        
        return $stmt->execute([$nickname, $id]);
    }

    /**
     * Update user's bio
     */
    public function updateBio(int $id, ?string $bio): bool
    {
        $stmt = $this->db->prepare(
            "UPDATE {$this->table} 
             SET bio = ?, updated_at = CURRENT_TIMESTAMP 
             WHERE id = ?"
        );
        
        return $stmt->execute([$bio, $id]);
    }

    /**
     * Generate a unique nickname hash for a user
     * Uses google_id + salt for uniqueness
     */
    public function generateNicknameHash(string $googleId, string $salt): string
    {
        // Create a hash of google_id + salt
        $hash = hash('sha256', $googleId . $salt);
        // Take first 8 characters for a short, readable nickname
        return 'user_' . substr($hash, 0, 8);
    }

    /**
     * Get or generate nickname for a user
     * If user doesn't have a nickname, generate one and save it
     */
    public function getOrGenerateNickname(int $userId, string $googleId, string $salt): string
    {
        $user = $this->findById($userId);
        
        if ($user && !empty($user['nickname'])) {
            return $user['nickname'];
        }
        
        // Generate a unique nickname
        $nickname = $this->generateNicknameHash($googleId, $salt);
        
        // Ensure uniqueness by appending a counter if needed
        $counter = 1;
        $originalNickname = $nickname;
        while ($this->findByNickname($nickname) !== null) {
            $nickname = $originalNickname . $counter;
            $counter++;
        }
        
        // Save the generated nickname
        $this->updateNickname($userId, $nickname);
        
        return $nickname;
    }

    /**
     * Check if a nickname is available (not taken by another user)
     */
    public function isNicknameAvailable(string $nickname, ?int $excludeUserId = null): bool
    {
        $user = $this->findByNickname($nickname);
        
        if ($user === null) {
            return true;
        }
        
        // If excluding a user ID (for updates), check if the nickname belongs to that user
        if ($excludeUserId !== null && $user['id'] === $excludeUserId) {
            return true;
        }
        
        return false;
    }
    
    /**
     * Update user's profile image
     */
    public function updateProfileImage(int $userId, int $imageId): bool
    {
        $stmt = $this->db->prepare(
            "UPDATE {$this->table} 
             SET profile_image_id = ?, updated_at = CURRENT_TIMESTAMP 
             WHERE id = ?"
        );
        
        return $stmt->execute([$imageId, $userId]);
    }
    
    /**
     * Update user's header image
     */
    public function updateHeaderImage(int $userId, int $imageId): bool
    {
        $stmt = $this->db->prepare(
            "UPDATE {$this->table} 
             SET header_image_id = ?, updated_at = CURRENT_TIMESTAMP 
             WHERE id = ?"
        );
        
        return $stmt->execute([$imageId, $userId]);
    }
    
    /**
     * Get user with image URLs
     */
    public function findByIdWithImages(int $id): ?array
    {
        try {
            $stmt = $this->db->prepare("
                SELECT u.*,
                       pi.filename as profile_image_filename,
                       hi.filename as header_image_filename
                FROM {$this->table} u
                LEFT JOIN trail_images pi ON u.profile_image_id = pi.id
                LEFT JOIN trail_images hi ON u.header_image_id = hi.id
                WHERE u.id = ?
            ");
            $stmt->execute([$id]);
            $user = $stmt->fetch();
            
            if (!$user) {
                return null;
            }
            
            // Add image URLs
            if (!empty($user['profile_image_filename'])) {
                $user['profile_image_url'] = '/uploads/images/' . $user['id'] . '/' . $user['profile_image_filename'];
            }
            if (!empty($user['header_image_filename'])) {
                $user['header_image_url'] = '/uploads/images/' . $user['id'] . '/' . $user['header_image_filename'];
            }
            
            return $user;
        } catch (\PDOException $e) {
            // Fallback if trail_images table doesn't exist yet
            error_log("findByIdWithImages error (table may not exist): " . $e->getMessage());
            return $this->findById($id);
        }
    }

    /**
     * Find user by API token
     */
    public function findByApiToken(string $apiToken): ?array
    {
        $stmt = $this->db->prepare("SELECT * FROM {$this->table} WHERE api_token = ?");
        $stmt->execute([$apiToken]);
        $user = $stmt->fetch();
        
        return $user ?: null;
    }

    /**
     * Update user's API token
     */
    public function updateApiToken(int $userId, string $apiToken): bool
    {
        $stmt = $this->db->prepare(
            "UPDATE {$this->table} 
             SET api_token = ?, api_token_created_at = CURRENT_TIMESTAMP 
             WHERE id = ?"
        );
        
        return $stmt->execute([$apiToken, $userId]);
    }

    /**
     * Get profile statistics for a user (entries, links, comments, last entry, last login, view stats)
     *
     * Runs a single query with subselects for efficiency.
     */
    public function getProfileStats(int $userId): array
    {
        $stmt = $this->db->prepare("
            SELECT
                (SELECT COUNT(*) FROM trail_entries WHERE user_id = :uid1) AS entry_count,
                (SELECT COUNT(*) FROM trail_entries WHERE user_id = :uid2 AND preview_url IS NOT NULL) AS link_count,
                (SELECT COUNT(*) FROM trail_comments WHERE user_id = :uid3) AS comment_count,
                (SELECT MAX(created_at) FROM trail_entries WHERE user_id = :uid4) AS last_entry_at,
                (SELECT MAX(created_at) FROM trail_sessions WHERE user_id = :uid5
                    AND created_at < (SELECT MAX(created_at) FROM trail_sessions WHERE user_id = :uid6)
                ) AS previous_login_at,
                COALESCE((
                    SELECT SUM(vc.view_count)
                    FROM trail_view_counts vc
                    INNER JOIN trail_entries e ON vc.target_id = e.id
                    WHERE vc.target_type = 'entry' AND e.user_id = :uid7
                ), 0) AS total_entry_views,
                COALESCE((
                    SELECT SUM(vc.view_count)
                    FROM trail_view_counts vc
                    INNER JOIN trail_comments c ON vc.target_id = c.id
                    WHERE vc.target_type = 'comment' AND c.user_id = :uid8
                ), 0) AS total_comment_views,
                COALESCE((
                    SELECT view_count
                    FROM trail_view_counts
                    WHERE target_type = 'profile' AND target_id = :uid9
                ), 0) AS total_profile_views
        ");
        $stmt->execute([
            ':uid1' => $userId,
            ':uid2' => $userId,
            ':uid3' => $userId,
            ':uid4' => $userId,
            ':uid5' => $userId,
            ':uid6' => $userId,
            ':uid7' => $userId,
            ':uid8' => $userId,
            ':uid9' => $userId,
        ]);

        $row = $stmt->fetch();

        return [
            'entry_count'         => (int) ($row['entry_count'] ?? 0),
            'link_count'          => (int) ($row['link_count'] ?? 0),
            'comment_count'       => (int) ($row['comment_count'] ?? 0),
            'last_entry_at'       => $row['last_entry_at'] ?? null,
            'previous_login_at'   => $row['previous_login_at'] ?? null,
            'total_entry_views'   => (int) ($row['total_entry_views'] ?? 0),
            'total_comment_views' => (int) ($row['total_comment_views'] ?? 0),
            'total_profile_views' => (int) ($row['total_profile_views'] ?? 0),
        ];
    }

    /**
     * Generate a cryptographically secure API token
     */
    public function generateApiToken(): string
    {
        return bin2hex(random_bytes(32)); // 64 hex characters
    }
}

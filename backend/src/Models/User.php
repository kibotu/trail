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
        $stmt = $this->db->prepare("SELECT * FROM {$this->table} WHERE nickname = ?");
        $stmt->execute([$nickname]);
        $user = $stmt->fetch();
        
        return $user ?: null;
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
}

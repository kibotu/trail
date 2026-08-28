<?php

declare(strict_types=1);

namespace Trail\Models;

use PDO;

class Collection
{
    private PDO $db;
    private string $table = 'trail_collections';

    /** Top-level route segments a collection slug must not collide with */
    public const RESERVED_SLUGS = [
        'api', 'profile', 'status', 'admin', 'assets', 'uploads',
        'data-privacy', 'terms-and-conditions', 'account-pending-deletion',
        'notifications', 'collection', 'collections',
    ];

    public function __construct(PDO $db)
    {
        $this->db = $db;
    }

    /**
     * Validate slug format: lowercase [a-z0-9-], 2-64 chars.
     * Returns an error message, or null when valid.
     */
    public static function validateSlug(string $slug): ?string
    {
        if (mb_strlen($slug) < 2 || mb_strlen($slug) > 64) {
            return 'Slug must be 2-64 characters';
        }
        if (!preg_match('/^[a-z0-9-]+$/', $slug)) {
            return 'Slug may only contain lowercase letters, digits and hyphens';
        }
        return null;
    }

    /**
     * Suggest a slug from a collection name.
     */
    public static function slugify(string $name): string
    {
        $slug = mb_strtolower($name, 'UTF-8');
        $slug = preg_replace('/[^a-z0-9]+/', '-', $slug) ?? '';
        $slug = trim($slug, '-');
        return substr($slug, 0, 64);
    }

    /**
     * All collections with entry count and view count.
     */
    public function getAll(): array
    {
        $stmt = $this->db->prepare(
            "SELECT c.*,
                    COUNT(DISTINCT et.entry_id) AS entry_count,
                    COALESCE(vc.view_count, 0) AS view_count,
                    av.filename AS avatar_filename,
                    av.user_id AS avatar_user_id,
                    hd.filename AS header_filename,
                    hd.user_id AS header_user_id
             FROM {$this->table} c
             LEFT JOIN trail_collection_tags ct ON ct.collection_id = c.id
             LEFT JOIN trail_entry_tags et ON et.tag_id = ct.tag_id
             LEFT JOIN trail_view_counts vc ON vc.target_type = 'collection' AND vc.target_id = c.id
             LEFT JOIN trail_images av ON av.id = c.avatar_image_id
             LEFT JOIN trail_images hd ON hd.id = c.header_image_id
             GROUP BY c.id
             ORDER BY c.created_at DESC"
        );
        $stmt->execute();

        return array_map(fn(array $row): array => $this->attachImageUrls($row), $stmt->fetchAll());
    }

    /**
     * Find a collection by slug with counts.
     */
    public function findBySlug(string $slug): ?array
    {
        $stmt = $this->db->prepare(
            "SELECT c.*,
                    COUNT(DISTINCT et.entry_id) AS entry_count,
                    COALESCE(vc.view_count, 0) AS view_count,
                    av.filename AS avatar_filename,
                    av.user_id AS avatar_user_id,
                    hd.filename AS header_filename,
                    hd.user_id AS header_user_id
             FROM {$this->table} c
             LEFT JOIN trail_collection_tags ct ON ct.collection_id = c.id
             LEFT JOIN trail_entry_tags et ON et.tag_id = ct.tag_id
             LEFT JOIN trail_view_counts vc ON vc.target_type = 'collection' AND vc.target_id = c.id
             LEFT JOIN trail_images av ON av.id = c.avatar_image_id
             LEFT JOIN trail_images hd ON hd.id = c.header_image_id
             WHERE c.slug = ?
             GROUP BY c.id"
        );
        $stmt->execute([$slug]);
        $row = $stmt->fetch();

        return $row ? $this->attachImageUrls($row) : null;
    }

    public function findById(int $id): ?array
    {
        $stmt = $this->db->prepare("SELECT * FROM {$this->table} WHERE id = ?");
        $stmt->execute([$id]);
        $row = $stmt->fetch();

        return $row ? $this->attachImageUrls($row) : null;
    }

    /**
     * Create a collection.
     */
    public function create(int $ownerUserId, string $name, string $slug, ?string $bio = null, ?int $avatarImageId = null, ?int $headerImageId = null, array $tagIds = []): int
    {
        $this->db->beginTransaction();
        try {
            $stmt = $this->db->prepare(
                "INSERT INTO {$this->table} (owner_user_id, name, slug, bio, avatar_image_id, header_image_id) VALUES (?, ?, ?, ?, ?, ?)"
            );
            $stmt->execute([$ownerUserId, $name, $slug, $bio, $avatarImageId, $headerImageId]);
            $id = (int) $this->db->lastInsertId();

            if (!empty($tagIds)) {
                $values = array_fill(0, count($tagIds), '(?, ?)');
                $params = [];
                foreach ($tagIds as $tagId) {
                    $params[] = $id;
                    $params[] = (int) $tagId;
                }
                $this->db->prepare("INSERT IGNORE INTO trail_collection_tags (collection_id, tag_id) VALUES " . implode(', ', $values))->execute($params);
            }

            $this->db->commit();
        } catch (\PDOException $e) {
            $this->db->rollBack();
            throw $e;
        }

        return $id;
    }

    /**
     * Replace all collection fields. Nulls clear the field.
     */
    public function update(int $id, string $name, string $slug, ?string $bio, ?int $avatarImageId, ?int $headerImageId): bool
    {
        $stmt = $this->db->prepare(
            "UPDATE {$this->table} SET name = ?, slug = ?, bio = ?, avatar_image_id = ?, header_image_id = ? WHERE id = ?"
        );

        return $stmt->execute([$name, $slug, $bio, $avatarImageId, $headerImageId, $id]);
    }

    /**
     * Hard delete a collection. Claimed entries revert to poster attribution automatically.
     */
    public function delete(int $id): bool
    {
        $this->db->beginTransaction();
        try {
            $this->db->prepare("DELETE FROM trail_view_counts WHERE target_type = 'collection' AND target_id = ?")->execute([$id]);
            $this->db->prepare("DELETE FROM trail_views WHERE target_type = 'collection' AND target_id = ?")->execute([$id]);
            $stmt = $this->db->prepare("DELETE FROM {$this->table} WHERE id = ?");
            $success = $stmt->execute([$id]);
            $this->db->commit();

            return $success;
        } catch (\PDOException $e) {
            $this->db->rollBack();
            throw $e;
        }
    }

    /**
     * Tags for a collection.
     */
    public function getTags(int $id): array
    {
        $stmt = $this->db->prepare(
            "SELECT t.id, t.name, t.slug
             FROM trail_tags t
             JOIN trail_collection_tags ct ON ct.tag_id = t.id
             WHERE ct.collection_id = ?
             ORDER BY t.name ASC"
        );
        $stmt->execute([$id]);

        return $stmt->fetchAll();
    }

    /**
     * Replace the tag set of a collection (idempotent).
     */
    public function setTags(int $collectionId, array $tagIds): void
    {
        $this->db->beginTransaction();
        try {
            $this->db->prepare("DELETE FROM trail_collection_tags WHERE collection_id = ?")->execute([$collectionId]);

            if (!empty($tagIds)) {
                $values = array_fill(0, count($tagIds), '(?, ?)');
                $params = [];
                foreach ($tagIds as $tagId) {
                    $params[] = $collectionId;
                    $params[] = (int) $tagId;
                }
                $stmt = $this->db->prepare(
                    "INSERT IGNORE INTO trail_collection_tags (collection_id, tag_id) VALUES " . implode(', ', $values)
                );
                $stmt->execute($params);
            }

            $this->db->commit();
        } catch (\PDOException $e) {
            $this->db->rollBack();
            throw $e;
        }
    }

    /**
     * Build avatar/header image URLs from trail_images rows.
     */

    private function attachImageUrls(array $row): array
    {
        if (!empty($row['avatar_image_id'])) {
            $row['avatar_url'] = '/uploads/images/' . $row['avatar_user_id'] . '/' . $row['avatar_filename'];
        }
        if (!empty($row['header_image_id'])) {
            $row['header_image_url'] = '/uploads/images/' . $row['header_user_id'] . '/' . $row['header_filename'];
        }

        return $row;
    }
}

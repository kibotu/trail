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
    public function create(int $ownerUserId, string $name, string $slug, ?string $bio = null, ?int $avatarImageId = null, ?int $headerImageId = null): int
    {
        $stmt = $this->db->prepare(
            "INSERT INTO {$this->table} (owner_user_id, name, slug, bio, avatar_image_id, header_image_id) VALUES (?, ?, ?, ?, ?, ?)"
        );
        $stmt->execute([$ownerUserId, $name, $slug, $bio, $avatarImageId, $headerImageId]);

        return (int) $this->db->lastInsertId();
    }

    /**
     * Update collection fields. Null values leave the field untouched.
     */
    public function update(int $id, ?string $name = null, ?string $slug = null, ?string $bio = null, ?int $avatarImageId = null, ?int $headerImageId = null): bool
    {
        $fields = [];
        $params = [];
        if ($name !== null) {
            $fields[] = 'name = ?';
            $params[] = $name;
        }
        if ($slug !== null) {
            $fields[] = 'slug = ?';
            $params[] = $slug;
        }
        if ($bio !== null) {
            $fields[] = 'bio = ?';
            $params[] = $bio;
        }
        if ($avatarImageId !== null) {
            $fields[] = 'avatar_image_id = ?';
            $params[] = $avatarImageId;
        }
        if ($headerImageId !== null) {
            $fields[] = 'header_image_id = ?';
            $params[] = $headerImageId;
        }
        if (empty($fields)) {
            return true;
        }

        $params[] = $id;
        $sql = "UPDATE {$this->table} SET " . implode(', ', $fields) . ", updated_at = CURRENT_TIMESTAMP WHERE id = ?";
        $stmt = $this->db->prepare($sql);

        return $stmt->execute($params);
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
     * Entries belonging to the collection (cursor pagination on created_at).
     *
     * Optional filters mirror Entry::getAll: $excludeUserId hides entries from
     * muted users, $excludeEntryIds hides reported entries, and $searchQuery
     * enables FULLTEXT/LIKE search (mirrors Entry::searchByUser).
     */
    public function getEntries(int $collectionId, int $limit = 20, ?string $before = null, ?int $currentUserId = null, ?int $excludeUserId = null, array $excludeEntryIds = [], ?string $searchQuery = null): array
    {
        $sql = "SELECT e.*, u.name as user_name, u.email as user_email, u.nickname as user_nickname, u.gravatar_hash, u.photo_url, u.google_id,
                p.url as preview_url, p.title as preview_title, p.description as preview_description,
                p.image as preview_image, p.site_name as preview_site_name, p.json as preview_json, p.source as preview_source,
                COALESCE(clap_totals.total_claps, 0) as clap_count,
                COALESCE(comment_counts.comment_count, 0) as comment_count,
                COALESCE(view_counts.view_count, 0) as view_count";

        $params = [];

        // FULLTEXT relevance column for queries >= 4 chars (mirrors Entry::searchByUser)
        $useFulltext = $searchQuery !== null && mb_strlen($searchQuery) >= 4;
        if ($useFulltext) {
            $sql .= ", (MATCH(e.text) AGAINST(? IN NATURAL LANGUAGE MODE) + COALESCE(MATCH(p.title, p.description, p.site_name) AGAINST(? IN NATURAL LANGUAGE MODE), 0)) as relevance";
            $params[] = $searchQuery;
            $params[] = $searchQuery;
        }

        if ($currentUserId !== null) {
            $sql .= ", COALESCE(user_claps.clap_count, 0) as user_clap_count";
        }

        $sql .= " FROM trail_entries e
                 JOIN trail_users u ON e.user_id = u.id
                 JOIN (
                     SELECT et.entry_id
                     FROM trail_entry_tags et
                     JOIN trail_collection_tags ct ON ct.tag_id = et.tag_id
                     WHERE ct.collection_id = ?
                     GROUP BY et.entry_id
                 ) claimed ON claimed.entry_id = e.id
                 LEFT JOIN trail_url_previews p ON e.url_preview_id = p.id
                 LEFT JOIN (
                     SELECT entry_id, SUM(clap_count) as total_claps
                     FROM trail_claps
                     GROUP BY entry_id
                 ) clap_totals ON e.id = clap_totals.entry_id
                 LEFT JOIN (
                     SELECT entry_id, COUNT(*) as comment_count
                     FROM trail_comments
                     GROUP BY entry_id
                 ) comment_counts ON e.id = comment_counts.entry_id
                 LEFT JOIN trail_view_counts view_counts
                     ON view_counts.target_type = 'entry' AND view_counts.target_id = e.id";

        $params[] = $collectionId;

        if ($currentUserId !== null) {
            $sql .= " LEFT JOIN trail_claps user_claps ON e.id = user_claps.entry_id AND user_claps.user_id = ?";
            $params[] = $currentUserId;
        }

        $whereConditions = ["u.deletion_requested_at IS NULL"];

        if ($searchQuery !== null) {
            $likeQuery = '%' . str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $searchQuery) . '%';
            if ($useFulltext) {
                $whereConditions[] = "(MATCH(e.text) AGAINST(? IN NATURAL LANGUAGE MODE) > 0 OR MATCH(p.title, p.description, p.site_name) AGAINST(? IN NATURAL LANGUAGE MODE) > 0 OR e.text LIKE ? OR p.title LIKE ? OR p.description LIKE ? OR p.site_name LIKE ?)";
                $params[] = $searchQuery;
                $params[] = $searchQuery;
                $params[] = $likeQuery;
                $params[] = $likeQuery;
                $params[] = $likeQuery;
                $params[] = $likeQuery;
            } else {
                $whereConditions[] = "(e.text LIKE ? OR p.title LIKE ? OR p.description LIKE ? OR p.site_name LIKE ?)";
                $params[] = $likeQuery;
                $params[] = $likeQuery;
                $params[] = $likeQuery;
                $params[] = $likeQuery;
            }
        }

        if ($before !== null) {
            $whereConditions[] = "e.created_at < ?";
            $params[] = $before;
        }

        // Exclude muted users
        if ($excludeUserId !== null) {
            $whereConditions[] = "e.user_id NOT IN (
                SELECT muted_user_id FROM trail_muted_users WHERE muter_user_id = ?
            )";
            $params[] = $excludeUserId;
        }

        // Exclude hidden entries
        if (!empty($excludeEntryIds)) {
            $placeholders = implode(',', array_fill(0, count($excludeEntryIds), '?'));
            $whereConditions[] = "e.id NOT IN ($placeholders)";
            $params = array_merge($params, $excludeEntryIds);
        }

        $sql .= " WHERE " . implode(' AND ', $whereConditions);
        $sql .= " ORDER BY e.created_at DESC LIMIT ?";
        $params[] = $limit;

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetchAll();
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

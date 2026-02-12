<?php

declare(strict_types=1);

namespace Trail\Models;

use PDO;

class Tag
{
    private PDO $db;
    private string $table = 'trail_tags';
    private string $junctionTable = 'trail_entry_tags';

    public function __construct(PDO $db)
    {
        $this->db = $db;
    }

    /**
     * Find or create a tag by name
     * 
     * @param string $name Tag name (will be slugified)
     * @return array Tag data ['id' => int, 'name' => string, 'slug' => string]
     */
    public function findOrCreate(string $name): array
    {
        $name = trim($name);
        if (empty($name)) {
            throw new \InvalidArgumentException('Tag name cannot be empty');
        }

        if (mb_strlen($name) > 100) {
            throw new \InvalidArgumentException('Tag name cannot exceed 100 characters');
        }

        $slug = $this->slugify($name);

        // Try to insert, ignore if exists
        $stmt = $this->db->prepare(
            "INSERT IGNORE INTO {$this->table} (name, slug) VALUES (?, ?)"
        );
        $stmt->execute([$name, $slug]);

        // Fetch the tag (either just inserted or already existing)
        return $this->findBySlug($slug);
    }

    /**
     * Find a tag by slug
     * 
     * @param string $slug Tag slug
     * @return array|null Tag data or null if not found
     */
    public function findBySlug(string $slug): ?array
    {
        $stmt = $this->db->prepare(
            "SELECT id, name, slug, created_at, updated_at FROM {$this->table} WHERE slug = ?"
        );
        $stmt->execute([$slug]);
        $result = $stmt->fetch();

        return $result ?: null;
    }

    /**
     * Search tags by name (for autocomplete)
     * 
     * @param string $query Search query
     * @param int $limit Maximum results
     * @return array Array of tags
     */
    public function search(string $query, int $limit = 20): array
    {
        $query = trim($query);
        if (empty($query)) {
            return [];
        }

        $stmt = $this->db->prepare(
            "SELECT t.id, t.name, t.slug, COUNT(et.entry_id) as entry_count
             FROM {$this->table} t
             LEFT JOIN {$this->junctionTable} et ON t.id = et.tag_id
             WHERE t.name LIKE ?
             GROUP BY t.id
             ORDER BY entry_count DESC, t.name ASC
             LIMIT ?"
        );
        $stmt->execute(['%' . $query . '%', $limit]);

        return $stmt->fetchAll();
    }

    /**
     * Get all tags with entry counts
     * 
     * @return array Array of tags with entry_count
     */
    public function getAllWithCounts(): array
    {
        $stmt = $this->db->prepare(
            "SELECT t.id, t.name, t.slug, COUNT(et.entry_id) as entry_count
             FROM {$this->table} t
             LEFT JOIN {$this->junctionTable} et ON t.id = et.tag_id
             GROUP BY t.id
             ORDER BY entry_count DESC, t.name ASC"
        );
        $stmt->execute();

        return $stmt->fetchAll();
    }

    /**
     * Get tags for a single entry
     * 
     * @param int $entryId Entry ID
     * @return array Array of tags
     */
    public function getTagsForEntry(int $entryId): array
    {
        $stmt = $this->db->prepare(
            "SELECT t.id, t.name, t.slug
             FROM {$this->table} t
             JOIN {$this->junctionTable} et ON t.id = et.tag_id
             WHERE et.entry_id = ?
             ORDER BY t.name ASC"
        );
        $stmt->execute([$entryId]);

        return $stmt->fetchAll();
    }

    /**
     * Batch fetch tags for multiple entries
     * Returns array with entry_id as key and array of tags as value
     * 
     * @param array $entryIds Array of entry IDs
     * @return array ['entry_id' => [tags...]]
     */
    public function getTagsForEntries(array $entryIds): array
    {
        if (empty($entryIds)) {
            return [];
        }

        $placeholders = implode(',', array_fill(0, count($entryIds), '?'));
        
        $stmt = $this->db->prepare(
            "SELECT et.entry_id, t.id, t.name, t.slug
             FROM {$this->junctionTable} et
             JOIN {$this->table} t ON et.tag_id = t.id
             WHERE et.entry_id IN ($placeholders)
             ORDER BY et.entry_id, t.name ASC"
        );
        $stmt->execute($entryIds);

        $results = [];
        while ($row = $stmt->fetch()) {
            $entryId = (int) $row['entry_id'];
            if (!isset($results[$entryId])) {
                $results[$entryId] = [];
            }
            $results[$entryId][] = [
                'id' => (int) $row['id'],
                'name' => $row['name'],
                'slug' => $row['slug']
            ];
        }

        return $results;
    }

    /**
     * Replace all tags for an entry (idempotent)
     * 
     * @param int $entryId Entry ID
     * @param array $tagNames Array of tag names
     * @return array Final array of tags for the entry
     */
    public function setTagsForEntry(int $entryId, array $tagNames): array
    {
        // Start transaction
        $this->db->beginTransaction();

        try {
            // Delete existing tags
            $stmt = $this->db->prepare(
                "DELETE FROM {$this->junctionTable} WHERE entry_id = ?"
            );
            $stmt->execute([$entryId]);

            // If no tags provided, we're done
            if (empty($tagNames)) {
                $this->db->commit();
                return [];
            }

            // Find or create all tags
            $tags = [];
            foreach ($tagNames as $tagName) {
                $tagName = trim($tagName);
                if (!empty($tagName)) {
                    $tags[] = $this->findOrCreate($tagName);
                }
            }

            // Insert junction records
            if (!empty($tags)) {
                $values = [];
                $params = [];
                foreach ($tags as $tag) {
                    $values[] = '(?, ?)';
                    $params[] = $entryId;
                    $params[] = $tag['id'];
                }

                $stmt = $this->db->prepare(
                    "INSERT IGNORE INTO {$this->junctionTable} (entry_id, tag_id) VALUES " . implode(', ', $values)
                );
                $stmt->execute($params);
            }

            $this->db->commit();

            // Return the final tag list
            return $this->getTagsForEntry($entryId);

        } catch (\Exception $e) {
            $this->db->rollBack();
            throw $e;
        }
    }

    /**
     * Add a single tag to an entry (idempotent)
     * 
     * @param int $entryId Entry ID
     * @param string $tagName Tag name
     * @return bool Success status
     */
    public function addTagToEntry(int $entryId, string $tagName): bool
    {
        $tag = $this->findOrCreate($tagName);

        $stmt = $this->db->prepare(
            "INSERT IGNORE INTO {$this->junctionTable} (entry_id, tag_id) VALUES (?, ?)"
        );

        return $stmt->execute([$entryId, $tag['id']]);
    }

    /**
     * Remove a tag from an entry
     * 
     * @param int $entryId Entry ID
     * @param int $tagId Tag ID
     * @return bool Success status
     */
    public function removeTagFromEntry(int $entryId, int $tagId): bool
    {
        $stmt = $this->db->prepare(
            "DELETE FROM {$this->junctionTable} WHERE entry_id = ? AND tag_id = ?"
        );

        return $stmt->execute([$entryId, $tagId]);
    }

    /**
     * Get entry IDs for a specific tag (for pagination)
     * 
     * @param int $tagId Tag ID
     * @param int $limit Maximum results
     * @param string|null $before Cursor for pagination (created_at timestamp)
     * @return array Array of entry IDs
     */
    public function getEntryIdsByTag(int $tagId, int $limit, ?string $before): array
    {
        if ($before !== null) {
            $stmt = $this->db->prepare(
                "SELECT et.entry_id
                 FROM {$this->junctionTable} et
                 WHERE et.tag_id = ? AND et.created_at < ?
                 ORDER BY et.created_at DESC
                 LIMIT ?"
            );
            $stmt->execute([$tagId, $before, $limit]);
        } else {
            $stmt = $this->db->prepare(
                "SELECT et.entry_id
                 FROM {$this->junctionTable} et
                 WHERE et.tag_id = ?
                 ORDER BY et.created_at DESC
                 LIMIT ?"
            );
            $stmt->execute([$tagId, $limit]);
        }

        $results = [];
        while ($row = $stmt->fetch()) {
            $results[] = (int) $row['entry_id'];
        }

        return $results;
    }

    /**
     * Find a tag by ID
     * 
     * @param int $id Tag ID
     * @return array|null Tag data or null if not found
     */
    public function findById(int $id): ?array
    {
        $stmt = $this->db->prepare(
            "SELECT id, name, slug, created_at, updated_at FROM {$this->table} WHERE id = ?"
        );
        $stmt->execute([$id]);
        $result = $stmt->fetch();

        return $result ?: null;
    }

    /**
     * Update a tag's name
     * 
     * @param int $id Tag ID
     * @param string $name New tag name
     * @return array|null Updated tag data or null if not found
     */
    public function update(int $id, string $name): ?array
    {
        $name = trim($name);
        if (empty($name)) {
            throw new \InvalidArgumentException('Tag name cannot be empty');
        }

        if (mb_strlen($name) > 100) {
            throw new \InvalidArgumentException('Tag name cannot exceed 100 characters');
        }

        $slug = $this->slugify($name);

        // Check if another tag with this slug already exists
        $stmt = $this->db->prepare(
            "SELECT id FROM {$this->table} WHERE slug = ? AND id != ?"
        );
        $stmt->execute([$slug, $id]);
        if ($stmt->fetch()) {
            throw new \InvalidArgumentException('A tag with this name already exists');
        }

        $stmt = $this->db->prepare(
            "UPDATE {$this->table} SET name = ?, slug = ?, updated_at = NOW() WHERE id = ?"
        );
        $stmt->execute([$name, $slug, $id]);

        return $this->findById($id);
    }

    /**
     * Delete a tag and all its associations
     * 
     * @param int $id Tag ID
     * @return bool Success status
     */
    public function delete(int $id): bool
    {
        // Junction table entries will be deleted automatically due to CASCADE
        $stmt = $this->db->prepare(
            "DELETE FROM {$this->table} WHERE id = ?"
        );
        
        return $stmt->execute([$id]) && $stmt->rowCount() > 0;
    }

    /**
     * Merge one tag into another (reassign all entries, then delete source tag)
     * 
     * @param int $sourceId Tag to merge from (will be deleted)
     * @param int $targetId Tag to merge into
     * @return int Number of entries reassigned
     */
    public function merge(int $sourceId, int $targetId): int
    {
        if ($sourceId === $targetId) {
            throw new \InvalidArgumentException('Cannot merge a tag into itself');
        }

        $this->db->beginTransaction();

        try {
            // Update entry_tags to point to target tag (ignore duplicates)
            $stmt = $this->db->prepare(
                "UPDATE IGNORE {$this->junctionTable} SET tag_id = ? WHERE tag_id = ?"
            );
            $stmt->execute([$targetId, $sourceId]);
            $reassigned = $stmt->rowCount();

            // Delete any remaining associations (duplicates that couldn't be reassigned)
            $stmt = $this->db->prepare(
                "DELETE FROM {$this->junctionTable} WHERE tag_id = ?"
            );
            $stmt->execute([$sourceId]);

            // Delete the source tag
            $this->delete($sourceId);

            $this->db->commit();

            return $reassigned;

        } catch (\Exception $e) {
            $this->db->rollBack();
            throw $e;
        }
    }

    /**
     * Slugify a tag name
     * Converts to lowercase, replaces spaces and special chars with hyphens
     * 
     * @param string $name Tag name
     * @return string Slug
     */
    private function slugify(string $name): string
    {
        // Convert to lowercase
        $slug = mb_strtolower($name, 'UTF-8');

        // Replace spaces and special characters with hyphens
        $slug = preg_replace('/[^\p{L}\p{N}]+/u', '-', $slug);

        // Remove leading/trailing hyphens
        $slug = trim($slug, '-');

        // Replace multiple consecutive hyphens with single hyphen
        $slug = preg_replace('/-+/', '-', $slug);

        return $slug;
    }
}

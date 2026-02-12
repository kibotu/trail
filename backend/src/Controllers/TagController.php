<?php

declare(strict_types=1);

namespace Trail\Controllers;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Trail\Database\Database;
use Trail\Models\Tag;
use Trail\Models\Entry;
use Trail\Config\Config;
use Trail\Services\HashIdService;

class TagController
{
    /**
     * List all tags with entry counts
     * GET /api/tags
     * Optional query param: ?search=query
     */
    public static function listTags(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $config = Config::load(__DIR__ . '/../../secrets.yml');
        $db = Database::getInstance($config);
        $tagModel = new Tag($db);

        try {
            $queryParams = $request->getQueryParams();
            $searchQuery = $queryParams['search'] ?? null;

            if ($searchQuery !== null && trim($searchQuery) !== '') {
                $tags = $tagModel->search(trim($searchQuery), 20);
            } else {
                $tags = $tagModel->getAllWithCounts();
            }

            $response->getBody()->write(json_encode(['tags' => $tags]));
            return $response->withHeader('Content-Type', 'application/json');

        } catch (\PDOException $e) {
            error_log("TagController: Database error listing tags - " . $e->getMessage());
            $response->getBody()->write(json_encode(['error' => 'Database error occurred']));
            return $response->withStatus(500)->withHeader('Content-Type', 'application/json');
        } catch (\Exception $e) {
            error_log("TagController: Unexpected error listing tags - " . $e->getMessage());
            $response->getBody()->write(json_encode(['error' => 'An unexpected error occurred']));
            return $response->withStatus(500)->withHeader('Content-Type', 'application/json');
        }
    }

    /**
     * Get tags for a specific entry
     * GET /api/entries/{id}/tags
     */
    public static function getEntryTags(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $hashId = $args['id'] ?? '';
        
        $config = Config::load(__DIR__ . '/../../secrets.yml');
        
        // Decode hash ID
        $hashSalt = $config['app']['entry_hash_salt'] ?? 'default_entry_salt_change_me';
        $hashIdService = new HashIdService($hashSalt);
        $entryId = $hashIdService->decode($hashId);
        
        if ($entryId === null) {
            $response->getBody()->write(json_encode(['error' => 'Invalid entry ID']));
            return $response->withStatus(400)->withHeader('Content-Type', 'application/json');
        }

        $db = Database::getInstance($config);
        
        // Verify entry exists
        $entryModel = new Entry($db);
        $entry = $entryModel->findById($entryId);
        
        if (!$entry) {
            $response->getBody()->write(json_encode(['error' => 'Entry not found']));
            return $response->withStatus(404)->withHeader('Content-Type', 'application/json');
        }

        try {
            $tagModel = new Tag($db);
            $tags = $tagModel->getTagsForEntry($entryId);

            $response->getBody()->write(json_encode(['tags' => $tags]));
            return $response->withHeader('Content-Type', 'application/json');

        } catch (\PDOException $e) {
            error_log("TagController: Database error getting entry tags - " . $e->getMessage());
            $response->getBody()->write(json_encode(['error' => 'Database error occurred']));
            return $response->withStatus(500)->withHeader('Content-Type', 'application/json');
        } catch (\Exception $e) {
            error_log("TagController: Unexpected error getting entry tags - " . $e->getMessage());
            $response->getBody()->write(json_encode(['error' => 'An unexpected error occurred']));
            return $response->withStatus(500)->withHeader('Content-Type', 'application/json');
        }
    }

    /**
     * Replace all tags for an entry
     * PUT /api/entries/{id}/tags
     * Body: {"tags": ["tag1", "tag2", ...]}
     */
    public static function setEntryTags(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        // Authentication required
        $userId = $request->getAttribute('user_id');
        $isAdmin = $request->getAttribute('is_admin') ?? false;
        
        if (!$userId) {
            $response->getBody()->write(json_encode(['error' => 'Authentication required']));
            return $response->withStatus(401)->withHeader('Content-Type', 'application/json');
        }

        $hashId = $args['id'] ?? '';
        
        $config = Config::load(__DIR__ . '/../../secrets.yml');
        
        // Decode hash ID
        $hashSalt = $config['app']['entry_hash_salt'] ?? 'default_entry_salt_change_me';
        $hashIdService = new HashIdService($hashSalt);
        $entryId = $hashIdService->decode($hashId);
        
        if ($entryId === null) {
            $response->getBody()->write(json_encode(['error' => 'Invalid entry ID']));
            return $response->withStatus(400)->withHeader('Content-Type', 'application/json');
        }

        // Parse request body
        $data = json_decode((string) $request->getBody(), true);
        if (!$data || !isset($data['tags'])) {
            $response->getBody()->write(json_encode(['error' => 'Tags array is required']));
            return $response->withStatus(400)->withHeader('Content-Type', 'application/json');
        }

        if (!is_array($data['tags'])) {
            $response->getBody()->write(json_encode(['error' => 'Tags must be an array']));
            return $response->withStatus(400)->withHeader('Content-Type', 'application/json');
        }

        $db = Database::getInstance($config);
        
        // Verify entry exists and check ownership
        $entryModel = new Entry($db);
        $entry = $entryModel->findById($entryId);
        
        if (!$entry) {
            $response->getBody()->write(json_encode(['error' => 'Entry not found']));
            return $response->withStatus(404)->withHeader('Content-Type', 'application/json');
        }

        // Check ownership
        if ((int) $entry['user_id'] !== (int) $userId && !$isAdmin) {
            $response->getBody()->write(json_encode(['error' => 'You can only modify tags on your own entries']));
            return $response->withStatus(403)->withHeader('Content-Type', 'application/json');
        }

        try {
            $tagModel = new Tag($db);
            $tags = $tagModel->setTagsForEntry($entryId, $data['tags']);

            $response->getBody()->write(json_encode(['tags' => $tags]));
            return $response->withHeader('Content-Type', 'application/json');

        } catch (\InvalidArgumentException $e) {
            $response->getBody()->write(json_encode(['error' => $e->getMessage()]));
            return $response->withStatus(400)->withHeader('Content-Type', 'application/json');
        } catch (\PDOException $e) {
            error_log("TagController: Database error setting entry tags - " . $e->getMessage());
            $response->getBody()->write(json_encode(['error' => 'Database error occurred']));
            return $response->withStatus(500)->withHeader('Content-Type', 'application/json');
        } catch (\Exception $e) {
            error_log("TagController: Unexpected error setting entry tags - " . $e->getMessage());
            $response->getBody()->write(json_encode(['error' => 'An unexpected error occurred']));
            return $response->withStatus(500)->withHeader('Content-Type', 'application/json');
        }
    }

    /**
     * Add a single tag to an entry
     * POST /api/entries/{id}/tags
     * Body: {"tag": "tagname"}
     */
    public static function addEntryTag(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        // Authentication required
        $userId = $request->getAttribute('user_id');
        $isAdmin = $request->getAttribute('is_admin') ?? false;
        
        if (!$userId) {
            $response->getBody()->write(json_encode(['error' => 'Authentication required']));
            return $response->withStatus(401)->withHeader('Content-Type', 'application/json');
        }

        $hashId = $args['id'] ?? '';
        
        $config = Config::load(__DIR__ . '/../../secrets.yml');
        
        // Decode hash ID
        $hashSalt = $config['app']['entry_hash_salt'] ?? 'default_entry_salt_change_me';
        $hashIdService = new HashIdService($hashSalt);
        $entryId = $hashIdService->decode($hashId);
        
        if ($entryId === null) {
            $response->getBody()->write(json_encode(['error' => 'Invalid entry ID']));
            return $response->withStatus(400)->withHeader('Content-Type', 'application/json');
        }

        // Parse request body
        $data = json_decode((string) $request->getBody(), true);
        if (!$data || !isset($data['tag'])) {
            $response->getBody()->write(json_encode(['error' => 'Tag name is required']));
            return $response->withStatus(400)->withHeader('Content-Type', 'application/json');
        }

        $db = Database::getInstance($config);
        
        // Verify entry exists and check ownership
        $entryModel = new Entry($db);
        $entry = $entryModel->findById($entryId);
        
        if (!$entry) {
            $response->getBody()->write(json_encode(['error' => 'Entry not found']));
            return $response->withStatus(404)->withHeader('Content-Type', 'application/json');
        }

        // Check ownership
        if ((int) $entry['user_id'] !== (int) $userId && !$isAdmin) {
            $response->getBody()->write(json_encode(['error' => 'You can only modify tags on your own entries']));
            return $response->withStatus(403)->withHeader('Content-Type', 'application/json');
        }

        try {
            $tagModel = new Tag($db);
            $success = $tagModel->addTagToEntry($entryId, $data['tag']);

            if (!$success) {
                error_log("TagController: Failed to add tag for entry $entryId");
                $response->getBody()->write(json_encode(['error' => 'Failed to add tag']));
                return $response->withStatus(500)->withHeader('Content-Type', 'application/json');
            }

            // Return updated tag list
            $tags = $tagModel->getTagsForEntry($entryId);
            $response->getBody()->write(json_encode(['tags' => $tags]));
            return $response->withHeader('Content-Type', 'application/json');

        } catch (\InvalidArgumentException $e) {
            $response->getBody()->write(json_encode(['error' => $e->getMessage()]));
            return $response->withStatus(400)->withHeader('Content-Type', 'application/json');
        } catch (\PDOException $e) {
            error_log("TagController: Database error adding entry tag - " . $e->getMessage());
            $response->getBody()->write(json_encode(['error' => 'Database error occurred']));
            return $response->withStatus(500)->withHeader('Content-Type', 'application/json');
        } catch (\Exception $e) {
            error_log("TagController: Unexpected error adding entry tag - " . $e->getMessage());
            $response->getBody()->write(json_encode(['error' => 'An unexpected error occurred']));
            return $response->withStatus(500)->withHeader('Content-Type', 'application/json');
        }
    }

    /**
     * Remove a tag from an entry
     * DELETE /api/entries/{id}/tags/{slug}
     */
    public static function removeEntryTag(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        // Authentication required
        $userId = $request->getAttribute('user_id');
        $isAdmin = $request->getAttribute('is_admin') ?? false;
        
        if (!$userId) {
            $response->getBody()->write(json_encode(['error' => 'Authentication required']));
            return $response->withStatus(401)->withHeader('Content-Type', 'application/json');
        }

        $hashId = $args['id'] ?? '';
        $tagSlug = $args['slug'] ?? '';
        
        $config = Config::load(__DIR__ . '/../../secrets.yml');
        
        // Decode hash ID
        $hashSalt = $config['app']['entry_hash_salt'] ?? 'default_entry_salt_change_me';
        $hashIdService = new HashIdService($hashSalt);
        $entryId = $hashIdService->decode($hashId);
        
        if ($entryId === null) {
            $response->getBody()->write(json_encode(['error' => 'Invalid entry ID']));
            return $response->withStatus(400)->withHeader('Content-Type', 'application/json');
        }

        $db = Database::getInstance($config);
        
        // Verify entry exists and check ownership
        $entryModel = new Entry($db);
        $entry = $entryModel->findById($entryId);
        
        if (!$entry) {
            $response->getBody()->write(json_encode(['error' => 'Entry not found']));
            return $response->withStatus(404)->withHeader('Content-Type', 'application/json');
        }

        // Check ownership
        if ((int) $entry['user_id'] !== (int) $userId && !$isAdmin) {
            $response->getBody()->write(json_encode(['error' => 'You can only modify tags on your own entries']));
            return $response->withStatus(403)->withHeader('Content-Type', 'application/json');
        }

        try {
            $tagModel = new Tag($db);
            
            // Find tag by slug
            $tag = $tagModel->findBySlug($tagSlug);
            if (!$tag) {
                $response->getBody()->write(json_encode(['error' => 'Tag not found']));
                return $response->withStatus(404)->withHeader('Content-Type', 'application/json');
            }

            $success = $tagModel->removeTagFromEntry($entryId, (int) $tag['id']);

            $response->getBody()->write(json_encode(['success' => $success]));
            return $response->withHeader('Content-Type', 'application/json');

        } catch (\PDOException $e) {
            error_log("TagController: Database error removing entry tag - " . $e->getMessage());
            $response->getBody()->write(json_encode(['error' => 'Database error occurred']));
            return $response->withStatus(500)->withHeader('Content-Type', 'application/json');
        } catch (\Exception $e) {
            error_log("TagController: Unexpected error removing entry tag - " . $e->getMessage());
            $response->getBody()->write(json_encode(['error' => 'An unexpected error occurred']));
            return $response->withStatus(500)->withHeader('Content-Type', 'application/json');
        }
    }

    /**
     * Get entries by tag
     * GET /api/tags/{slug}/entries
     * Query params: ?limit=20&before=cursor
     */
    public static function getEntriesByTag(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $tagSlug = $args['slug'] ?? '';
        
        $config = Config::load(__DIR__ . '/../../secrets.yml');
        $db = Database::getInstance($config);

        try {
            $tagModel = new Tag($db);
            
            // Find tag by slug
            $tag = $tagModel->findBySlug($tagSlug);
            if (!$tag) {
                $response->getBody()->write(json_encode(['error' => 'Tag not found']));
                return $response->withStatus(404)->withHeader('Content-Type', 'application/json');
            }

            // Get pagination params
            $queryParams = $request->getQueryParams();
            $limit = min(100, max(1, (int) ($queryParams['limit'] ?? 20)));
            $before = $queryParams['before'] ?? null;

            // Get entry IDs for this tag
            $entryIds = $tagModel->getEntryIdsByTag((int) $tag['id'], $limit, $before);

            if (empty($entryIds)) {
                $response->getBody()->write(json_encode([
                    'entries' => [],
                    'has_more' => false,
                    'next_cursor' => null,
                    'limit' => $limit,
                    'tag' => [
                        'name' => $tag['name'],
                        'slug' => $tag['slug']
                    ]
                ]));
                return $response->withHeader('Content-Type', 'application/json');
            }

            // Get full entry data
            $entryModel = new Entry($db);
            $entries = [];
            
            // Get optional current user ID for personalized data
            $currentUserId = $request->getAttribute('user_id');
            
            foreach ($entryIds as $entryId) {
                $entry = $entryModel->findByIdWithImages($entryId, $currentUserId);
                if ($entry) {
                    // Add hash ID
                    $hashSalt = $config['app']['entry_hash_salt'] ?? 'default_entry_salt_change_me';
                    $hashIdService = new HashIdService($hashSalt);
                    $entry['hash_id'] = $hashIdService->encode($entry['id']);
                    
                    $entries[] = $entry;
                }
            }

            $hasMore = count($entries) === $limit;
            $nextCursor = $hasMore ? end($entries)['created_at'] : null;

            $response->getBody()->write(json_encode([
                'entries' => $entries,
                'has_more' => $hasMore,
                'next_cursor' => $nextCursor,
                'limit' => $limit,
                'tag' => [
                    'name' => $tag['name'],
                    'slug' => $tag['slug']
                ]
            ]));
            return $response->withHeader('Content-Type', 'application/json');

        } catch (\PDOException $e) {
            error_log("TagController: Database error getting entries by tag - " . $e->getMessage());
            $response->getBody()->write(json_encode(['error' => 'Database error occurred']));
            return $response->withStatus(500)->withHeader('Content-Type', 'application/json');
        } catch (\Exception $e) {
            error_log("TagController: Unexpected error getting entries by tag - " . $e->getMessage());
            $response->getBody()->write(json_encode(['error' => 'An unexpected error occurred']));
            return $response->withStatus(500)->withHeader('Content-Type', 'application/json');
        }
    }

    /**
     * List all tags with entry counts (admin)
     * GET /api/admin/tags
     * Optional query params: ?search=query
     */
    public static function adminListTags(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        // Admin authentication is handled by middleware
        $config = Config::load(__DIR__ . '/../../secrets.yml');
        $db = Database::getInstance($config);
        $tagModel = new Tag($db);

        try {
            $queryParams = $request->getQueryParams();
            $searchQuery = $queryParams['search'] ?? null;

            if ($searchQuery !== null && trim($searchQuery) !== '') {
                $tags = $tagModel->search(trim($searchQuery), 100);
            } else {
                $tags = $tagModel->getAllWithCounts();
            }

            $response->getBody()->write(json_encode(['tags' => $tags]));
            return $response->withHeader('Content-Type', 'application/json');

        } catch (\PDOException $e) {
            error_log("TagController: Database error listing admin tags - " . $e->getMessage());
            $response->getBody()->write(json_encode(['error' => 'Database error occurred']));
            return $response->withStatus(500)->withHeader('Content-Type', 'application/json');
        } catch (\Exception $e) {
            error_log("TagController: Unexpected error listing admin tags - " . $e->getMessage());
            $response->getBody()->write(json_encode(['error' => 'An unexpected error occurred']));
            return $response->withStatus(500)->withHeader('Content-Type', 'application/json');
        }
    }

    /**
     * Update a tag (admin only)
     * PUT /api/admin/tags/{id}
     * Body: {"name": "new tag name"}
     */
    public static function adminUpdateTag(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $tagId = (int) ($args['id'] ?? 0);
        
        if ($tagId <= 0) {
            $response->getBody()->write(json_encode(['error' => 'Invalid tag ID']));
            return $response->withStatus(400)->withHeader('Content-Type', 'application/json');
        }

        // Parse request body
        $data = json_decode((string) $request->getBody(), true);
        if (!$data || !isset($data['name'])) {
            $response->getBody()->write(json_encode(['error' => 'Name is required']));
            return $response->withStatus(400)->withHeader('Content-Type', 'application/json');
        }

        $config = Config::load(__DIR__ . '/../../secrets.yml');
        $db = Database::getInstance($config);
        $tagModel = new Tag($db);

        try {
            // Check if tag exists
            $tag = $tagModel->findById($tagId);
            if (!$tag) {
                $response->getBody()->write(json_encode(['error' => 'Tag not found']));
                return $response->withStatus(404)->withHeader('Content-Type', 'application/json');
            }

            $updatedTag = $tagModel->update($tagId, $data['name']);

            $response->getBody()->write(json_encode(['tag' => $updatedTag]));
            return $response->withHeader('Content-Type', 'application/json');

        } catch (\InvalidArgumentException $e) {
            $response->getBody()->write(json_encode(['error' => $e->getMessage()]));
            return $response->withStatus(400)->withHeader('Content-Type', 'application/json');
        } catch (\PDOException $e) {
            error_log("TagController: Database error updating tag - " . $e->getMessage());
            $response->getBody()->write(json_encode(['error' => 'Database error occurred']));
            return $response->withStatus(500)->withHeader('Content-Type', 'application/json');
        } catch (\Exception $e) {
            error_log("TagController: Unexpected error updating tag - " . $e->getMessage());
            $response->getBody()->write(json_encode(['error' => 'An unexpected error occurred']));
            return $response->withStatus(500)->withHeader('Content-Type', 'application/json');
        }
    }

    /**
     * Delete a tag (admin only)
     * DELETE /api/admin/tags/{id}
     */
    public static function adminDeleteTag(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $tagId = (int) ($args['id'] ?? 0);
        
        if ($tagId <= 0) {
            $response->getBody()->write(json_encode(['error' => 'Invalid tag ID']));
            return $response->withStatus(400)->withHeader('Content-Type', 'application/json');
        }

        $config = Config::load(__DIR__ . '/../../secrets.yml');
        $db = Database::getInstance($config);
        $tagModel = new Tag($db);

        try {
            // Check if tag exists
            $tag = $tagModel->findById($tagId);
            if (!$tag) {
                $response->getBody()->write(json_encode(['error' => 'Tag not found']));
                return $response->withStatus(404)->withHeader('Content-Type', 'application/json');
            }

            $success = $tagModel->delete($tagId);

            $response->getBody()->write(json_encode([
                'success' => $success,
                'message' => $success ? 'Tag deleted successfully' : 'Failed to delete tag'
            ]));
            return $response->withHeader('Content-Type', 'application/json');

        } catch (\PDOException $e) {
            error_log("TagController: Database error deleting tag - " . $e->getMessage());
            $response->getBody()->write(json_encode(['error' => 'Database error occurred']));
            return $response->withStatus(500)->withHeader('Content-Type', 'application/json');
        } catch (\Exception $e) {
            error_log("TagController: Unexpected error deleting tag - " . $e->getMessage());
            $response->getBody()->write(json_encode(['error' => 'An unexpected error occurred']));
            return $response->withStatus(500)->withHeader('Content-Type', 'application/json');
        }
    }

    /**
     * Merge one tag into another (admin only)
     * POST /api/admin/tags/{id}/merge
     * Body: {"target_id": 123}
     */
    public static function adminMergeTag(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $sourceId = (int) ($args['id'] ?? 0);
        
        if ($sourceId <= 0) {
            $response->getBody()->write(json_encode(['error' => 'Invalid tag ID']));
            return $response->withStatus(400)->withHeader('Content-Type', 'application/json');
        }

        // Parse request body
        $data = json_decode((string) $request->getBody(), true);
        if (!$data || !isset($data['target_id'])) {
            $response->getBody()->write(json_encode(['error' => 'target_id is required']));
            return $response->withStatus(400)->withHeader('Content-Type', 'application/json');
        }

        $targetId = (int) $data['target_id'];
        if ($targetId <= 0) {
            $response->getBody()->write(json_encode(['error' => 'Invalid target tag ID']));
            return $response->withStatus(400)->withHeader('Content-Type', 'application/json');
        }

        $config = Config::load(__DIR__ . '/../../secrets.yml');
        $db = Database::getInstance($config);
        $tagModel = new Tag($db);

        try {
            // Check if both tags exist
            $sourceTag = $tagModel->findById($sourceId);
            if (!$sourceTag) {
                $response->getBody()->write(json_encode(['error' => 'Source tag not found']));
                return $response->withStatus(404)->withHeader('Content-Type', 'application/json');
            }

            $targetTag = $tagModel->findById($targetId);
            if (!$targetTag) {
                $response->getBody()->write(json_encode(['error' => 'Target tag not found']));
                return $response->withStatus(404)->withHeader('Content-Type', 'application/json');
            }

            $reassigned = $tagModel->merge($sourceId, $targetId);

            $response->getBody()->write(json_encode([
                'success' => true,
                'reassigned' => $reassigned,
                'message' => "Merged '{$sourceTag['name']}' into '{$targetTag['name']}' ($reassigned entries reassigned)"
            ]));
            return $response->withHeader('Content-Type', 'application/json');

        } catch (\InvalidArgumentException $e) {
            $response->getBody()->write(json_encode(['error' => $e->getMessage()]));
            return $response->withStatus(400)->withHeader('Content-Type', 'application/json');
        } catch (\PDOException $e) {
            error_log("TagController: Database error merging tag - " . $e->getMessage());
            $response->getBody()->write(json_encode(['error' => 'Database error occurred']));
            return $response->withStatus(500)->withHeader('Content-Type', 'application/json');
        } catch (\Exception $e) {
            error_log("TagController: Unexpected error merging tag - " . $e->getMessage());
            $response->getBody()->write(json_encode(['error' => 'An unexpected error occurred']));
            return $response->withStatus(500)->withHeader('Content-Type', 'application/json');
        }
    }

    /**
     * Batch set tags for multiple entries (admin only)
     * PUT /api/admin/entries/tags
     * Body: {"entries": [{"id": "hashId", "tags": ["tag1", "tag2"]}, ...]}
     */
    public static function batchSetTags(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        // Admin authentication required
        $isAdmin = $request->getAttribute('is_admin') ?? false;
        
        if (!$isAdmin) {
            $response->getBody()->write(json_encode(['error' => 'Admin privileges required']));
            return $response->withStatus(403)->withHeader('Content-Type', 'application/json');
        }

        // Parse request body
        $data = json_decode((string) $request->getBody(), true);
        if (!$data || !isset($data['entries'])) {
            $response->getBody()->write(json_encode(['error' => 'Entries array is required']));
            return $response->withStatus(400)->withHeader('Content-Type', 'application/json');
        }

        if (!is_array($data['entries'])) {
            $response->getBody()->write(json_encode(['error' => 'Entries must be an array']));
            return $response->withStatus(400)->withHeader('Content-Type', 'application/json');
        }

        if (count($data['entries']) > 100) {
            $response->getBody()->write(json_encode(['error' => 'Maximum 100 entries per batch']));
            return $response->withStatus(400)->withHeader('Content-Type', 'application/json');
        }

        $config = Config::load(__DIR__ . '/../../secrets.yml');
        $db = Database::getInstance($config);
        
        $hashSalt = $config['app']['entry_hash_salt'] ?? 'default_entry_salt_change_me';
        $hashIdService = new HashIdService($hashSalt);
        
        $tagModel = new Tag($db);
        $entryModel = new Entry($db);
        
        $processed = 0;
        $errors = [];

        foreach ($data['entries'] as $index => $entryData) {
            try {
                if (!isset($entryData['id']) || !isset($entryData['tags'])) {
                    $errors[] = [
                        'index' => $index,
                        'error' => 'Missing id or tags field'
                    ];
                    continue;
                }

                if (!is_array($entryData['tags'])) {
                    $errors[] = [
                        'index' => $index,
                        'id' => $entryData['id'],
                        'error' => 'Tags must be an array'
                    ];
                    continue;
                }

                // Decode hash ID
                $entryId = $hashIdService->decode($entryData['id']);
                if ($entryId === null) {
                    $errors[] = [
                        'index' => $index,
                        'id' => $entryData['id'],
                        'error' => 'Invalid entry ID'
                    ];
                    continue;
                }

                // Verify entry exists
                $entry = $entryModel->findById($entryId);
                if (!$entry) {
                    $errors[] = [
                        'index' => $index,
                        'id' => $entryData['id'],
                        'error' => 'Entry not found'
                    ];
                    continue;
                }

                // Set tags
                $tagModel->setTagsForEntry($entryId, $entryData['tags']);
                $processed++;

            } catch (\Exception $e) {
                error_log("TagController: Error in batch processing entry at index $index - " . $e->getMessage());
                $errors[] = [
                    'index' => $index,
                    'id' => $entryData['id'] ?? null,
                    'error' => $e->getMessage()
                ];
            }
        }

        $response->getBody()->write(json_encode([
            'success' => true,
            'processed' => $processed,
            'errors' => $errors
        ]));
        return $response->withHeader('Content-Type', 'application/json');
    }
}

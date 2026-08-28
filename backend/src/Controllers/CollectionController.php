<?php

declare(strict_types=1);

namespace Trail\Controllers;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Trail\Database\Database;
use Trail\Models\Collection;
use Trail\Models\Entry;
use Trail\Services\HashIdService;
use Trail\Services\RssGenerator;
use Trail\Config\Config;

class CollectionController
{
    /**
     * List all collections.
     * GET /api/collections
     */
    public static function list(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $config = Config::load(__DIR__ . '/../../secrets.yml');
        $db = Database::getInstance($config);
        $collectionModel = new Collection($db);

        try {
            $response->getBody()->write(json_encode(['collections' => $collectionModel->getAll()]));
            return $response->withHeader('Content-Type', 'application/json');
        } catch (\PDOException $e) {
            error_log("CollectionController: Database error listing collections - " . $e->getMessage());
            $response->getBody()->write(json_encode(['error' => 'Database error occurred']));
            return $response->withStatus(500)->withHeader('Content-Type', 'application/json');
        }
    }

    /**
     * Collection metadata.
     * GET /api/collections/{slug}
     */
    public static function get(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $slug = $args['slug'] ?? '';
        $config = Config::load(__DIR__ . '/../../secrets.yml');
        $db = Database::getInstance($config);
        $collectionModel = new Collection($db);

        try {
            $collection = $collectionModel->findBySlug($slug);
            if (!$collection) {
                $response->getBody()->write(json_encode(['error' => 'Collection not found']));
                return $response->withStatus(404)->withHeader('Content-Type', 'application/json');
            }

            $collection['tags'] = $collectionModel->getTags((int) $collection['id']);

            $response->getBody()->write(json_encode(['collection' => $collection]));
            return $response->withHeader('Content-Type', 'application/json');
        } catch (\PDOException $e) {
            error_log("CollectionController: Database error getting collection - " . $e->getMessage());
            $response->getBody()->write(json_encode(['error' => 'Database error occurred']));
            return $response->withStatus(500)->withHeader('Content-Type', 'application/json');
        }
    }

    /**
     * Paginated entries for a collection.
     * GET /api/collections/{slug}/entries
     */
    public static function getEntries(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $slug = $args['slug'] ?? '';
        $config = Config::load(__DIR__ . '/../../secrets.yml');
        $db = Database::getInstance($config);
        $collectionModel = new Collection($db);

        try {
            $collection = $collectionModel->findBySlug($slug);
            if (!$collection) {
                $response->getBody()->write(json_encode(['error' => 'Collection not found']));
                return $response->withStatus(404)->withHeader('Content-Type', 'application/json');
            }

            $queryParams = $request->getQueryParams();
            $limit = min(100, max(1, (int) ($queryParams['limit'] ?? 20)));
            $before = $queryParams['before'] ?? null;
            $searchQuery = $queryParams['q'] ?? null;

            // Logged-in users get the same mute/hide filters as the landing feed
            $auth = EntryController::getOptionalAuth($request, $config);
            $currentUserId = $auth['user_id'];
            $excludeUserId = null;
            $excludeEntryIds = [];
            if ($currentUserId) {
                $reportModel = new \Trail\Models\Report($db);
                $excludeUserId = $currentUserId;
                $excludeEntryIds = $reportModel->getHiddenEntryIds($currentUserId);
            }

            $entries = [];
            if ($searchQuery !== null && trim($searchQuery) !== '') {
                $searchQuery = \Trail\Services\SearchService::sanitize($searchQuery);
                if (\Trail\Services\SearchService::isEmpty($searchQuery) || !\Trail\Services\SearchService::isSafe($searchQuery)) {
                    $entries = [];
                } else {
                    $entries = $collectionModel->getEntries((int) $collection['id'], $limit, $before, $currentUserId, $excludeUserId, $excludeEntryIds, $searchQuery);
                }
            } else {
                $entries = $collectionModel->getEntries((int) $collection['id'], $limit, $before, $currentUserId, $excludeUserId, $excludeEntryIds);
            }

            $entryModel = new Entry($db);
            $entries = $entryModel->attachImagesToEntries($entries);
            $entries = $entryModel->attachTagsToEntries($entries);

            $hasMore = count($entries) === $limit;

            $hashSalt = Config::getEntryHashSalt($config);
            $hashIdService = new HashIdService($hashSalt);

            foreach ($entries as &$entry) {
                $entry['avatar_url'] = self::getAvatarUrl($entry);
                try {
                    $entry['hash_id'] = $hashIdService->encode((int) $entry['id']);
                } catch (\Throwable $e) {
                    $entry['hash_id'] = (string) $entry['id'];
                }
            }
            unset($entry);

            $nextCursor = null;
            if ($hasMore && !empty($entries)) {
                $nextCursor = end($entries)['created_at'];
            }

            $response->getBody()->write(json_encode([
                'entries' => $entries,
                'has_more' => $hasMore,
                'next_cursor' => $nextCursor,
                'limit' => $limit,
                'collection' => [
                    'id' => (int) $collection['id'],
                    'slug' => $collection['slug'],
                    'name' => $collection['name'],
                ],
            ]));
            return $response->withHeader('Content-Type', 'application/json');
        } catch (\PDOException $e) {
            error_log("CollectionController: Database error getting collection entries - " . $e->getMessage());
            $response->getBody()->write(json_encode(['error' => 'Database error occurred']));
            return $response->withStatus(500)->withHeader('Content-Type', 'application/json');
        }
    }

    /**
     * RSS feed scoped to the collection.
     * GET /api/collections/{slug}/rss
     */
    public static function rss(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $slug = $args['slug'] ?? '';
        $config = Config::load(__DIR__ . '/../../secrets.yml');
        $db = Database::getInstance($config);
        $collectionModel = new Collection($db);

        try {
            $collection = $collectionModel->findBySlug($slug);
            if (!$collection) {
                $response->getBody()->write(json_encode(['error' => 'Collection not found']));
                return $response->withStatus(404)->withHeader('Content-Type', 'application/json');
            }

            $entries = $collectionModel->getEntries((int) $collection['id'], 100);

            $baseUrl = $config['app']['base_url'] ?? '';
            $hashSalt = Config::getEntryHashSalt($config);
            $hashIdService = new HashIdService($hashSalt);

            foreach ($entries as &$entry) {
                $entry['user_name'] = $collection['name'];
                try {
                    $entry['hash_id'] = $hashIdService->encode((int) $entry['id']);
                } catch (\Throwable $e) {
                    $entry['hash_id'] = (string) $entry['id'];
                }
            }
            unset($entry);

            $generator = new RssGenerator($config);
            $xml = $generator->generate($entries, null, $collection['name'], $baseUrl . '/collection/' . $collection['slug']);

            $response->getBody()->write($xml);
            return $response->withHeader('Content-Type', 'application/rss+xml; charset=UTF-8');
        } catch (\PDOException $e) {
            error_log("CollectionController: Database error generating collection RSS - " . $e->getMessage());
            $response->getBody()->write(json_encode(['error' => 'Database error occurred']));
            return $response->withStatus(500)->withHeader('Content-Type', 'application/json');
        }
    }

    /**
     * Create a collection (admin).
     * POST /api/admin/collections
     */
    public static function adminCreate(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $data = json_decode((string) $request->getBody(), true);
        $name = $data['name'] ?? '';
        $slug = $data['slug'] ?? '';

        if (empty($name)) {
            $response->getBody()->write(json_encode(['error' => 'Name is required']));
            return $response->withStatus(400)->withHeader('Content-Type', 'application/json');
        }

        $config = Config::load(__DIR__ . '/../../secrets.yml');
        $db = Database::getInstance($config);
        $collectionModel = new Collection($db);

        try {
            $error = Collection::validateSlug($slug);
            if ($error !== null) {
                $response->getBody()->write(json_encode(['error' => $error]));
                return $response->withStatus(400)->withHeader('Content-Type', 'application/json');
            }
            if (in_array($slug, Collection::RESERVED_SLUGS, true)) {
                $response->getBody()->write(json_encode(['error' => 'Slug is reserved']));
                return $response->withStatus(400)->withHeader('Content-Type', 'application/json');
            }
            if ($collectionModel->findBySlug($slug)) {
                $response->getBody()->write(json_encode(['error' => 'Slug is already in use']));
                return $response->withStatus(409)->withHeader('Content-Type', 'application/json');
            }

            $ownerUserId = (int) $request->getAttribute('user_id');
            $id = $collectionModel->create(
                $ownerUserId,
                $name,
                $slug,
                $data['bio'] ?? null,
                isset($data['avatar_image_id']) ? (int) $data['avatar_image_id'] : null,
                isset($data['header_image_id']) ? (int) $data['header_image_id'] : null
            );

            $tagIds = $data['tag_ids'] ?? [];
            if (!empty($tagIds) && is_array($tagIds)) {
                $collectionModel->setTags($id, array_map('intval', $tagIds));
            }

            $response->getBody()->write(json_encode(['collection' => $collectionModel->findById($id)]));
            return $response->withStatus(201)->withHeader('Content-Type', 'application/json');
        } catch (\PDOException $e) {
            error_log("CollectionController: Database error creating collection - " . $e->getMessage());
            $response->getBody()->write(json_encode(['error' => 'Database error occurred']));
            return $response->withStatus(500)->withHeader('Content-Type', 'application/json');
        }
    }

    /**
     * Update a collection (admin).
     * PUT /api/admin/collections/{id}
     */
    public static function adminUpdate(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $id = (int) ($args['id'] ?? 0);
        if ($id <= 0) {
            $response->getBody()->write(json_encode(['error' => 'Invalid collection ID']));
            return $response->withStatus(400)->withHeader('Content-Type', 'application/json');
        }

        $data = json_decode((string) $request->getBody(), true);
        $config = Config::load(__DIR__ . '/../../secrets.yml');
        $db = Database::getInstance($config);
        $collectionModel = new Collection($db);

        try {
            $existing = $collectionModel->findById($id);
            if (!$existing) {
                $response->getBody()->write(json_encode(['error' => 'Collection not found']));
                return $response->withStatus(404)->withHeader('Content-Type', 'application/json');
            }

            if (isset($data['slug'])) {
                $error = Collection::validateSlug($data['slug']);
                if ($error !== null) {
                    $response->getBody()->write(json_encode(['error' => $error]));
                    return $response->withStatus(400)->withHeader('Content-Type', 'application/json');
                }
                if (in_array($data['slug'], Collection::RESERVED_SLUGS, true)) {
                    $response->getBody()->write(json_encode(['error' => 'Slug is reserved']));
                    return $response->withStatus(400)->withHeader('Content-Type', 'application/json');
                }
                $other = $collectionModel->findBySlug($data['slug']);
                if ($other && (int) $other['id'] !== $id) {
                    $response->getBody()->write(json_encode(['error' => 'Slug is already in use']));
                    return $response->withStatus(409)->withHeader('Content-Type', 'application/json');
                }
            }

            $collectionModel->update(
                $id,
                $data['name'] ?? null,
                $data['slug'] ?? null,
                $data['bio'] ?? null,
                isset($data['avatar_image_id']) ? (int) $data['avatar_image_id'] : null,
                isset($data['header_image_id']) ? (int) $data['header_image_id'] : null
            );

            if (array_key_exists('tag_ids', $data)) {
                $tagIds = is_array($data['tag_ids']) ? array_map('intval', $data['tag_ids']) : [];
                $collectionModel->setTags($id, $tagIds);
            }

            $response->getBody()->write(json_encode(['collection' => $collectionModel->findById($id)]));
            return $response->withHeader('Content-Type', 'application/json');
        } catch (\PDOException $e) {
            error_log("CollectionController: Database error updating collection - " . $e->getMessage());
            $response->getBody()->write(json_encode(['error' => 'Database error occurred']));
            return $response->withStatus(500)->withHeader('Content-Type', 'application/json');
        }
    }

    /**
     * Hard delete a collection (admin).
     * DELETE /api/admin/collections/{id}
     */
    public static function adminDelete(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $id = (int) ($args['id'] ?? 0);
        if ($id <= 0) {
            $response->getBody()->write(json_encode(['error' => 'Invalid collection ID']));
            return $response->withStatus(400)->withHeader('Content-Type', 'application/json');
        }

        $config = Config::load(__DIR__ . '/../../secrets.yml');
        $db = Database::getInstance($config);
        $collectionModel = new Collection($db);

        try {
            $success = $collectionModel->delete($id);
            $response->getBody()->write(json_encode(['success' => $success]));
            return $response->withHeader('Content-Type', 'application/json');
        } catch (\PDOException $e) {
            error_log("CollectionController: Database error deleting collection - " . $e->getMessage());
            $response->getBody()->write(json_encode(['error' => 'Database error occurred']));
            return $response->withStatus(500)->withHeader('Content-Type', 'application/json');
        }
    }

    /**
     * Entry avatar URL (Gravatar fallback), shared shape with EntryController.
     */
    private static function getAvatarUrl(array $entry, int $size = 96): string
    {
        if (!empty($entry['photo_url'])) {
            return $entry['photo_url'];
        }
        if (!empty($entry['gravatar_hash'])) {
            return "https://www.gravatar.com/avatar/{$entry['gravatar_hash']}?s={$size}&d=mp";
        }
        if (!empty($entry['user_email'])) {
            $gravatarHash = md5(strtolower(trim($entry['user_email'])));
            return "https://www.gravatar.com/avatar/{$gravatarHash}?s={$size}&d=mp";
        }
        return "https://www.gravatar.com/avatar/00000000000000000000000000000000?s={$size}&d=mp";
    }
}

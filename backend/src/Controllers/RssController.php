<?php

declare(strict_types=1);

namespace Trail\Controllers;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Trail\Database\Database;
use Trail\Models\Entry;
use Trail\Models\User;
use Trail\Services\RssGenerator;
use Trail\Config\Config;

class RssController
{
    public static function globalFeed(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $config = Config::load(__DIR__ . '/../../secrets.yml');
        $db = Database::getInstance($config);
        $entryModel = new Entry($db);

        $entries = $entryModel->getAll(100, null);

        // Attribute claimed entries to their collection; add permalinks for links and guids
        $hashSalt = Config::getEntryHashSalt($config);
        $hashIdService = new \Trail\Services\HashIdService($hashSalt);

        foreach ($entries as &$entry) {
            if (!empty($entry['collection']['name'])) {
                $entry['user_name'] = $entry['collection']['name'];
            }
            try {
                $entry['hash_id'] = $hashIdService->encode((int) $entry['id']);
            } catch (\Throwable $e) {
                $entry['hash_id'] = (string) $entry['id'];
            }
        }
        unset($entry);

        $rssGenerator = new RssGenerator($config);
        $xml = $rssGenerator->generate($entries);

        $response->getBody()->write($xml);
        return $response->withHeader('Content-Type', 'application/rss+xml; charset=UTF-8');
    }

    public static function userFeed(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $userId = (int) $args['user_id'];

        $config = Config::load(__DIR__ . '/../../secrets.yml');
        $db = Database::getInstance($config);
        $entryModel = new Entry($db);

        $entries = $entryModel->getByUser($userId, 100, null);
        $entries = self::attachHashIds($entries, Config::getEntryHashSalt($config));

        $rssGenerator = new RssGenerator($config);
        $xml = $rssGenerator->generate($entries, $userId);

        $response->getBody()->write($xml);
        return $response->withHeader('Content-Type', 'application/rss+xml; charset=UTF-8');
    }

    public static function userFeedByNickname(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $nickname = $args['nickname'] ?? null;

        if (empty($nickname)) {
            $response->getBody()->write(json_encode(['error' => 'Nickname is required']));
            return $response->withStatus(400)->withHeader('Content-Type', 'application/json');
        }

        // Strip @ prefix if present (for URLs like /api/users/@nickname/rss)
        $nickname = ltrim($nickname, '@');

        $config = Config::load(__DIR__ . '/../../secrets.yml');
        $db = Database::getInstance($config);
        
        // Find user by nickname
        $userModel = new User($db);
        $user = $userModel->findByNickname($nickname);

        if (!$user || !empty($user['deletion_requested_at'])) {
            $response->getBody()->write(json_encode(['error' => 'User not found']));
            return $response->withStatus(404)->withHeader('Content-Type', 'application/json');
        }

        // Get entries for this user
        $entryModel = new Entry($db);
        $entries = $entryModel->getByUser($user['id'], 100, null);
        $entries = self::attachHashIds($entries, Config::getEntryHashSalt($config));

        $rssGenerator = new RssGenerator($config);
        $xml = $rssGenerator->generate($entries, $user['id']);

        $response->getBody()->write($xml);
        return $response->withHeader('Content-Type', 'application/rss+xml; charset=UTF-8');
    }

    /**
     * Add hash_id permalinks so RSS guids and link fallbacks are stable.
     */
    private static function attachHashIds(array $entries, string $hashSalt): array
    {
        $hashIdService = new \Trail\Services\HashIdService($hashSalt);
        foreach ($entries as &$entry) {
            try {
                $entry['hash_id'] = $hashIdService->encode((int) $entry['id']);
            } catch (\Throwable $e) {
                $entry['hash_id'] = (string) $entry['id'];
            }
        }
        unset($entry);

        return $entries;
    }
}

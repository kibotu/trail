<?php

declare(strict_types=1);

namespace Trail\Controllers;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Trail\Database\Database;
use Trail\Models\Entry;
use Trail\Config\Config;

class EntryController
{
    public static function create(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $userId = $request->getAttribute('user_id');
        $data = json_decode((string) $request->getBody(), true);
        
        $url = $data['url'] ?? '';
        $message = $data['message'] ?? '';

        // Validation
        if (empty($url) || empty($message)) {
            $response->getBody()->write(json_encode(['error' => 'URL and message are required']));
            return $response->withStatus(400)->withHeader('Content-Type', 'application/json');
        }

        if (!filter_var($url, FILTER_VALIDATE_URL)) {
            $response->getBody()->write(json_encode(['error' => 'Invalid URL format']));
            return $response->withStatus(400)->withHeader('Content-Type', 'application/json');
        }

        if (mb_strlen($message) > 280) {
            $response->getBody()->write(json_encode(['error' => 'Message must be 280 characters or less']));
            return $response->withStatus(400)->withHeader('Content-Type', 'application/json');
        }

        $config = Config::load(__DIR__ . '/../../config.yml');
        $db = Database::getInstance($config);
        $entryModel = new Entry($db);

        $entryId = $entryModel->create($userId, $url, $message);
        $entry = $entryModel->findById($entryId);

        $response->getBody()->write(json_encode([
            'id' => $entryId,
            'created_at' => $entry['created_at'],
        ]));
        
        return $response->withStatus(201)->withHeader('Content-Type', 'application/json');
    }

    public static function list(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $userId = $request->getAttribute('user_id');
        $queryParams = $request->getQueryParams();
        
        $page = max(1, (int) ($queryParams['page'] ?? 1));
        $limit = min(50, max(1, (int) ($queryParams['limit'] ?? 20)));
        $offset = ($page - 1) * $limit;

        $config = Config::load(__DIR__ . '/../../config.yml');
        $db = Database::getInstance($config);
        $entryModel = new Entry($db);

        $entries = $entryModel->getByUser($userId, $limit, $offset);
        $total = $entryModel->countByUser($userId);
        $pages = ceil($total / $limit);

        // Add Gravatar URLs
        foreach ($entries as &$entry) {
            $entry['gravatar_url'] = \Trail\Services\GravatarService::generateUrl($entry['gravatar_hash']);
        }

        $response->getBody()->write(json_encode([
            'entries' => $entries,
            'total' => $total,
            'page' => $page,
            'pages' => $pages,
            'limit' => $limit,
        ]));
        
        return $response->withHeader('Content-Type', 'application/json');
    }
}

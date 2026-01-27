<?php

declare(strict_types=1);

namespace Trail\Controllers;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Trail\Database\Database;
use Trail\Models\Entry;
use Trail\Services\RssGenerator;
use Trail\Config\Config;

class RssController
{
    public static function globalFeed(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $config = Config::load(__DIR__ . '/../../config.yml');
        $db = Database::getInstance($config);
        $entryModel = new Entry($db);

        $entries = $entryModel->getAll(100, null);

        $rssGenerator = new RssGenerator($config);
        $xml = $rssGenerator->generate($entries);

        $response->getBody()->write($xml);
        return $response->withHeader('Content-Type', 'application/rss+xml; charset=UTF-8');
    }

    public static function userFeed(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $userId = (int) $args['user_id'];

        $config = Config::load(__DIR__ . '/../../config.yml');
        $db = Database::getInstance($config);
        $entryModel = new Entry($db);

        $entries = $entryModel->getByUser($userId, 100, null);

        $rssGenerator = new RssGenerator($config);
        $xml = $rssGenerator->generate($entries, $userId);

        $response->getBody()->write($xml);
        return $response->withHeader('Content-Type', 'application/rss+xml; charset=UTF-8');
    }
}

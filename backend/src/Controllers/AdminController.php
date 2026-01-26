<?php

declare(strict_types=1);

namespace Trail\Controllers;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Trail\Database\Database;
use Trail\Models\Entry;
use Trail\Models\User;
use Trail\Config\Config;
use Trail\Services\TextSanitizer;

class AdminController
{
    public static function dashboard(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $config = Config::load(__DIR__ . '/../../config.yml');
        $db = Database::getInstance($config);
        
        $entryModel = new Entry($db);
        $userModel = new User($db);

        $stats = [
            'total_entries' => $entryModel->count(),
            'total_users' => $userModel->count(),
        ];

        // Load template
        $html = self::renderTemplate('dashboard', $stats);
        
        $response->getBody()->write($html);
        return $response->withHeader('Content-Type', 'text/html');
    }

    public static function entries(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $config = Config::load(__DIR__ . '/../../config.yml');
        $db = Database::getInstance($config);
        $entryModel = new Entry($db);

        $entries = $entryModel->getAll(100, 0);

        // Add Gravatar URLs
        foreach ($entries as &$entry) {
            $entry['gravatar_url'] = \Trail\Services\GravatarService::generateUrl($entry['gravatar_hash']);
        }

        $html = self::renderTemplate('entries', ['entries' => $entries]);
        
        $response->getBody()->write($html);
        return $response->withHeader('Content-Type', 'text/html');
    }

    public static function updateEntry(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $entryId = (int) $args['id'];
        $data = json_decode((string) $request->getBody(), true);

        $text = $data['text'] ?? '';

        if (empty($text)) {
            $response->getBody()->write(json_encode(['error' => 'Text is required']));
            return $response->withStatus(400)->withHeader('Content-Type', 'application/json');
        }

        // Validation: Check UTF-8 encoding (for emoji support)
        if (!TextSanitizer::isValidUtf8($text)) {
            $response->getBody()->write(json_encode(['error' => 'Invalid text encoding']));
            return $response->withStatus(400)->withHeader('Content-Type', 'application/json');
        }

        // Validation: Check length before sanitization
        if (mb_strlen($text) > 280) {
            $response->getBody()->write(json_encode(['error' => 'Text must be 280 characters or less']));
            return $response->withStatus(400)->withHeader('Content-Type', 'application/json');
        }

        // Security: Check for dangerous patterns before sanitization
        if (!TextSanitizer::isSafe($text)) {
            $response->getBody()->write(json_encode(['error' => 'Text contains potentially dangerous content']));
            return $response->withStatus(400)->withHeader('Content-Type', 'application/json');
        }

        // Security: Sanitize text to remove scripts while preserving URLs and emojis
        $sanitizedText = TextSanitizer::sanitize($text);

        $config = Config::load(__DIR__ . '/../../config.yml');
        $db = Database::getInstance($config);
        $entryModel = new Entry($db);

        $success = $entryModel->update($entryId, $sanitizedText);

        $response->getBody()->write(json_encode(['success' => $success]));
        return $response->withHeader('Content-Type', 'application/json');
    }

    public static function deleteEntry(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $entryId = (int) $args['id'];

        $config = Config::load(__DIR__ . '/../../config.yml');
        $db = Database::getInstance($config);
        $entryModel = new Entry($db);

        $success = $entryModel->delete($entryId);

        $response->getBody()->write(json_encode(['success' => $success]));
        return $response->withHeader('Content-Type', 'application/json');
    }

    public static function users(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $config = Config::load(__DIR__ . '/../../config.yml');
        $db = Database::getInstance($config);
        $userModel = new User($db);

        $users = $userModel->getAll(100, 0);

        // Add Gravatar URLs
        foreach ($users as &$user) {
            $user['gravatar_url'] = \Trail\Services\GravatarService::generateUrl($user['gravatar_hash'], 200);
        }

        $html = self::renderTemplate('users', ['users' => $users]);
        
        $response->getBody()->write($html);
        return $response->withHeader('Content-Type', 'text/html');
    }

    public static function deleteUser(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $userId = (int) $args['id'];

        $config = Config::load(__DIR__ . '/../../config.yml');
        $db = Database::getInstance($config);
        $userModel = new User($db);

        $success = $userModel->delete($userId);

        $response->getBody()->write(json_encode(['success' => $success]));
        return $response->withHeader('Content-Type', 'application/json');
    }

    private static function renderTemplate(string $name, array $data = []): string
    {
        $templatePath = __DIR__ . '/../../templates/admin/' . $name . '.php';
        
        if (!file_exists($templatePath)) {
            return '<h1>Template not found: ' . htmlspecialchars($name) . '</h1>';
        }

        extract($data);
        ob_start();
        include $templatePath;
        return ob_get_clean();
    }
}

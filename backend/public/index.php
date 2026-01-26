<?php

declare(strict_types=1);

use Slim\Factory\AppFactory;
use Trail\Config\Config;
use Trail\Middleware\CorsMiddleware;
use Trail\Middleware\SecurityMiddleware;
use Trail\Middleware\RateLimitMiddleware;
use Trail\Middleware\AuthMiddleware;
use Trail\Controllers\AuthController;
use Trail\Controllers\EntryController;
use Trail\Controllers\AdminController;
use Trail\Controllers\RssController;

require __DIR__ . '/../vendor/autoload.php';

// Load environment variables
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/../..');
$dotenv->safeLoad();

// Load configuration
$config = Config::load(__DIR__ . '/../../config.yml');

// Create Slim app
$app = AppFactory::create();
$app->addRoutingMiddleware();

// Add error middleware
$errorMiddleware = $app->addErrorMiddleware(
    $config['app']['environment'] === 'development',
    true,
    true
);

// Add global middleware
$app->add(new CorsMiddleware());
$app->add(new SecurityMiddleware($config));
$app->add(new RateLimitMiddleware($config));

// Health check endpoint
$app->get('/health', function ($request, $response) {
    $response->getBody()->write(json_encode(['status' => 'ok']));
    return $response->withHeader('Content-Type', 'application/json');
});

// Authentication routes
$app->post('/api/auth/google', [AuthController::class, 'googleAuth']);

// Entry routes (authenticated)
$app->group('/api/entries', function ($group) {
    $group->post('', [EntryController::class, 'create']);
    $group->get('', [EntryController::class, 'list']);
})->add(new AuthMiddleware($config));

// Admin routes (authenticated + admin)
$app->group('/admin', function ($group) {
    $group->get('', [AdminController::class, 'dashboard']);
    $group->get('/entries', [AdminController::class, 'entries']);
    $group->post('/entries/{id}', [AdminController::class, 'updateEntry']);
    $group->delete('/entries/{id}', [AdminController::class, 'deleteEntry']);
    $group->get('/users', [AdminController::class, 'users']);
    $group->delete('/users/{id}', [AdminController::class, 'deleteUser']);
})->add(new AuthMiddleware($config, true));

// Public RSS routes
$app->get('/rss', [RssController::class, 'globalFeed']);
$app->get('/rss/{user_id}', [RssController::class, 'userFeed']);

$app->run();

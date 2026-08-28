<?php
// Dev router for the PHP built-in server: serve existing static files,
// otherwise hand the request to the Slim app in public/index.php.
$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$root = __DIR__ . '/public';
$file = $root . $path;

if ($path !== '/' && is_file($file)) {
    return false; // serve statically
}

$_SERVER['SCRIPT_NAME'] = $root . '/index.php';
$_SERVER['SCRIPT_FILENAME'] = $root . '/index.php';
require $root . '/index.php';

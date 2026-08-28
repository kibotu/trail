<?php
// Dev router for the PHP built-in server: serve existing static files,
// otherwise hand the request to the Slim app in public/index.php.
$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$root = realpath(__DIR__ . '/public');
$file = $root . $path;

// Serve statically only if the resolved file stays inside the web root
// (guards against /../secrets.yml on the dev server)
if ($path !== '/' && is_file($file) && strpos(realpath($file), $root . '/') === 0) {
    return false; // serve statically
}

$_SERVER['SCRIPT_NAME'] = $root . '/index.php';
$_SERVER['SCRIPT_FILENAME'] = $root . '/index.php';
require $root . '/index.php';

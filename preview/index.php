<?php
/**
 * Folder entry point.
 * - On Apache, /preview/ lands here and is sent to the admin panel.
 * - Under `php -S localhost:8080 -t preview` (no .htaccess), unknown paths such as
 *   /p/<id> fall back to this file, so we route them to the client page here.
 */
$path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
if (preg_match('~/p/([A-Za-z0-9_-]{20,64})/?$~', $path, $m)) {
    $_GET['id'] = $m[1];
    require __DIR__ . '/p.php';
    exit;
}
if (preg_match('~/(storage|inc)(/|$)~i', $path)) {
    http_response_code(403);
    exit('Forbidden');
}
if ($path !== '/' && substr($path, -1) !== '/' && basename($path) !== 'index.php') {
    http_response_code(404);
    exit('Not found');
}
header('Location: admin.php', true, 302);

<?php
/**
 * Tanish Arts Preview — shared bootstrap.
 * Every entry script defines TA_APP before requiring this file.
 */
if (!defined('TA_APP')) {
    http_response_code(403);
    exit;
}

error_reporting(E_ALL);
ini_set('display_errors', '0');

define('TA_ROOT', dirname(__DIR__));
define('TA_STORAGE', TA_ROOT . '/storage');
define('TA_ORIGINALS', TA_STORAGE . '/originals');
define('TA_PREVIEWS', TA_STORAGE . '/previews');
define('TA_PROJECTS', TA_STORAGE . '/projects');
define('TA_CONFIG', TA_STORAGE . '/config.php');

/**
 * Every file written into storage/ starts with this header. If the web server
 * ever serves storage/ despite .htaccess, the file is executed as PHP, answers
 * 403 and stops — the bytes after __halt_compiler() are never parsed or sent.
 */
define('TA_GUARD', "<?php http_response_code(403); exit; __halt_compiler();\n");

define('TA_MAX_UPLOAD', 25 * 1024 * 1024);
define('TA_MAX_AVATAR', 5 * 1024 * 1024);
define('TA_MAX_PIXELS', 80000000); // 80 MP: protects GD memory on shared hosting
define('TA_VERSION_KEYS', ['A', 'B', 'C', 'D']);

require_once __DIR__ . '/storage.php';
require_once __DIR__ . '/image.php';
require_once __DIR__ . '/helpers.php';

$__cfg = ta_config();
date_default_timezone_set($__cfg['timezone'] ?? 'Asia/Tehran');
unset($__cfg);

header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: same-origin');
header('X-Robots-Tag: noindex, nofollow');

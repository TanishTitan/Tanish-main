<?php
/**
 * Serves the WATERMARKED 640px preview (or the channel avatar) of a project.
 * The original upload is never served here — see download.php.
 *   img.php?p=<id>&v=<A-D>   preview of a version
 *   img.php?p=<id>&a=1       channel avatar
 */
define('TA_APP', true);
require __DIR__ . '/inc/bootstrap.php';

function ta_img_fail(int $code): void
{
    http_response_code($code);
    header('Cache-Control: no-store');
    exit;
}

$p = ta_load_project($_GET['p'] ?? '');
if (!$p) {
    ta_img_fail(404);
}
if (ta_is_expired($p) && !ta_is_admin()) {
    ta_img_fail(410);
}

if (!empty($_GET['a'])) {
    $file = ta_storage_file(TA_PREVIEWS, $p['avatar'] ?? null);
} else {
    $v = ta_version($p, (string) ($_GET['v'] ?? ''));
    $file = $v ? ta_storage_file(TA_PREVIEWS, $v['preview']) : null;
}
if (!$file) {
    ta_img_fail(404);
}

$etag = '"' . md5(basename($file) . filemtime($file)) . '"';
header('Content-Type: image/jpeg');
header('Cache-Control: private, max-age=86400');
header('ETag: ' . $etag);
header('Content-Disposition: inline; filename="preview.jpg"');
if (trim($_SERVER['HTTP_IF_NONE_MATCH'] ?? '') === $etag) {
    http_response_code(304);
    exit;
}
header('Content-Length: ' . ta_guarded_size($file));
ta_stream_guarded($file);

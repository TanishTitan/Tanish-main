<?php
/**
 * Streams the ORIGINAL full-resolution file — only when the project is paid.
 *   download.php?p=<id>          favourite version (from feedback) or the first one
 *   download.php?p=<id>&v=<A-D>  a specific version
 */
define('TA_APP', true);
require __DIR__ . '/inc/bootstrap.php';

$p = ta_load_project($_GET['p'] ?? '');
if (!$p || ($p['status'] ?? '') !== 'paid') {
    // Same answer for "unknown" and "not paid yet": nothing to learn by probing.
    http_response_code(403);
    header('Content-Type: text/plain; charset=utf-8');
    header('Cache-Control: no-store');
    exit('The full-resolution file is available after payment.');
}

$key = (string) ($_GET['v'] ?? '');
if ($key === '') {
    $fb = $p['feedback'] ?? [];
    $fav = end($fb) ?: [];
    $key = $fav['version'] ?? ($p['versions'][0]['key'] ?? '');
}
$v = ta_version($p, $key) ?? ($p['versions'][0] ?? null);
$file = $v ? ta_storage_file(TA_ORIGINALS, $v['original']) : null;
if (!$file) {
    http_response_code(404);
    exit('File not found.');
}

$name = 'TanishArts_' . ta_slug($p['wm_name_effective'] ?? $p['client_name']) . '_'
    . ta_slug($p['video_title'], 'thumbnail') . (count($p['versions']) > 1 ? '_' . $v['key'] : '') . '.' . $v['ext'];

while (ob_get_level()) {
    ob_end_clean();
}
@ini_set('zlib.output_compression', '0');
header('Content-Type: ' . $v['mime']);
header('Content-Length: ' . ta_guarded_size($file));
header('Content-Disposition: attachment; filename="' . $name . '"');
header('Cache-Control: private, no-store');
ta_stream_guarded($file);

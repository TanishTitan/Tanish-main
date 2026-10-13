<?php
/**
 * Stores the client's favourite version + comment.
 * Works with fetch() (JSON reply) and as a plain form POST (redirect back).
 */
define('TA_APP', true);
require __DIR__ . '/inc/bootstrap.php';

$id = (string) ($_POST['id'] ?? '');
$wantsJson = str_contains($_SERVER['HTTP_ACCEPT'] ?? '', 'application/json');

function ta_fb_reply(bool $ok, string $msg, string $id, bool $json): void
{
    if ($json) {
        http_response_code($ok ? 200 : 400);
        header('Content-Type: application/json; charset=utf-8');
        header('Cache-Control: no-store');
        echo json_encode(['ok' => $ok, 'message' => $msg]);
        exit;
    }
    $q = 'p.php?id=' . rawurlencode($id) . ($ok ? '&thanks=1' : '&fberror=' . rawurlencode($msg));
    header('Location: ' . $q . '#feedback', true, 303);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit;
}
$p = ta_load_project($id);
if (!$p || ta_is_expired($p)) {
    ta_fb_reply(false, 'This preview is no longer available.', $id, $wantsJson);
}
$token = (string) ($_POST['token'] ?? '');
if (!hash_equals(ta_feedback_token($id), $token)) {
    ta_fb_reply(false, 'Please reload the page and try again.', $id, $wantsJson);
}
if (ta_rate_limited('feedback', $id, 20, 3600)) {
    ta_fb_reply(false, 'Too many messages — please try again a little later.', $id, $wantsJson);
}

$version = (string) ($_POST['version'] ?? '');
if ($version !== '' && !ta_version($p, $version)) {
    $version = '';
}
if (count($p['versions']) === 1) {
    $version = $p['versions'][0]['key'];
}
$comment = trim(str_replace("\r\n", "\n", (string) ($_POST['comment'] ?? '')));
$comment = mb_substr(preg_replace('/[^\P{C}\n\t]/u', '', $comment) ?? '', 0, 2000);
if ($version === '' && $comment === '') {
    ta_fb_reply(false, 'Pick a version or write a short comment.', $id, $wantsJson);
}

ta_rate_hit('feedback', $id, 3600);
ta_update_project($id, function ($p) use ($version, $comment) {
    $p['feedback'][] = ['version' => $version, 'comment' => $comment, 'at' => time()];
    $p['feedback'] = array_slice($p['feedback'], -50);
    if (($p['status'] ?? 'pending') === 'pending' && $version !== '') {
        $p['status'] = 'approved';
    }
    return $p;
});
ta_fb_reply(true, 'Thanks — Tanish will get back to you.', $id, $wantsJson);

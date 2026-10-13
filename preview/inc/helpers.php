<?php
/** Small helpers: escaping, sessions, CSRF, URLs, YouTube parsing, dates. */
if (!defined('TA_APP')) {
    http_response_code(403);
    exit;
}

function h($s): string
{
    return htmlspecialchars((string) $s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function ta_is_https(): bool
{
    return (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || (($_SERVER['SERVER_PORT'] ?? '') === '443')
        || (strtolower($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');
}

function ta_session(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        return;
    }
    session_name('ta_admin');
    session_set_cookie_params([
        'lifetime' => 0,
        'path' => ta_base_path(),
        'secure' => ta_is_https(),
        'httponly' => true,
        'samesite' => 'Strict',
    ]);
    ini_set('session.use_strict_mode', '1');
    session_start();

    // Idle timeout: 4 hours.
    if (!empty($_SESSION['admin']) && time() - ($_SESSION['seen'] ?? 0) > 4 * 3600) {
        $_SESSION = [];
        session_regenerate_id(true);
    }
    $_SESSION['seen'] = time();
}

/** Admin check that does not create a session for visitors who never logged in. */
function ta_is_admin(): bool
{
    if (session_status() !== PHP_SESSION_ACTIVE && empty($_COOKIE['ta_admin'])) {
        return false;
    }
    ta_session();
    return !empty($_SESSION['admin']);
}

function ta_csrf_token(): string
{
    ta_session();
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf'];
}

function ta_csrf_field(): string
{
    return '<input type="hidden" name="csrf" value="' . h(ta_csrf_token()) . '">';
}

function ta_csrf_ok(): bool
{
    ta_session();
    $sent = $_POST['csrf'] ?? '';
    return is_string($sent) && !empty($_SESSION['csrf']) && hash_equals($_SESSION['csrf'], $sent);
}

/** Token that lets a client page post feedback for exactly one project. */
function ta_feedback_token(string $id): string
{
    return hash_hmac('sha256', 'feedback|' . $id, ta_config()['secret'] ?? '');
}

function ta_client_ip(): string
{
    return $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
}

/** URL path of the preview/ folder, e.g. "/preview/". */
function ta_base_path(): string
{
    $dir = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '/'));
    return rtrim($dir, '/') . '/';
}

function ta_base_url(): string
{
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    return (ta_is_https() ? 'https' : 'http') . '://' . $host . ta_base_path();
}

function ta_client_link(string $id): string
{
    return ta_base_url() . 'p/' . $id;
}

function ta_redirect(string $url): void
{
    header('Location: ' . $url, true, 303);
    exit;
}

/** Extract an 11-character YouTube video ID from a URL or a bare ID. */
function ta_youtube_id(string $s): ?string
{
    $s = trim($s);
    if (preg_match('/^[A-Za-z0-9_-]{11}$/', $s)) {
        return $s;
    }
    $patterns = [
        '~youtu\.be/([A-Za-z0-9_-]{11})~i',
        '~[?&]v=([A-Za-z0-9_-]{11})~i',
        '~youtube(?:-nocookie)?\.com/(?:embed|shorts|live|v)/([A-Za-z0-9_-]{11})~i',
        '~i\.ytimg\.com/vi(?:_webp)?/([A-Za-z0-9_-]{11})~i',
    ];
    foreach ($patterns as $re) {
        if (preg_match($re, $s, $m)) {
            return $m[1];
        }
    }
    return null;
}

/**
 * Parse the competitor textarea: one per line, "URL" or "URL | title | channel".
 * Returns [list, invalidLines].
 */
function ta_parse_competitors(string $text): array
{
    $list = [];
    $bad = [];
    foreach (preg_split('/\R/', $text) as $line) {
        $line = trim($line);
        if ($line === '') {
            continue;
        }
        $parts = array_map('trim', explode('|', $line));
        $vid = ta_youtube_id($parts[0]);
        if (!$vid) {
            $bad[] = $line;
            continue;
        }
        $list[] = [
            'id' => $vid,
            'title' => mb_substr($parts[1] ?? '', 0, 140),
            'channel' => mb_substr($parts[2] ?? '', 0, 60),
        ];
        if (count($list) >= 15) {
            break;
        }
    }
    return [$list, $bad];
}

function ta_competitors_text(array $list): string
{
    $lines = [];
    foreach ($list as $c) {
        $line = 'https://www.youtube.com/watch?v=' . $c['id'];
        if ($c['title'] !== '' || $c['channel'] !== '') {
            $line .= ' | ' . $c['title'] . ($c['channel'] !== '' ? ' | ' . $c['channel'] : '');
        }
        $lines[] = $line;
    }
    return implode("\n", $lines);
}

function ta_is_expired(array $p): bool
{
    if (empty($p['expires']) || ($p['status'] ?? '') === 'paid') {
        return false; // a paid client always keeps access to their file
    }
    $end = strtotime($p['expires'] . ' 23:59:59');
    return $end !== false && time() > $end;
}

const TA_STATUS = [
    'pending' => 'در انتظار',
    'approved' => 'تایید شده',
    'paid' => 'پرداخت شده',
];

function ta_fa_digits($s): string
{
    return strtr((string) $s, ['0' => '۰', '1' => '۱', '2' => '۲', '3' => '۳', '4' => '۴',
        '5' => '۵', '6' => '۶', '7' => '۷', '8' => '۸', '9' => '۹']);
}

/** Gregorian -> Jalali (Solar Hijri). */
function ta_jalali(int $gy, int $gm, int $gd): array
{
    $g_d_m = [0, 31, 59, 90, 120, 151, 181, 212, 243, 273, 304, 334];
    $gy2 = $gm > 2 ? $gy + 1 : $gy;
    $days = 355666 + (365 * $gy) + intdiv($gy2 + 3, 4) - intdiv($gy2 + 99, 100)
        + intdiv($gy2 + 399, 400) + $gd + $g_d_m[$gm - 1];
    $jy = -1595 + (33 * intdiv($days, 12053));
    $days %= 12053;
    $jy += 4 * intdiv($days, 1461);
    $days %= 1461;
    if ($days > 365) {
        $jy += intdiv($days - 1, 365);
        $days = ($days - 1) % 365;
    }
    if ($days < 186) {
        $jm = 1 + intdiv($days, 31);
        $jd = 1 + ($days % 31);
    } else {
        $jm = 7 + intdiv($days - 186, 30);
        $jd = 1 + (($days - 186) % 30);
    }
    return [$jy, $jm, $jd];
}

function ta_fa_date(int $ts, bool $withTime = false): string
{
    [$y, $m, $d] = ta_jalali((int) date('Y', $ts), (int) date('n', $ts), (int) date('j', $ts));
    $s = sprintf('%04d/%02d/%02d', $y, $m, $d);
    if ($withTime) {
        $s .= ' — ' . date('H:i', $ts);
    }
    return ta_fa_digits($s);
}

/** ASCII-only slug for download file names. */
function ta_slug(string $s, string $fallback = 'client'): string
{
    $s = preg_replace('/[^A-Za-z0-9]+/', '-', $s);
    $s = trim(substr((string) $s, 0, 40), '-');
    return $s === '' ? $fallback : $s;
}

function ta_version(array $p, $key): ?array
{
    foreach ($p['versions'] ?? [] as $v) {
        if ($v['key'] === $key) {
            return $v;
        }
    }
    return null;
}

/** Short code burned into the watermark so a leak can be traced to a project. */
function ta_trace_code(string $id): string
{
    return strtoupper(substr(hash('sha256', $id), 0, 6));
}

function ta_asset(string $path): string
{
    $file = TA_ROOT . '/' . $path;
    return $path . (is_file($file) ? '?v=' . filemtime($file) : '');
}

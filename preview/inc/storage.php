<?php
/** Guarded file storage, project records, config and rate limiting. */
if (!defined('TA_APP')) {
    http_response_code(403);
    exit;
}

function ta_config(): array
{
    static $cfg = null;
    if ($cfg === null) {
        $cfg = is_file(TA_CONFIG) ? (include TA_CONFIG) : [];
        if (!is_array($cfg)) {
            $cfg = [];
        }
    }
    return $cfg;
}

function ta_is_setup(): bool
{
    $cfg = ta_config();
    return !empty($cfg['password_hash']) && !empty($cfg['secret']);
}

/** Atomically write $data into a guarded storage file. */
function ta_write_guarded(string $path, string $data): void
{
    $dir = dirname($path);
    if (!is_dir($dir) && !mkdir($dir, 0755, true) && !is_dir($dir)) {
        throw new RuntimeException('Cannot create directory');
    }
    $tmp = $dir . '/.tmp-' . bin2hex(random_bytes(6)) . '.php';
    if (file_put_contents($tmp, TA_GUARD . $data, LOCK_EX) === false) {
        @unlink($tmp);
        throw new RuntimeException('Cannot write file');
    }
    @chmod($tmp, 0644);
    if (!rename($tmp, $path)) {
        @unlink($tmp);
        throw new RuntimeException('Cannot move file');
    }
}

/** Read a guarded storage file and return the payload without the header. */
function ta_read_guarded(string $path): ?string
{
    if (!is_file($path)) {
        return null;
    }
    $raw = file_get_contents($path);
    if ($raw === false) {
        return null;
    }
    if (strncmp($raw, TA_GUARD, strlen(TA_GUARD)) === 0) {
        return substr($raw, strlen(TA_GUARD));
    }
    return $raw;
}

/** Size of the payload of a guarded file. */
function ta_guarded_size(string $path): int
{
    return max(0, (int) filesize($path) - strlen(TA_GUARD));
}

/** Stream the payload of a guarded file to the client. */
function ta_stream_guarded(string $path): void
{
    $fh = fopen($path, 'rb');
    if (!$fh) {
        return;
    }
    fseek($fh, strlen(TA_GUARD));
    while (!feof($fh)) {
        echo fread($fh, 1 << 16);
        flush();
    }
    fclose($fh);
}

function ta_random_id(int $bytes = 18): string
{
    // 18 random bytes -> 24 URL-safe characters (144 bits).
    return rtrim(strtr(base64_encode(random_bytes($bytes)), '+/', '-_'), '=');
}

function ta_valid_id($id): bool
{
    return is_string($id) && preg_match('/^[A-Za-z0-9_-]{20,64}$/', $id) === 1;
}

function ta_project_path(string $id): string
{
    return TA_PROJECTS . '/' . $id . '.php';
}

function ta_load_project($id): ?array
{
    if (!ta_valid_id($id)) {
        return null;
    }
    $json = ta_read_guarded(ta_project_path($id));
    if ($json === null) {
        return null;
    }
    $p = json_decode($json, true);
    return is_array($p) ? $p : null;
}

function ta_save_project(array $p): void
{
    $p['updated'] = time();
    ta_write_guarded(
        ta_project_path($p['id']),
        json_encode($p, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
    );
}

/**
 * Read-modify-write a project under an exclusive lock.
 * $fn receives the project array and returns the new one (or null to abort).
 */
function ta_update_project(string $id, callable $fn): ?array
{
    if (!ta_valid_id($id)) {
        return null;
    }
    $lock = fopen(TA_PROJECTS . '/.lock', 'c');
    if ($lock) {
        flock($lock, LOCK_EX);
    }
    try {
        $p = ta_load_project($id);
        if ($p === null) {
            return null;
        }
        $new = $fn($p);
        if (is_array($new)) {
            ta_save_project($new);
            return $new;
        }
        return $p;
    } finally {
        if ($lock) {
            flock($lock, LOCK_UN);
            fclose($lock);
        }
    }
}

/** All projects, newest first. */
function ta_all_projects(): array
{
    $out = [];
    foreach (glob(TA_PROJECTS . '/*.php') ?: [] as $file) {
        $id = basename($file, '.php');
        if ($id === 'index') {
            continue;
        }
        $p = ta_load_project($id);
        if ($p) {
            $out[] = $p;
        }
    }
    usort($out, fn($a, $b) => ($b['created'] ?? 0) <=> ($a['created'] ?? 0));
    return $out;
}

/** Remove a project and every file that belongs to it. */
function ta_delete_project(string $id): void
{
    if (!ta_valid_id($id)) {
        return;
    }
    foreach ([TA_ORIGINALS, TA_PREVIEWS] as $dir) {
        foreach (glob($dir . '/' . $id . '_*.php') ?: [] as $f) {
            @unlink($f);
        }
    }
    @unlink(ta_project_path($id));
}

/** Safe storage path for a file name recorded in a project. */
function ta_storage_file(string $dir, $name): ?string
{
    if (!is_string($name) || !preg_match('/^[A-Za-z0-9_-]+\.php$/', $name)) {
        return null;
    }
    $path = $dir . '/' . $name;
    return is_file($path) ? $path : null;
}

/**
 * Sliding-window rate limiter.
 * Returns true when the key has already reached $max hits inside $window seconds.
 */
function ta_rate_limited(string $bucket, string $key, int $max, int $window): bool
{
    $data = ta_rate_read();
    $now = time();
    $hits = array_filter($data[$bucket][$key] ?? [], fn($t) => $t > $now - $window);
    return count($hits) >= $max;
}

function ta_rate_hit(string $bucket, string $key, int $window): void
{
    ta_rate_mutate(function (array $data) use ($bucket, $key, $window) {
        $now = time();
        $hits = array_values(array_filter($data[$bucket][$key] ?? [], fn($t) => $t > $now - $window));
        $hits[] = $now;
        $data[$bucket][$key] = array_slice($hits, -50);
        // Drop stale keys so the file stays small.
        foreach ($data as $b => $keys) {
            foreach ($keys as $k => $ts) {
                if (!$ts || max($ts) < $now - 86400) {
                    unset($data[$b][$k]);
                }
            }
        }
        return $data;
    });
}

function ta_rate_clear(string $bucket, string $key): void
{
    ta_rate_mutate(function (array $data) use ($bucket, $key) {
        unset($data[$bucket][$key]);
        return $data;
    });
}

function ta_rate_read(): array
{
    $json = ta_read_guarded(TA_STORAGE . '/ratelimit.php');
    $data = $json ? json_decode($json, true) : [];
    return is_array($data) ? $data : [];
}

function ta_rate_mutate(callable $fn): void
{
    $lock = fopen(TA_STORAGE . '/.ratelimit.lock', 'c');
    if ($lock) {
        flock($lock, LOCK_EX);
    }
    try {
        $data = $fn(ta_rate_read());
        ta_write_guarded(TA_STORAGE . '/ratelimit.php', json_encode($data));
    } finally {
        if ($lock) {
            flock($lock, LOCK_UN);
            fclose($lock);
        }
    }
}

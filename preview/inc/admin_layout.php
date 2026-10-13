<?php
/** Shared Persian RTL layout for setup.php and admin.php. */
if (!defined('TA_APP')) {
    http_response_code(403);
    exit;
}

function ta_admin_head(string $title, bool $loggedIn = false): void
{
    ta_session(); // must start before any output so the cookie can be sent
    ta_csrf_token();
    header('Content-Type: text/html; charset=utf-8');
    header('X-Frame-Options: DENY');
    header("Content-Security-Policy: default-src 'self'; img-src 'self' data: https://i.ytimg.com; style-src 'self'; script-src 'self'; font-src 'self'; frame-ancestors 'none'; form-action 'self'; base-uri 'self'");
    header('Cache-Control: no-store');
    ?>
<!doctype html>
<html lang="fa" dir="rtl">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex, nofollow">
<title><?= h($title) ?> · Tanish Arts Preview</title>
<link rel="stylesheet" href="<?= h(ta_asset('assets/css/admin.css')) ?>">
<link rel="icon" href="assets/img/favicon.svg" type="image/svg+xml">
</head>
<body>
<div class="glow" aria-hidden="true"></div>
<header class="topbar">
    <a class="brand" href="admin.php">
        <span class="brand-mark" aria-hidden="true">T</span>
        <span class="brand-text"><b>TANISH</b> ARTS <small>Preview</small></span>
    </a>
    <?php if ($loggedIn): ?>
    <nav class="topnav">
        <a class="btn btn-primary btn-sm" href="admin.php?view=new">+ پروژه جدید</a>
        <form method="post" action="admin.php" class="inline">
            <?= ta_csrf_field() ?>
            <input type="hidden" name="action" value="logout">
            <button class="btn btn-ghost btn-sm" type="submit">خروج</button>
        </form>
    </nav>
    <?php endif; ?>
</header>
<main class="wrap">
<?php
    foreach ($_SESSION['flash'] ?? [] as $f) {
        echo '<div class="flash flash-' . h($f[0]) . '">' . h($f[1]) . '</div>';
    }
    unset($_SESSION['flash']);
}

function ta_admin_foot(): void
{
    ?>
</main>
<script src="<?= h(ta_asset('assets/js/admin.js')) ?>"></script>
</body>
</html>
<?php
}

function ta_flash(string $type, string $msg): void
{
    ta_session();
    $_SESSION['flash'][] = [$type, $msg];
}

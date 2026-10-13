<?php
/**
 * Client proofing page: p.php?id=<id>  (or /p/<id> via rewrite).
 * Only ever references the watermarked preview through img.php.
 */
if (!defined('TA_APP')) {
    define('TA_APP', true);
}
require_once __DIR__ . '/inc/bootstrap.php';

// Relative prefix so assets resolve both from /preview/p.php and /preview/p/<id>.
$uriPath = parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH) ?: '';
$rel = preg_match('~/p/[A-Za-z0-9_-]+(/?)$~', $uriPath, $mm) ? ($mm[1] === '/' ? '../../' : '../') : '';

$p = ta_load_project($_GET['id'] ?? '');
$isAdmin = $p ? ta_is_admin() : false;

header('Content-Type: text/html; charset=utf-8');
header('X-Frame-Options: DENY');
header("Content-Security-Policy: default-src 'self'; img-src 'self' data: https://i.ytimg.com; style-src 'self'; script-src 'self'; font-src 'self'; connect-src 'self'; frame-ancestors 'none'; form-action 'self'; base-uri 'self'");
header('Cache-Control: private, no-store');

function ta_simple_page(string $rel, string $title, string $heading, string $text, int $code): void
{
    http_response_code($code);
    ?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex, nofollow">
<title><?= h($title) ?> · Tanish Arts</title>
<link rel="stylesheet" href="<?= h($rel . ta_asset('assets/css/client.css')) ?>">
<link rel="icon" href="<?= h($rel) ?>assets/img/favicon.svg" type="image/svg+xml">
</head>
<body class="client simple">
<div class="bg-glow" aria-hidden="true"></div>
<main class="simple-card">
    <?php ta_wordmark($rel); ?>
    <h1><?= h($heading) ?></h1>
    <p><?= h($text) ?></p>
    <a class="btn-brand" href="https://tanisharts.com">Visit tanisharts.com</a>
</main>
</body>
</html>
<?php
    exit;
}

function ta_wordmark(string $rel): void
{
    if (is_file(TA_ROOT . '/assets/img/logo.png')) {
        echo '<a class="wordmark" href="https://tanisharts.com"><img src="' . h($rel) . 'assets/img/logo.png" alt="Tanish Arts" class="logo-img"></a>';
        return;
    }
    echo '<a class="wordmark" href="https://tanisharts.com" aria-label="Tanish Arts">'
        . '<span class="wm-mark" aria-hidden="true">T</span>'
        . '<span class="wm-text"><b>TANISH</b><span class="grad">ARTS</span></span></a>';
}

if (!$p) {
    ta_simple_page($rel, 'Preview not found', 'This preview link isn’t valid', 'Please check the link you received, or message Tanish for a new one.', 404);
}
if (ta_is_expired($p) && !$isAdmin) {
    ta_simple_page($rel, 'Preview expired', 'This preview has expired', 'Thanks for taking a look! This preview link is no longer active — message Tanish if you’d like it reopened.', 410);
}

$id = $p['id'];
$paid = ($p['status'] ?? '') === 'paid';
$versions = $p['versions'];
$multi = count($versions) > 1;
$first = $versions[0]['key'];
$t = (int) ($p['updated'] ?? 0);
$imgUrl = fn(string $key) => $rel . 'img.php?p=' . rawurlencode($id) . '&v=' . rawurlencode($key) . '&t=' . $t;
$avatarUrl = !empty($p['avatar']) ? $rel . 'img.php?p=' . rawurlencode($id) . '&a=1&t=' . $t : null;
$comps = $p['competitors'] ?? [];
$lastFeedback = $p['feedback'] ? end($p['feedback']) : null;
$thanks = !empty($_GET['thanks']);
$fbError = isset($_GET['fberror']) ? mb_substr((string) $_GET['fberror'], 0, 120) : '';

$fakeDur = ['10:24', '18:03', '7:41', '22:16', '14:58', '9:12', '31:07', '12:30', '6:55', '16:20', '25:44', '8:09'];
$fakeViews = ['1.2M views', '384K views', '96K views', '2.7M views', '51K views', '640K views', '18K views', '1.9M views'];
$fakeAge = ['3 days ago', '2 weeks ago', '5 hours ago', '1 month ago', '6 days ago', '3 weeks ago', '11 months ago', '1 year ago'];

/** Competitor (or neutral placeholder) for slot $j of a mockup. */
$comp = function (int $j, int $offset) use ($comps): ?array {
    $n = count($comps);
    if ($j >= $n) {
        return null;
    }
    return $comps[($j + $offset) % $n];
};

/* ---------- small render helpers ---------- */

$dots = '<svg class="yt-ico" viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="5" r="1.7"/><circle cx="12" cy="12" r="1.7"/><circle cx="12" cy="19" r="1.7"/></svg>';

$clientThumb = function (string $extra = '') use ($imgUrl, $first, $p): string {
    return '<div class="yt-thumb ' . $extra . '"><img class="js-v" data-key="' . h($first) . '" src="' . h($imgUrl($first)) . '" alt="" draggable="false">'
        . '<span class="yt-dur">' . h($p['duration_text']) . '</span><span class="shield" aria-hidden="true"></span></div>';
};

$compThumb = function (?array $c, int $i, string $extra = '') use ($fakeDur): string {
    if (!$c) {
        return '<div class="yt-thumb is-ph ' . $extra . '"><span class="ph-fill"></span></div>';
    }
    return '<div class="yt-thumb ' . $extra . '"><img class="js-comp" src="https://i.ytimg.com/vi/' . h($c['id']) . '/hqdefault.jpg" alt="" loading="lazy" referrerpolicy="no-referrer" draggable="false">'
        . '<span class="yt-dur">' . h($fakeDur[$i % count($fakeDur)]) . '</span><span class="shield" aria-hidden="true"></span></div>';
};

$clientAvatar = function (string $size = '') use ($avatarUrl, $p): string {
    if ($avatarUrl) {
        return '<span class="yt-av ' . $size . '"><img src="' . h($avatarUrl) . '" alt="" draggable="false"></span>';
    }
    $initial = mb_strtoupper(mb_substr($p['channel_name'] ?: $p['client_name'], 0, 1));
    return '<span class="yt-av yt-av-letter ' . $size . '">' . h($initial) . '</span>';
};

$compAvatar = fn(string $size = '') => '<span class="yt-av is-ph ' . $size . '"></span>';

/** Title + meta text block (real text for client / named competitors, skeleton otherwise). */
$text = function (?array $c, int $i, bool $isClient, string $layout) use ($p, $fakeViews, $fakeAge, $dots): string {
    if ($isClient) {
        $title = $p['video_title'];
        $channel = $p['channel_name'];
        $views = $p['views_text'];
        $age = $p['age_text'];
    } elseif ($c && $c['title'] !== '') {
        $title = $c['title'];
        $channel = $c['channel'] !== '' ? $c['channel'] : 'YouTube channel';
        $views = $fakeViews[$i % count($fakeViews)];
        $age = $fakeAge[$i % count($fakeAge)];
    } else {
        return '<div class="yt-sk"><span class="sk sk-l"></span><span class="sk sk-m"></span><span class="sk sk-s"></span></div>';
    }
    $titleCls = $isClient ? 'yt-title js-title' : 'yt-title';
    switch ($layout) {
        case 'mobile':
            return '<div class="yt-txt"><h3 class="' . $titleCls . '" dir="auto">' . h($title) . '</h3>'
                . '<p class="yt-meta" dir="auto">' . h($channel) . ' · ' . h($views) . ' · ' . h($age) . '</p></div>' . $dots;
        case 'search':
            return '';
        default:
            return '<div class="yt-txt"><h3 class="' . $titleCls . '" dir="auto">' . h($title) . '</h3>'
                . '<p class="yt-meta" dir="auto">' . h($channel) . '</p>'
                . '<p class="yt-meta" dir="auto">' . h($views) . ' • ' . h($age) . '</p></div>' . $dots;
    }
};

$chips = ['All', 'Gaming', 'Music', 'Podcasts', 'Live', 'Comedy', 'Thrillers', 'Recently uploaded', 'Watched', 'New to you', 'Mixes', 'Computers'];

/* ---------- desktop home feed ---------- */
ob_start();
for ($i = 0; $i < 8; $i++) {
    $isClient = $i === 1;
    $c = $isClient ? null : $comp($i < 1 ? $i : $i - 1, 0);
    echo '<article class="yt-card' . ($isClient ? ' is-client' : '') . '">';
    echo $isClient ? $clientThumb() : $compThumb($c, $i);
    echo '<div class="yt-row">' . ($isClient ? $clientAvatar() : $compAvatar()) . $text($c, $i, $isClient, 'grid') . '</div>';
    echo '</article>';
}
$desktopCards = ob_get_clean();

/* ---------- mobile feed ---------- */
ob_start();
for ($i = 0; $i < 3; $i++) {
    $isClient = $i === 1;
    $c = $isClient ? null : $comp($i < 1 ? $i : $i - 1, 2);
    echo '<article class="yt-mcard' . ($isClient ? ' is-client' : '') . '">';
    echo $isClient ? $clientThumb('flat') : $compThumb($c, $i + 3, 'flat');
    echo '<div class="yt-row">' . ($isClient ? $clientAvatar() : $compAvatar()) . $text($c, $i + 3, $isClient, 'mobile') . '</div>';
    echo '</article>';
}
$mobileCards = ob_get_clean();

/* ---------- up next sidebar ---------- */
ob_start();
for ($i = 0; $i < 7; $i++) {
    $isClient = $i === 2;
    $c = $isClient ? null : $comp($i < 2 ? $i : $i - 1, 1);
    echo '<article class="yt-side' . ($isClient ? ' is-client' : '') . '">';
    echo $isClient ? $clientThumb('small') : $compThumb($c, $i + 5, 'small');
    echo $text($c, $i + 5, $isClient, 'side');
    echo '</article>';
}
$sideCards = ob_get_clean();

/* ---------- search results ---------- */
ob_start();
for ($i = 0; $i < 4; $i++) {
    $isClient = $i === 1;
    $c = $isClient ? null : $comp($i < 1 ? $i : $i - 1, 3);
    echo '<article class="yt-search' . ($isClient ? ' is-client' : '') . '">';
    echo $isClient ? $clientThumb('wide') : $compThumb($c, $i + 2, 'wide');
    if ($isClient || ($c && $c['title'] !== '')) {
        $title = $isClient ? $p['video_title'] : $c['title'];
        $channel = $isClient ? $p['channel_name'] : ($c['channel'] !== '' ? $c['channel'] : 'YouTube channel');
        $views = $isClient ? $p['views_text'] : $fakeViews[($i + 2) % 8];
        $age = $isClient ? $p['age_text'] : $fakeAge[($i + 2) % 8];
        echo '<div class="yt-stxt"><h3 class="yt-title' . ($isClient ? ' js-title' : '') . '" dir="auto">' . h($title) . '</h3>'
            . '<p class="yt-meta" dir="auto">' . h($views) . ' • ' . h($age) . '</p>'
            . '<p class="yt-chan">' . ($isClient ? $clientAvatar('xs') : $compAvatar('xs')) . '<span dir="auto">' . h($channel) . '</span></p>'
            . '<p class="yt-desc"><span class="sk sk-l"></span><span class="sk sk-m"></span></p></div>' . $dots;
    } else {
        echo '<div class="yt-stxt"><div class="yt-sk"><span class="sk sk-l"></span><span class="sk sk-s"></span><span class="sk sk-xs"></span><span class="sk sk-m"></span></div></div>';
    }
    echo '</article>';
}
$searchCards = ob_get_clean();

$ytLogo = '<svg class="yt-logo" viewBox="0 0 90 20" aria-label="YouTube"><rect x="0" y="1" width="28" height="19.6" rx="5.5" fill="#FF0033"/><path d="M11.2 6.3v8.6l7.4-4.3z" fill="#fff"/><text x="31" y="16.2" class="yt-logo-text" textLength="58" lengthAdjust="spacingAndGlyphs">YouTube</text></svg>';
$ico = [
    'menu' => '<svg class="yt-ico" viewBox="0 0 24 24"><path d="M3 6h18v1.6H3zm0 5.2h18v1.6H3zm0 5.2h18V18H3z"/></svg>',
    'search' => '<svg class="yt-ico" viewBox="0 0 24 24"><path d="M15.5 14h-.8l-.3-.3A6.5 6.5 0 1 0 9.5 16a6.5 6.5 0 0 0 4.2-1.6l.3.3v.8l5 5 1.5-1.5-5-5zm-6 0a4.5 4.5 0 1 1 0-9 4.5 4.5 0 0 1 0 9z"/></svg>',
    'mic' => '<svg class="yt-ico" viewBox="0 0 24 24"><path d="M12 14a3 3 0 0 0 3-3V5a3 3 0 0 0-6 0v6a3 3 0 0 0 3 3zm5-3a5 5 0 0 1-10 0H5a7 7 0 0 0 6 6.9V21h2v-3.1A7 7 0 0 0 19 11h-2z"/></svg>',
    'bell' => '<svg class="yt-ico" viewBox="0 0 24 24"><path d="M12 22a2 2 0 0 0 2-2h-4a2 2 0 0 0 2 2zm6-6V11a6 6 0 0 0-4.5-5.8V4.5a1.5 1.5 0 0 0-3 0v.7A6 6 0 0 0 6 11v5l-2 2v1h16v-1l-2-2z"/></svg>',
    'create' => '<svg class="yt-ico" viewBox="0 0 24 24"><path d="M14 13h-3v3H9v-3H6v-2h3V8h2v3h3v2zm3-7H3v12h14v-6.4l4 4V7.4l-4 4V6z"/></svg>',
    'home' => '<svg class="yt-ico" viewBox="0 0 24 24"><path d="M4 21V10.1L12 4l8 6.1V21h-6v-6h-4v6H4z"/></svg>',
    'shorts' => '<svg class="yt-ico" viewBox="0 0 24 24"><path d="M17.8 10.1 16 9.2l1.4-.8a3.6 3.6 0 0 0-3.5-6.3L6.6 6a3.6 3.6 0 0 0 0 6.3l1.4.9-1.4.8a3.6 3.6 0 0 0 3.5 6.3l7.3-3.9a3.6 3.6 0 0 0 .4-6.3zM10 14.6V8.4l5 3.1-5 3.1z"/></svg>',
    'subs' => '<svg class="yt-ico" viewBox="0 0 24 24"><path d="M10 18V12l5 3-5 3zM17 3H7v1h10V3zm3 3H4v1h16V6zm2 3H2v12h20V9z"/></svg>',
    'you' => '<svg class="yt-ico" viewBox="0 0 24 24"><path d="M12 12a4 4 0 1 0 0-8 4 4 0 0 0 0 8zm0 2c-4 0-8 2-8 5v1h16v-1c0-3-4-5-8-5z"/></svg>',
    'cast' => '<svg class="yt-ico" viewBox="0 0 24 24"><path d="M21 3H3a2 2 0 0 0-2 2v3h2V5h18v14h-7v2h7a2 2 0 0 0 2-2V5a2 2 0 0 0-2-2zM1 18v3h3a3 3 0 0 0-3-3zm0-4v2a5 5 0 0 1 5 5h2a7 7 0 0 0-7-7zm0-4v2a9 9 0 0 1 9 9h2A11 11 0 0 0 1 10z"/></svg>',
    'plus' => '<svg class="yt-ico" viewBox="0 0 24 24"><path d="M12 3a9 9 0 1 0 0 18 9 9 0 0 0 0-18zm4.5 9.8h-3.7v3.7h-1.6v-3.7H7.5v-1.6h3.7V7.5h1.6v3.7h3.7v1.6z"/></svg>',
];

$data = [
    'versions' => array_map(fn($v) => ['key' => $v['key'], 'src' => $imgUrl($v['key'])], $versions),
    'feedbackUrl' => $rel . 'feedback.php',
];
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex, nofollow">
<meta name="theme-color" content="#07050C">
<title>Thumbnail preview for <?= h($p['client_name']) ?> · Tanish Arts</title>
<meta property="og:title" content="Thumbnail preview for <?= h($p['client_name']) ?>">
<meta property="og:description" content="Tanish Arts — private thumbnail preview">
<link rel="stylesheet" href="<?= h($rel . ta_asset('assets/css/client.css')) ?>">
<link rel="icon" href="<?= h($rel) ?>assets/img/favicon.svg" type="image/svg+xml">
</head>
<body class="client" data-yt="dark">
<div class="bg-glow" aria-hidden="true"></div>

<?php if ($isAdmin): ?>
<div class="admin-bar">Admin view<?= ta_is_expired($p) ? ' — this link is EXPIRED for the client' : '' ?> · <a href="<?= h($rel) ?>admin.php?view=project&amp;id=<?= h($id) ?>">Back to panel</a></div>
<?php endif; ?>

<header class="hero">
    <div class="brandbar">
        <?php ta_wordmark($rel); ?>
        <span class="pill <?= $paid ? 'pill-paid' : '' ?>"><?= $paid ? 'Paid · download unlocked' : 'Private preview' ?></span>
    </div>
    <p class="eyebrow">Thumbnail preview for</p>
    <h1 class="client-name" dir="auto"><?= h($p['client_name']) ?></h1>
    <p class="video-title" dir="auto">“<?= h($p['video_title']) ?>”</p>
    <p class="lede">See your thumbnail exactly where viewers will meet it — the home feed, on a phone, in “Up next” and in search<?= $comps ? ', next to real competing videos' : '' ?>.<?= $multi ? ' Switch versions, compare them side by side, then pick your favourite below.' : ' Tell me what you think below.' ?></p>
</header>

<div class="toolbar" id="toolbar">
    <?php if ($multi): ?>
    <div class="seg js-versions" role="tablist" aria-label="Version">
        <?php foreach ($versions as $i => $v): ?>
        <button type="button" role="tab" class="<?= $i === 0 ? 'on' : '' ?>" aria-selected="<?= $i === 0 ? 'true' : 'false' ?>" data-key="<?= h($v['key']) ?>"><span class="hide-xs">Version </span><?= h($v['key']) ?></button>
        <?php endforeach; ?>
    </div>
    <div class="seg js-view" aria-label="View">
        <button type="button" class="on" data-view="mockups">In context</button>
        <button type="button" data-view="compare">Side by side</button>
    </div>
    <?php endif; ?>
    <div class="seg js-theme" aria-label="YouTube theme">
        <button type="button" class="on" data-theme="dark"><span class="dot dot-dark"></span>Dark</button>
        <button type="button" data-theme="light"><span class="dot dot-light"></span>Light</button>
    </div>
</div>

<main class="content">

<section id="mockups" class="js-panel" data-panel="mockups">

    <div class="stage">
        <div class="stage-head">
            <span class="stage-num">01</span>
            <div><h2>Home feed · Desktop</h2><p>The first impression on a laptop or TV — your thumbnail in a 4-column grid.</p></div>
        </div>
        <div class="browser">
            <div class="browser-bar"><i></i><i></i><i></i><span class="url">youtube.com</span></div>
            <div class="scaler js-scale" data-width="1280">
                <div class="yt yt-desktop">
                    <div class="yt-mast">
                        <div class="yt-mast-l"><?= $ico['menu'] ?><?= $ytLogo ?></div>
                        <div class="yt-mast-c"><div class="yt-search-box"><span>Search</span><span class="yt-search-btn"><?= $ico['search'] ?></span></div><span class="yt-circle"><?= $ico['mic'] ?></span></div>
                        <div class="yt-mast-r"><span class="yt-create"><?= $ico['create'] ?>Create</span><?= $ico['bell'] ?><span class="yt-me"></span></div>
                    </div>
                    <div class="yt-body">
                        <nav class="yt-mini">
                            <span><?= $ico['home'] ?>Home</span><span><?= $ico['shorts'] ?>Shorts</span><span><?= $ico['subs'] ?>Subscriptions</span><span><?= $ico['you'] ?>You</span>
                        </nav>
                        <div class="yt-main">
                            <div class="yt-chips"><?php foreach ($chips as $i => $c): ?><span class="<?= $i === 0 ? 'on' : '' ?>"><?= h($c) ?></span><?php endforeach; ?></div>
                            <div class="yt-grid"><?= $desktopCards ?></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <p class="stage-note js-scale-note" hidden>Scaled down to fit your screen — on a real desktop it appears larger.</p>
    </div>

    <div class="stage">
        <div class="stage-head">
            <span class="stage-num">02</span>
            <div><h2>Home feed · Mobile</h2><p>Where most views come from — a full-width card on a phone.</p></div>
        </div>
        <div class="phone">
            <div class="yt yt-mobile">
                <div class="yt-status"><span>9:41</span><span class="yt-status-r"><i></i><i></i><b></b></span></div>
                <div class="yt-mtop"><?= $ytLogo ?><span class="yt-mtop-r"><?= $ico['cast'] ?><?= $ico['bell'] ?><?= $ico['search'] ?></span></div>
                <div class="yt-chips yt-chips-m"><?php foreach (array_slice($chips, 0, 6) as $i => $c): ?><span class="<?= $i === 0 ? 'on' : '' ?>"><?= h($c) ?></span><?php endforeach; ?></div>
                <div class="yt-mfeed"><?= $mobileCards ?></div>
                <nav class="yt-mnav"><span class="on"><?= $ico['home'] ?>Home</span><span><?= $ico['shorts'] ?>Shorts</span><span class="yt-mnav-plus"><?= $ico['plus'] ?></span><span><?= $ico['subs'] ?>Subscriptions</span><span><?= $ico['you'] ?>You</span></nav>
            </div>
        </div>
    </div>

    <div class="stage">
        <div class="stage-head">
            <span class="stage-num">03</span>
            <div><h2>“Up next” sidebar</h2><p>Only 168&nbsp;px wide, next to a playing video — the hardest test for readability.</p></div>
        </div>
        <div class="yt yt-watch">
            <div class="yt-player">
                <div class="yt-video"><span class="yt-play"></span><span class="yt-progress"><i></i></span></div>
                <div class="yt-sk yt-now"><span class="sk sk-l"></span><span class="sk sk-s"></span></div>
            </div>
            <div class="yt-secondary">
                <div class="yt-chips yt-chips-s"><span class="on">All</span><span>From this channel</span><span>Related</span><span>Recently uploaded</span></div>
                <?= $sideCards ?>
            </div>
        </div>
    </div>

    <div class="stage">
        <div class="stage-head">
            <span class="stage-num">04</span>
            <div><h2>Search results</h2><p>When someone searches your topic, your thumbnail competes row by row.</p></div>
        </div>
        <div class="yt yt-results">
            <div class="yt-search-bar"><div class="yt-search-box"><span class="js-query" dir="auto"><?= h(mb_strtolower(implode(' ', array_slice(preg_split('/\s+/u', preg_replace('/[^\p{L}\p{N}\s]/u', '', $p['video_title'])), 0, 4)))) ?></span><span class="yt-search-btn"><?= $ico['search'] ?></span></div></div>
            <?= $searchCards ?>
        </div>
    </div>
</section>

<?php if ($multi): ?>
<section id="compare" class="js-panel" data-panel="compare" hidden>
    <div class="stage">
        <div class="stage-head">
            <span class="stage-num">A/B</span>
            <div><h2>Side by side</h2><p>Each version at real feed size and at “Up next” size.</p></div>
        </div>
        <div class="yt yt-compare">
            <?php foreach ($versions as $v): ?>
            <div class="cmp-col">
                <div class="cmp-label">Version <?= h($v['key']) ?></div>
                <article class="yt-card">
                    <div class="yt-thumb"><img src="<?= h($imgUrl($v['key'])) ?>" alt="Version <?= h($v['key']) ?>" draggable="false"><span class="yt-dur"><?= h($p['duration_text']) ?></span><span class="shield" aria-hidden="true"></span></div>
                    <div class="yt-row"><?= $clientAvatar() ?><?= $text(null, 0, true, 'grid') ?></div>
                </article>
                <article class="yt-side">
                    <div class="yt-thumb small"><img src="<?= h($imgUrl($v['key'])) ?>" alt="" draggable="false"><span class="yt-dur"><?= h($p['duration_text']) ?></span><span class="shield" aria-hidden="true"></span></div>
                    <?= $text(null, 0, true, 'side') ?>
                </article>
                <button type="button" class="btn-pick js-pick" data-key="<?= h($v['key']) ?>">I like Version <?= h($v['key']) ?></button>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
</section>
<?php endif; ?>

<?php if ($paid): ?>
<section class="panel download" id="download">
    <h2>Your thumbnail is ready</h2>
    <p>Thank you! Here is the clean, full-resolution file — no watermark.</p>
    <div class="dl-list">
        <?php $favKey = $lastFeedback['version'] ?? ''; foreach ($versions as $v): ?>
        <a class="btn-brand<?= ($multi && $v['key'] !== $favKey && $favKey !== '') ? ' btn-quiet' : '' ?>" href="<?= h($rel) ?>download.php?p=<?= h($id) ?>&amp;v=<?= h($v['key']) ?>">
            Download full resolution<?= $multi ? ' · Version ' . h($v['key']) : '' ?>
            <small><?= (int) $v['width'] ?>×<?= (int) $v['height'] ?> · <?= h(strtoupper($v['ext'])) ?></small>
        </a>
        <?php endforeach; ?>
    </div>
</section>
<?php endif; ?>

<section class="panel feedback" id="feedback">
    <div class="js-thanks thanks" <?= $thanks ? '' : 'hidden' ?>>
        <span class="thanks-icon" aria-hidden="true">✓</span>
        <h2>Thanks — Tanish will get back to you.</h2>
        <p>Your feedback was sent. You can send another note anytime.</p>
        <button type="button" class="btn-link js-again">Send another message</button>
    </div>
    <form class="js-fb" method="post" action="<?= h($rel) ?>feedback.php" <?= $thanks ? 'hidden' : '' ?>>
        <h2><?= $multi ? 'Which version do you like best?' : 'What do you think?' ?></h2>
        <?php if ($lastFeedback && !empty($lastFeedback['version']) && $multi): ?>
        <p class="muted">Last time you picked Version <?= h($lastFeedback['version']) ?>. You can change your mind anytime.</p>
        <?php endif; ?>
        <input type="hidden" name="id" value="<?= h($id) ?>">
        <input type="hidden" name="token" value="<?= h(ta_feedback_token($id)) ?>">
        <?php if ($multi): ?>
        <div class="pick-grid">
            <?php foreach ($versions as $v): ?>
            <label class="pick">
                <input type="radio" name="version" value="<?= h($v['key']) ?>" <?= (($lastFeedback['version'] ?? '') === $v['key']) ? 'checked' : '' ?>>
                <span class="pick-card">
                    <span class="pick-img"><img src="<?= h($imgUrl($v['key'])) ?>" alt="" draggable="false"><span class="shield" aria-hidden="true"></span></span>
                    <span class="pick-name">Version <?= h($v['key']) ?></span>
                </span>
            </label>
            <?php endforeach; ?>
        </div>
        <?php else: ?>
        <input type="hidden" name="version" value="<?= h($first) ?>">
        <?php endif; ?>
        <label class="fb-label" for="fb-comment">Comments or changes <span class="muted">(optional)</span></label>
        <textarea id="fb-comment" name="comment" rows="4" maxlength="2000" placeholder="e.g. Love B — could the text be a bit bigger?"></textarea>
        <p class="fb-error js-fb-error" <?= $fbError ? '' : 'hidden' ?>><?= h($fbError) ?></p>
        <button type="submit" class="btn-brand">Send feedback</button>
    </form>
</section>

</main>

<footer class="foot">
    <?php if (!$paid): ?><p class="foot-note">Preview only — full-resolution file is delivered after payment.</p><?php endif; ?>
    <p class="foot-brand">© <?= date('Y') ?> Tanish Arts · <a href="https://tanisharts.com">tanisharts.com</a></p>
</footer>

<script type="application/json" id="ta-data"><?= json_encode($data, JSON_HEX_TAG | JSON_HEX_AMP | JSON_UNESCAPED_SLASHES) ?></script>
<script src="<?= h($rel . ta_asset('assets/js/client.js')) ?>"></script>
</body>
</html>

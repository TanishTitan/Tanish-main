<?php
/** Admin panel (Persian, RTL). */
define('TA_APP', true);
require __DIR__ . '/inc/bootstrap.php';
require __DIR__ . '/inc/admin_layout.php';

if (!ta_is_setup()) {
    ta_redirect('setup.php');
}
ta_session();

// A POST larger than post_max_size arrives with empty $_POST/$_FILES.
if ($_SERVER['REQUEST_METHOD'] === 'POST' && empty($_POST) && (int) ($_SERVER['CONTENT_LENGTH'] ?? 0) > 0) {
    ta_flash('error', 'مجموع حجم فایل‌ها از حد مجاز سرور (' . ini_get('post_max_size') . ') بیشتر بود. فایل‌ها را در چند مرحله آپلود کنید یا حد را در cPanel بالا ببرید.');
    ta_redirect('admin.php' . (isset($_GET['id']) ? '?view=edit&id=' . urlencode((string) $_GET['id']) : '?view=new'));
}

$action = $_POST['action'] ?? '';

/* ---------------------------------------------------------------- login */

if (empty($_SESSION['admin'])) {
    $error = '';
    if ($action === 'login') {
        $ip = ta_client_ip();
        if (ta_rate_limited('login', $ip, 5, 900) || ta_rate_limited('login', '*', 40, 900)) {
            $error = 'تلاش‌های ناموفق زیاد بود. ۱۵ دقیقه دیگر دوباره امتحان کنید.';
        } elseif (!ta_csrf_ok()) {
            $error = 'نشست منقضی شده؛ دوباره تلاش کنید.';
        } elseif (password_verify((string) ($_POST['password'] ?? ''), ta_config()['password_hash'])) {
            ta_rate_clear('login', $ip);
            session_regenerate_id(true);
            $_SESSION['admin'] = true;
            $_SESSION['csrf'] = bin2hex(random_bytes(32));
            ta_redirect('admin.php');
        } else {
            ta_rate_hit('login', $ip, 900);
            ta_rate_hit('login', '*', 900);
            usleep(400000);
            $error = 'رمز عبور اشتباه است.';
        }
    }
    ta_admin_head('ورود');
    ?>
    <section class="card narrow">
        <h1>ورود به پنل</h1>
        <p class="muted">پیش‌نمایش تامبنیل برای مشتری‌های Tanish Arts</p>
        <?php if ($error): ?><div class="flash flash-error"><?= h($error) ?></div><?php endif; ?>
        <form method="post" action="admin.php" class="form">
            <?= ta_csrf_field() ?>
            <input type="hidden" name="action" value="login">
            <label>رمز عبور
                <input type="password" name="password" required autofocus autocomplete="current-password">
            </label>
            <button class="btn btn-primary" type="submit">ورود</button>
        </form>
    </section>
    <?php
    ta_admin_foot();
    exit;
}

/* -------------------------------------------------------------- actions */

$formErrors = [];
$formValues = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!ta_csrf_ok()) {
        ta_flash('error', 'توکن امنیتی نامعتبر بود؛ دوباره تلاش کنید.');
        ta_redirect('admin.php');
    }
    $id = (string) ($_POST['id'] ?? '');

    switch ($action) {
        case 'logout':
            $_SESSION = [];
            session_regenerate_id(true);
            ta_redirect('admin.php');
            break;

        case 'status':
            $status = (string) ($_POST['status'] ?? '');
            if (!isset(TA_STATUS[$status])) {
                ta_redirect('admin.php');
            }
            $p = ta_update_project($id, function ($p) use ($status) {
                $p['status'] = $status;
                if ($status === 'paid') {
                    $p['paid_at'] = time();
                }
                return $p;
            });
            if ($p) {
                ta_flash('ok', $status === 'paid'
                    ? 'پروژه پرداخت‌شده شد؛ دکمهٔ دانلود فایل اصلی برای مشتری فعال است.'
                    : 'وضعیت به «' . TA_STATUS[$status] . '» تغییر کرد.');
            }
            ta_redirect('admin.php?view=project&id=' . urlencode($id));
            break;

        case 'delete':
            if (ta_load_project($id)) {
                ta_delete_project($id);
                ta_flash('ok', 'پروژه و همهٔ فایل‌هایش حذف شد.');
            }
            ta_redirect('admin.php');
            break;

        case 'save':
            [$saved, $formErrors, $formValues] = ta_admin_save($id);
            if ($saved) {
                ta_flash('ok', $id === '' ? 'پروژه ساخته شد. لینک را کپی کنید و برای مشتری بفرستید.' : 'تغییرات ذخیره شد.');
                ta_redirect('admin.php?view=project&id=' . urlencode($saved));
            }
            $_GET['view'] = $id === '' ? 'new' : 'edit';
            $_GET['id'] = $id;
            break;
    }
}

/**
 * Create or update a project from the submitted form.
 * Returns [savedId|null, errors, submittedValues].
 */
function ta_admin_save(string $id): array
{
    $isNew = $id === '';
    $old = $isNew ? null : ta_load_project($id);
    if (!$isNew && !$old) {
        return [null, ['پروژه پیدا نشد.'], $_POST];
    }

    $str = function (string $key, int $max) {
        $v = trim(preg_replace('/\s+/u', ' ', (string) ($_POST[$key] ?? '')));
        return mb_substr($v, 0, $max);
    };
    $v = [
        'client_name' => $str('client_name', 80),
        'wm_name' => $str('wm_name', 60),
        'video_title' => $str('video_title', 120),
        'channel_name' => $str('channel_name', 60),
        'views_text' => $str('views_text', 30),
        'age_text' => $str('age_text', 30),
        'duration_text' => $str('duration_text', 12),
        'competitors_text' => (string) ($_POST['competitors_text'] ?? ''),
        'expires' => trim((string) ($_POST['expires'] ?? '')),
    ];
    $errors = [];
    if ($v['client_name'] === '') {
        $errors[] = 'نام مشتری را وارد کنید.';
    }
    if ($v['video_title'] === '') {
        $errors[] = 'عنوان ویدیو را وارد کنید.';
    }
    if ($v['expires'] !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $v['expires'])) {
        $errors[] = 'تاریخ انقضا معتبر نیست.';
    }
    [$competitors, $bad] = ta_parse_competitors($v['competitors_text']);
    if ($bad) {
        $errors[] = 'این خط‌ها لینک یوتیوب معتبر نیستند: ' . implode('، ', array_slice($bad, 0, 3));
    }

    // Validate every upload before touching storage.
    $uploads = [];
    foreach (TA_VERSION_KEYS as $key) {
        $f = $_FILES['version_' . $key] ?? null;
        if ($f && ($f['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
            try {
                $uploads[$key] = ta_validate_upload($f, TA_MAX_UPLOAD);
            } catch (RuntimeException $e) {
                $errors[] = 'نسخهٔ ' . $key . ': ' . $e->getMessage();
            }
        }
    }
    $avatarUpload = null;
    $af = $_FILES['avatar'] ?? null;
    if ($af && ($af['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
        try {
            $avatarUpload = ta_validate_upload($af, TA_MAX_AVATAR);
        } catch (RuntimeException $e) {
            $errors[] = 'آواتار: ' . $e->getMessage();
        }
    }

    $p = $old ?? [
        'id' => ta_random_id(),
        'created' => time(),
        'status' => 'pending',
        'versions' => [],
        'avatar' => null,
        'feedback' => [],
    ];

    // Versions kept after removals/replacements.
    $remaining = [];
    foreach (TA_VERSION_KEYS as $key) {
        $existing = ta_version($p, $key);
        if (isset($uploads[$key]) || ($existing && empty($_POST['remove_' . $key]))) {
            $remaining[] = $key;
        }
    }
    if (!$remaining) {
        $errors[] = 'حداقل یک نسخهٔ تامبنیل لازم است.';
    }
    if ($errors) {
        return [null, $errors, $v];
    }

    $wmName = $v['wm_name'] !== '' ? $v['wm_name'] : $v['client_name'];
    $wmChanged = $old && (($old['wm_name_effective'] ?? '') !== $wmName);
    $code = ta_trace_code($p['id']);
    $pid = $p['id'];
    $toDelete = [];

    try {
        $versions = [];
        foreach (TA_VERSION_KEYS as $key) {
            $existing = ta_version($p, $key);
            if (isset($uploads[$key])) {
                $u = $uploads[$key];
                $bytes = file_get_contents($u['tmp']);
                $previewBytes = ta_make_preview($bytes, $u['orientation'], $wmName, $code);
                $orig = $pid . '_' . $key . '_' . bin2hex(random_bytes(8)) . '.php';
                $prev = $pid . '_' . $key . 'p_' . bin2hex(random_bytes(8)) . '.php';
                ta_write_guarded(TA_ORIGINALS . '/' . $orig, $bytes);
                ta_write_guarded(TA_PREVIEWS . '/' . $prev, $previewBytes);
                unset($bytes);
                if ($existing) {
                    $toDelete[] = TA_ORIGINALS . '/' . $existing['original'];
                    $toDelete[] = TA_PREVIEWS . '/' . $existing['preview'];
                }
                $versions[] = [
                    'key' => $key,
                    'original' => $orig,
                    'preview' => $prev,
                    'mime' => $u['mime'],
                    'ext' => $u['ext'],
                    'width' => $u['width'],
                    'height' => $u['height'],
                    'size' => $u['size'],
                    'orientation' => $u['orientation'],
                    'original_name' => mb_substr(basename((string) ($_FILES['version_' . $key]['name'] ?? '')), 0, 120),
                    'uploaded' => time(),
                ];
            } elseif ($existing && empty($_POST['remove_' . $key])) {
                if ($wmChanged) {
                    $src = ta_storage_file(TA_ORIGINALS, $existing['original']);
                    if ($src) {
                        $prev = $pid . '_' . $key . 'p_' . bin2hex(random_bytes(8)) . '.php';
                        ta_write_guarded(TA_PREVIEWS . '/' . $prev, ta_make_preview(ta_read_guarded($src), (int) ($existing['orientation'] ?? 1), $wmName, $code));
                        $toDelete[] = TA_PREVIEWS . '/' . $existing['preview'];
                        $existing['preview'] = $prev;
                    }
                }
                $versions[] = $existing;
            } elseif ($existing) {
                $toDelete[] = TA_ORIGINALS . '/' . $existing['original'];
                $toDelete[] = TA_PREVIEWS . '/' . $existing['preview'];
            }
        }

        if ($avatarUpload) {
            $name = $pid . '_avatar_' . bin2hex(random_bytes(8)) . '.php';
            ta_write_guarded(TA_PREVIEWS . '/' . $name, ta_make_avatar(file_get_contents($avatarUpload['tmp']), $avatarUpload['orientation']));
            if (!empty($p['avatar'])) {
                $toDelete[] = TA_PREVIEWS . '/' . $p['avatar'];
            }
            $p['avatar'] = $name;
        } elseif (!empty($_POST['remove_avatar']) && !empty($p['avatar'])) {
            $toDelete[] = TA_PREVIEWS . '/' . $p['avatar'];
            $p['avatar'] = null;
        }
    } catch (Throwable $e) {
        return [null, ['پردازش تصویر ناموفق بود: ' . $e->getMessage()], $v];
    }

    $p['versions'] = $versions;
    $p['client_name'] = $v['client_name'];
    $p['wm_name'] = $v['wm_name'];
    $p['wm_name_effective'] = $wmName;
    $p['video_title'] = $v['video_title'];
    $p['channel_name'] = $v['channel_name'] !== '' ? $v['channel_name'] : $v['client_name'];
    $p['views_text'] = $v['views_text'] !== '' ? $v['views_text'] : '12K views';
    $p['age_text'] = $v['age_text'] !== '' ? $v['age_text'] : '2 days ago';
    $p['duration_text'] = $v['duration_text'] !== '' ? $v['duration_text'] : '12:47';
    $p['competitors'] = $competitors;
    $p['expires'] = $v['expires'] !== '' ? $v['expires'] : null;

    ta_save_project($p);
    foreach ($toDelete as $f) {
        if (is_file($f)) {
            @unlink($f);
        }
    }
    return [$pid, [], $v];
}

/* ---------------------------------------------------------------- views */

$view = $_GET['view'] ?? 'list';

function ta_status_badge(string $status): string
{
    return '<span class="badge badge-' . h($status) . '">' . h(TA_STATUS[$status] ?? $status) . '</span>';
}

function ta_admin_thumb(array $p, string $key, string $class = 'thumb'): string
{
    return '<img class="' . h($class) . '" src="img.php?p=' . h($p['id']) . '&amp;v=' . h($key) . '&amp;t=' . h($p['updated'] ?? 0) . '" alt="" loading="lazy">';
}

function ta_copy_button(string $link): string
{
    return '<button type="button" class="btn btn-primary js-copy" data-copy="' . h($link) . '">کپی لینک برای مشتری</button>';
}

if ($view === 'project') {
    $p = ta_load_project($_GET['id'] ?? '');
    if (!$p) {
        ta_flash('error', 'پروژه پیدا نشد.');
        ta_redirect('admin.php');
    }
    $link = ta_client_link($p['id']);
    $expired = ta_is_expired($p);
    ta_admin_head($p['client_name'], true);
    ?>
    <a class="back" href="admin.php">→ همهٔ پروژه‌ها</a>
    <section class="card">
        <div class="project-head">
            <div>
                <h1><?= h($p['client_name']) ?> <?= ta_status_badge($p['status']) ?></h1>
                <p class="muted" dir="auto"><?= h($p['video_title']) ?></p>
            </div>
            <a class="btn btn-ghost" href="admin.php?view=edit&amp;id=<?= h($p['id']) ?>">ویرایش</a>
        </div>

        <div class="linkbox">
            <input type="text" readonly value="<?= h($link) ?>" dir="ltr" class="js-select">
            <?= ta_copy_button($link) ?>
            <a class="btn btn-ghost" href="p.php?id=<?= h($p['id']) ?>" target="_blank" rel="noopener">باز کردن صفحهٔ مشتری</a>
        </div>
        <p class="hint">اگر لینک کوتاه <code dir="ltr">/p/…</code> روی هاست کار نکرد، این لینک همیشه کار می‌کند: <code dir="ltr"><?= h(ta_base_url() . 'p.php?id=' . $p['id']) ?></code></p>

        <?php if ($expired): ?>
        <div class="flash flash-error">این لینک منقضی شده است (<?= h(ta_fa_digits($p['expires'])) ?>). مشتری فقط پیام «منقضی شده» را می‌بیند. برای تمدید، تاریخ انقضا را ویرایش کنید.</div>
        <?php elseif (!empty($p['expires'])): ?>
        <p class="muted">انقضا: <?= h(ta_fa_digits($p['expires'])) ?> (پس از پرداخت، انقضا اعمال نمی‌شود)</p>
        <?php endif; ?>

        <div class="actions">
            <?php if ($p['status'] !== 'paid'): ?>
                <form method="post" action="admin.php" class="inline">
                    <?= ta_csrf_field() ?>
                    <input type="hidden" name="action" value="status">
                    <input type="hidden" name="id" value="<?= h($p['id']) ?>">
                    <input type="hidden" name="status" value="paid">
                    <button class="btn btn-success" type="submit">علامت‌گذاری به‌عنوان پرداخت‌شده</button>
                </form>
                <?php if ($p['status'] === 'pending'): ?>
                <form method="post" action="admin.php" class="inline">
                    <?= ta_csrf_field() ?>
                    <input type="hidden" name="action" value="status">
                    <input type="hidden" name="id" value="<?= h($p['id']) ?>">
                    <input type="hidden" name="status" value="approved">
                    <button class="btn btn-ghost" type="submit">علامت‌گذاری به‌عنوان تایید شده</button>
                </form>
                <?php else: ?>
                <form method="post" action="admin.php" class="inline">
                    <?= ta_csrf_field() ?>
                    <input type="hidden" name="action" value="status">
                    <input type="hidden" name="id" value="<?= h($p['id']) ?>">
                    <input type="hidden" name="status" value="pending">
                    <button class="btn btn-ghost" type="submit">برگرداندن به «در انتظار»</button>
                </form>
                <?php endif; ?>
            <?php else: ?>
                <form method="post" action="admin.php" class="inline">
                    <?= ta_csrf_field() ?>
                    <input type="hidden" name="action" value="status">
                    <input type="hidden" name="id" value="<?= h($p['id']) ?>">
                    <input type="hidden" name="status" value="approved">
                    <button class="btn btn-warn" type="submit">لغو پرداخت (قفل دوباره‌ی دانلود)</button>
                </form>
                <span class="muted">پرداخت شده در <?= h(ta_fa_date((int) ($p['paid_at'] ?? time()), true)) ?></span>
            <?php endif; ?>
        </div>
    </section>

    <section class="card">
        <h2>نسخه‌ها</h2>
        <div class="versions">
            <?php foreach ($p['versions'] as $v): ?>
            <figure class="version">
                <?= ta_admin_thumb($p, $v['key']) ?>
                <figcaption>
                    <b>نسخهٔ <?= h($v['key']) ?></b>
                    <span class="muted" dir="ltr"><?= (int) $v['width'] ?>×<?= (int) $v['height'] ?> · <?= h(strtoupper($v['ext'])) ?> · <?= round($v['size'] / 1048576, 2) ?> MB</span>
                    <?php if ($p['status'] === 'paid'): ?>
                    <a href="download.php?p=<?= h($p['id']) ?>&amp;v=<?= h($v['key']) ?>">دانلود فایل اصلی</a>
                    <?php endif; ?>
                </figcaption>
            </figure>
            <?php endforeach; ?>
        </div>
        <p class="hint">تصاویر بالا همان پیش‌نمایش واترمارک‌دار ۶۴۰ پیکسلی است که مشتری می‌بیند. کد ردیابی این پروژه: <b dir="ltr">#<?= h(ta_trace_code($p['id'])) ?></b></p>
    </section>

    <section class="card">
        <h2>نظر مشتری</h2>
        <?php if (empty($p['feedback'])): ?>
            <p class="muted">مشتری هنوز نظری ثبت نکرده است.</p>
        <?php else: ?>
            <ul class="feedback">
            <?php foreach (array_reverse($p['feedback']) as $i => $f): ?>
                <li class="<?= $i === 0 ? 'latest' : '' ?>">
                    <div class="fb-head">
                        <?php if (!empty($f['version'])): ?><span class="badge badge-approved">نسخهٔ انتخابی: <?= h($f['version']) ?></span><?php endif; ?>
                        <span class="muted"><?= h(ta_fa_date((int) $f['at'], true)) ?></span>
                        <?php if ($i === 0): ?><span class="muted">(آخرین)</span><?php endif; ?>
                    </div>
                    <?php if (trim($f['comment'] ?? '') !== ''): ?>
                    <p class="fb-comment" dir="auto"><?= nl2br(h($f['comment'])) ?></p>
                    <?php endif; ?>
                </li>
            <?php endforeach; ?>
            </ul>
        <?php endif; ?>
    </section>

    <section class="card danger">
        <h2>حذف پروژه</h2>
        <p class="muted">پروژه، فایل‌های اصلی، پیش‌نمایش‌ها و نظرها برای همیشه حذف می‌شوند و لینک مشتری از کار می‌افتد.</p>
        <form method="post" action="admin.php" class="inline js-confirm" data-confirm="پروژهٔ «<?= h($p['client_name']) ?>» و همهٔ فایل‌هایش حذف شود؟ این کار برگشت‌پذیر نیست.">
            <?= ta_csrf_field() ?>
            <input type="hidden" name="action" value="delete">
            <input type="hidden" name="id" value="<?= h($p['id']) ?>">
            <button class="btn btn-danger" type="submit">حذف پروژه</button>
        </form>
    </section>
    <?php
    ta_admin_foot();
    exit;
}

if ($view === 'new' || $view === 'edit') {
    $p = $view === 'edit' ? ta_load_project($_GET['id'] ?? '') : null;
    if ($view === 'edit' && !$p) {
        ta_flash('error', 'پروژه پیدا نشد.');
        ta_redirect('admin.php');
    }
    $val = $formValues ?? [
        'client_name' => $p['client_name'] ?? '',
        'wm_name' => $p['wm_name'] ?? '',
        'video_title' => $p['video_title'] ?? '',
        'channel_name' => $p['channel_name'] ?? '',
        'views_text' => $p['views_text'] ?? '',
        'age_text' => $p['age_text'] ?? '',
        'duration_text' => $p['duration_text'] ?? '',
        'competitors_text' => ta_competitors_text($p['competitors'] ?? []),
        'expires' => $p['expires'] ?? '',
    ];
    ta_admin_head($p ? 'ویرایش ' . $p['client_name'] : 'پروژهٔ جدید', true);
    ?>
    <a class="back" href="<?= $p ? 'admin.php?view=project&amp;id=' . h($p['id']) : 'admin.php' ?>">→ بازگشت</a>
    <form method="post" action="admin.php<?= $p ? '?id=' . h($p['id']) : '' ?>" enctype="multipart/form-data" class="card form js-upload-form">
        <h1><?= $p ? 'ویرایش پروژه' : 'پروژهٔ جدید' ?></h1>
        <?php foreach ($formErrors as $e): ?><div class="flash flash-error"><?= h($e) ?></div><?php endforeach; ?>
        <?= ta_csrf_field() ?>
        <input type="hidden" name="action" value="save">
        <input type="hidden" name="id" value="<?= h($p['id'] ?? '') ?>">

        <fieldset>
            <legend>مشتری</legend>
            <div class="grid2">
                <label>نام مشتری *
                    <input type="text" name="client_name" value="<?= h($val['client_name']) ?>" required maxlength="80" dir="auto">
                    <small>در صفحهٔ مشتری نمایش داده می‌شود: «Thumbnail preview for …»</small>
                </label>
                <label>نام روی واترمارک (انگلیسی)
                    <input type="text" name="wm_name" value="<?= h($val['wm_name']) ?>" maxlength="60" dir="ltr" placeholder="مثلاً MrClient">
                    <small>اگر خالی بماند، نام مشتری استفاده می‌شود. حروف فارسی در واترمارک جدا جدا چاپ می‌شوند؛ انگلیسی بنویسید.</small>
                </label>
            </div>
            <label>تاریخ انقضای لینک (اختیاری)
                <input type="date" name="expires" value="<?= h($val['expires']) ?>" dir="ltr">
                <small>بعد از این روز، مشتری پیام «This preview has expired» می‌بیند (پروژه‌های پرداخت‌شده منقضی نمی‌شوند).</small>
            </label>
        </fieldset>

        <fieldset>
            <legend>اطلاعات کارت یوتیوب (به انگلیسی، همان‌طور که در یوتیوب دیده می‌شود)</legend>
            <label>عنوان ویدیو *
                <input type="text" name="video_title" value="<?= h($val['video_title']) ?>" required maxlength="120" dir="auto">
            </label>
            <div class="grid2">
                <label>نام کانال
                    <input type="text" name="channel_name" value="<?= h($val['channel_name']) ?>" maxlength="60" dir="auto">
                </label>
                <label>آواتار کانال (اختیاری)
                    <input type="file" name="avatar" accept="image/jpeg,image/png,image/webp">
                    <?php if (!empty($p['avatar'])): ?>
                    <span class="avatar-row"><img class="avatar-prev" src="img.php?p=<?= h($p['id']) ?>&amp;a=1&amp;t=<?= h($p['updated']) ?>" alt=""> <label class="check"><input type="checkbox" name="remove_avatar" value="1"> حذف آواتار</label></span>
                    <?php endif; ?>
                </label>
            </div>
            <div class="grid3">
                <label>تعداد بازدید
                    <input type="text" name="views_text" value="<?= h($val['views_text']) ?>" maxlength="30" dir="ltr" placeholder="12K views">
                </label>
                <label>زمان انتشار
                    <input type="text" name="age_text" value="<?= h($val['age_text']) ?>" maxlength="30" dir="ltr" placeholder="2 days ago">
                </label>
                <label>مدت ویدیو
                    <input type="text" name="duration_text" value="<?= h($val['duration_text']) ?>" maxlength="12" dir="ltr" placeholder="12:47">
                </label>
            </div>
        </fieldset>

        <fieldset>
            <legend>تامبنیل‌ها (۱ تا ۴ نسخه — JPG، PNG یا WebP تا ۲۵ مگابایت)</legend>
            <div class="slots">
            <?php foreach (TA_VERSION_KEYS as $key): $ex = $p ? ta_version($p, $key) : null; ?>
                <div class="slot">
                    <b>نسخهٔ <?= h($key) ?></b>
                    <?php if ($ex): ?>
                        <?= ta_admin_thumb($p, $key) ?>
                        <label class="check"><input type="checkbox" name="remove_<?= h($key) ?>" value="1"> حذف این نسخه</label>
                        <small>برای جایگزینی، فایل جدید انتخاب کنید:</small>
                    <?php endif; ?>
                    <input type="file" name="version_<?= h($key) ?>" accept="image/jpeg,image/png,image/webp" class="js-file">
                </div>
            <?php endforeach; ?>
            </div>
            <small>فایل اصلی بدون تغییر ذخیره می‌شود و فقط بعد از پرداخت قابل دانلود است. مشتری قبل از پرداخت فقط نسخهٔ ۶۴۰ پیکسلی واترمارک‌دار را می‌بیند.</small>
            <?php if (ta_bytes(ini_get('upload_max_filesize')) < TA_MAX_UPLOAD || ta_bytes(ini_get('post_max_size')) < 4 * TA_MAX_UPLOAD): ?>
            <div class="flash flash-error">توجه: حد آپلود این سرور <b dir="ltr"><?= h(ini_get('upload_max_filesize')) ?></b> برای هر فایل و <b dir="ltr"><?= h(ini_get('post_max_size')) ?></b> برای کل فرم است. برای فایل‌های تا ۲۵ مگابایت، راهنمای README-fa.md (بخش «حد آپلود») را ببینید.</div>
            <?php endif; ?>
        </fieldset>

        <fieldset>
            <legend>رقبا (اختیاری)</legend>
            <label>لینک ویدیوهای یوتیوب، هر خط یکی
                <textarea name="competitors_text" rows="5" dir="ltr" placeholder="https://www.youtube.com/watch?v=dQw4w9WgXcQ&#10;https://youtu.be/xxxxxxxxxxx | Video title | Channel name"><?= h($val['competitors_text']) ?></textarea>
                <small>تامبنیل رقبا در مرورگر مشتری از یوتیوب بارگذاری می‌شود. می‌توانید بعد از لینک با «|» عنوان و نام کانال را هم بنویسید؛ وگرنه متن کارت خاکستری نمایش داده می‌شود. اگر خالی باشد، کارت‌های خاکستری خنثی نمایش داده می‌شوند.</small>
            </label>
        </fieldset>

        <div class="form-foot">
            <button class="btn btn-primary" type="submit"><?= $p ? 'ذخیرهٔ تغییرات' : 'ساخت پروژه' ?></button>
            <span class="muted js-upload-status" hidden>در حال آپلود و ساخت پیش‌نمایش… لطفاً صبر کنید.</span>
        </div>
    </form>
    <?php
    ta_admin_foot();
    exit;
}

/* ----------------------------------------------------------------- list */

$projects = ta_all_projects();
ta_admin_head('پروژه‌ها', true);
?>
<div class="page-head">
    <h1>پروژه‌ها</h1>
    <span class="muted"><?= ta_fa_digits(count($projects)) ?> پروژه</span>
</div>
<?php if (!$projects): ?>
    <section class="card empty">
        <p>هنوز پروژه‌ای نساخته‌اید.</p>
        <a class="btn btn-primary" href="admin.php?view=new">ساخت اولین پروژه</a>
    </section>
<?php else: ?>
    <div class="plist">
    <?php foreach ($projects as $p): $first = $p['versions'][0]['key'] ?? null; ?>
        <article class="pitem">
            <a class="pitem-thumb" href="admin.php?view=project&amp;id=<?= h($p['id']) ?>">
                <?= $first ? ta_admin_thumb($p, $first) : '' ?>
                <?php if (count($p['versions']) > 1): ?><span class="vcount"><?= ta_fa_digits(count($p['versions'])) ?> نسخه</span><?php endif; ?>
            </a>
            <div class="pitem-body">
                <h2><a href="admin.php?view=project&amp;id=<?= h($p['id']) ?>"><?= h($p['client_name']) ?></a></h2>
                <p class="muted ellipsis" dir="auto"><?= h($p['video_title']) ?></p>
                <div class="pitem-meta">
                    <?= ta_status_badge($p['status']) ?>
                    <?php if (ta_is_expired($p)): ?><span class="badge badge-expired">منقضی</span><?php endif; ?>
                    <?php if (!empty($p['feedback'])): ?><span class="badge badge-fb">دارای نظر مشتری</span><?php endif; ?>
                    <span class="muted"><?= h(ta_fa_date((int) $p['created'])) ?></span>
                </div>
                <div class="pitem-actions">
                    <?= ta_copy_button(ta_client_link($p['id'])) ?>
                    <a class="btn btn-ghost btn-sm" href="admin.php?view=project&amp;id=<?= h($p['id']) ?>">جزئیات</a>
                </div>
            </div>
        </article>
    <?php endforeach; ?>
    </div>
<?php endif;
ta_admin_foot();

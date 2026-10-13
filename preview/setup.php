<?php
/**
 * One-time setup: sets the admin password and creates storage/config.php.
 * Once config.php exists this script refuses to run again.
 */
define('TA_APP', true);
require __DIR__ . '/inc/bootstrap.php';
require __DIR__ . '/inc/admin_layout.php';

if (ta_is_setup()) {
    http_response_code(403);
    ta_admin_head('راه‌اندازی');
    ?>
    <section class="card narrow">
        <h1>راه‌اندازی قبلاً انجام شده است</h1>
        <p class="muted">رمز عبور پنل تنظیم شده و این صفحه دیگر کار نمی‌کند. برای امنیت بیشتر می‌توانید فایل <code>setup.php</code> را از هاست حذف کنید.</p>
        <p class="muted">اگر رمز را فراموش کرده‌اید، فایل <code>storage/config.php</code> را از طریق File Manager پاک کنید و دوباره این صفحه را باز کنید.</p>
        <a class="btn btn-primary" href="admin.php">ورود به پنل</a>
    </section>
    <?php
    ta_admin_foot();
    exit;
}

$error = '';
$checks = [
    'نسخهٔ PHP ۸ یا بالاتر' => PHP_VERSION_ID >= 80000,
    'افزونهٔ GD' => function_exists('imagecreatetruecolor'),
    'پشتیبانی WebP در GD' => function_exists('imagecreatefromwebp'),
    'FreeType (متن واترمارک)' => function_exists('imagettftext'),
    'پوشهٔ storage قابل نوشتن' => is_writable(TA_STORAGE),
    'پوشهٔ originals قابل نوشتن' => is_writable(TA_ORIGINALS),
    'پوشهٔ previews قابل نوشتن' => is_writable(TA_PREVIEWS),
    'پوشهٔ projects قابل نوشتن' => is_writable(TA_PROJECTS),
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $pass = (string) ($_POST['password'] ?? '');
    $pass2 = (string) ($_POST['password2'] ?? '');
    if (!ta_csrf_ok()) {
        $error = 'نشست منقضی شده؛ صفحه را دوباره بارگذاری کنید.';
    } elseif (mb_strlen($pass) < 8) {
        $error = 'رمز عبور باید حداقل ۸ کاراکتر باشد.';
    } elseif ($pass !== $pass2) {
        $error = 'رمز عبور و تکرار آن یکسان نیستند.';
    } else {
        $config = [
            'password_hash' => password_hash($pass, PASSWORD_DEFAULT),
            'secret' => bin2hex(random_bytes(32)),
            'timezone' => 'Asia/Tehran',
            'created' => time(),
        ];
        $php = "<?php\n// Tanish Arts Preview config — created by setup.php. Do not share this file.\n"
            . "if (!defined('TA_APP')) { http_response_code(403); exit; }\n"
            . "// Delete this file to run setup.php again (e.g. if you forget the password).\n"
            . 'return ' . var_export($config, true) . ";\n";
        // Create exclusively so two simultaneous requests cannot both set a password.
        $fh = @fopen(TA_CONFIG, 'x');
        if (!$fh) {
            $error = 'نوشتن فایل تنظیمات ممکن نشد. دسترسی پوشهٔ storage را بررسی کنید (۷۵۵).';
        } else {
            fwrite($fh, $php);
            fclose($fh);
            @chmod(TA_CONFIG, 0640);
            session_regenerate_id(true);
            $_SESSION = ['admin' => true, 'seen' => time()];
            ta_flash('ok', 'رمز عبور ذخیره شد و وارد پنل شدید. حالا اولین پروژه را بسازید.');
            ta_redirect('admin.php');
        }
    }
}

ta_admin_head('راه‌اندازی');
?>
<section class="card narrow">
    <h1>راه‌اندازی Tanish Arts Preview</h1>
    <p class="muted">این صفحه فقط یک بار کار می‌کند. یک رمز عبور قوی برای پنل مدیریت انتخاب کنید.</p>

    <ul class="checks">
        <?php foreach ($checks as $label => $ok): ?>
        <li class="<?= $ok ? 'ok' : 'bad' ?>"><span><?= $ok ? '✓' : '✗' ?></span> <?= h($label) ?></li>
        <?php endforeach; ?>
    </ul>

    <?php if ($error): ?><div class="flash flash-error"><?= h($error) ?></div><?php endif; ?>

    <form method="post" action="setup.php" class="form">
        <?= ta_csrf_field() ?>
        <label>رمز عبور جدید
            <input type="password" name="password" minlength="8" required autocomplete="new-password">
        </label>
        <label>تکرار رمز عبور
            <input type="password" name="password2" minlength="8" required autocomplete="new-password">
        </label>
        <button class="btn btn-primary" type="submit">ذخیرهٔ رمز و ورود</button>
    </form>
</section>
<?php
ta_admin_foot();

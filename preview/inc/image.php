<?php
/** Image validation and watermarked preview generation (PHP GD). */
if (!defined('TA_APP')) {
    http_response_code(403);
    exit;
}

const TA_IMAGE_TYPES = [
    IMAGETYPE_JPEG => ['mime' => 'image/jpeg', 'ext' => 'jpg'],
    IMAGETYPE_PNG  => ['mime' => 'image/png',  'ext' => 'png'],
    IMAGETYPE_WEBP => ['mime' => 'image/webp', 'ext' => 'webp'],
];

/**
 * Validate an uploaded file by its real content (magic bytes + decoder),
 * never by the extension. Returns image info or throws with a Persian message.
 */
function ta_validate_upload(array $file, int $maxBytes): array
{
    $err = $file['error'] ?? UPLOAD_ERR_NO_FILE;
    if ($err === UPLOAD_ERR_INI_SIZE || $err === UPLOAD_ERR_FORM_SIZE) {
        throw new RuntimeException('حجم فایل بیشتر از حد مجاز سرور است.');
    }
    if ($err !== UPLOAD_ERR_OK) {
        throw new RuntimeException('آپلود فایل ناموفق بود (کد ' . (int) $err . ').');
    }
    $tmp = $file['tmp_name'] ?? '';
    if (!is_uploaded_file($tmp)) {
        throw new RuntimeException('فایل آپلودشده معتبر نیست.');
    }
    $size = filesize($tmp);
    if ($size === false || $size <= 0) {
        throw new RuntimeException('فایل خالی است.');
    }
    if ($size > $maxBytes) {
        throw new RuntimeException('حجم فایل بیشتر از ' . round($maxBytes / 1048576) . ' مگابایت است.');
    }

    $info = @getimagesize($tmp);
    if (!$info || !isset(TA_IMAGE_TYPES[$info[2]])) {
        throw new RuntimeException('فقط تصویر JPG، PNG یا WebP قابل قبول است.');
    }
    $type = TA_IMAGE_TYPES[$info[2]];

    if (function_exists('finfo_open')) {
        $fi = finfo_open(FILEINFO_MIME_TYPE);
        $mime = $fi ? finfo_file($fi, $tmp) : null;
        if ($fi) {
            finfo_close($fi);
        }
        if ($mime && $mime !== $type['mime']) {
            throw new RuntimeException('نوع واقعی فایل با تصویر JPG/PNG/WebP مطابقت ندارد.');
        }
    }

    [$w, $h] = $info;
    if ($w < 16 || $h < 16) {
        throw new RuntimeException('ابعاد تصویر خیلی کوچک است.');
    }
    if ($w * $h > TA_MAX_PIXELS) {
        throw new RuntimeException('ابعاد تصویر خیلی بزرگ است (حداکثر ۸۰ مگاپیکسل).');
    }

    $orientation = 1;
    if ($info[2] === IMAGETYPE_JPEG && function_exists('exif_read_data')) {
        $exif = @exif_read_data($tmp);
        $orientation = (int) ($exif['Orientation'] ?? 1);
    }

    return [
        'tmp' => $tmp,
        'mime' => $type['mime'],
        'ext' => $type['ext'],
        'width' => $w,
        'height' => $h,
        'size' => $size,
        'orientation' => $orientation,
    ];
}

function ta_prepare_memory(): void
{
    $limit = ini_get('memory_limit');
    if ($limit !== '-1' && ta_bytes($limit) < 512 * 1048576) {
        @ini_set('memory_limit', '512M');
    }
    @set_time_limit(180);
}

function ta_bytes(string $v): int
{
    $v = trim($v);
    $n = (int) $v;
    switch (strtolower(substr($v, -1))) {
        case 'g': $n *= 1024;
        // no break
        case 'm': $n *= 1024;
        // no break
        case 'k': $n *= 1024;
    }
    return $n;
}

/** Decode image bytes into a GD image, applying JPEG EXIF orientation. */
function ta_decode(string $bytes, int $orientation = 1)
{
    $img = @imagecreatefromstring($bytes);
    if (!$img) {
        throw new RuntimeException('سرور نتوانست این تصویر را بخواند (شاید WebP متحرک یا خراب باشد).');
    }
    switch ($orientation) {
        case 3: $img = imagerotate($img, 180, 0); break;
        case 6: $img = imagerotate($img, -90, 0); break;
        case 8: $img = imagerotate($img, 90, 0); break;
    }
    return $img;
}

/** Resize to fit $maxW, flattened onto an opaque background. */
function ta_resize($src, int $maxW)
{
    $sw = imagesx($src);
    $sh = imagesy($src);
    $w = min($maxW, $sw);
    $h = max(1, (int) round($sh * $w / $sw));
    $dst = imagecreatetruecolor($w, $h);
    imagefill($dst, 0, 0, imagecolorallocate($dst, 255, 255, 255));
    imagealphablending($dst, true);
    imagecopyresampled($dst, $src, 0, 0, 0, 0, $w, $h, $sw, $sh);
    return $dst;
}

/**
 * Build the public preview: max 640px wide, JPEG q70, with a light tiled
 * diagonal "TANISH ARTS · PREVIEW" pattern and the client's name.
 */
function ta_make_preview(string $bytes, int $orientation, string $clientName, string $code): string
{
    ta_prepare_memory();
    $src = ta_decode($bytes, $orientation);
    $img = ta_resize($src, 640);
    imagedestroy($src);
    ta_watermark($img, $clientName, $code);

    imageinterlace($img, true);
    ob_start();
    imagejpeg($img, null, 70);
    $out = ob_get_clean();
    imagedestroy($img);
    return $out;
}

function ta_watermark($img, string $clientName, string $code): void
{
    $w = imagesx($img);
    $h = imagesy($img);
    imagealphablending($img, true);

    $brand = 'TANISH ARTS · PREVIEW';
    $client = trim($clientName) !== '' ? 'FOR ' . ta_upper($clientName) . ' · #' . $code : '#' . $code;

    // alpha 0 = opaque, 127 = transparent. Kept light so the design stays readable.
    $light = imagecolorallocatealpha($img, 255, 255, 255, 98);
    $dark = imagecolorallocatealpha($img, 0, 0, 0, 108);

    $font = __DIR__ . '/fonts/watermark.ttf';
    if (function_exists('imagettftext') && is_file($font)) {
        $size = max(8.0, $w / 52);
        $angle = 24;
        $rowGap = $size * 4.6;
        $bboxB = imagettfbbox($size, 0, $font, $brand);
        $bboxC = imagettfbbox($size * 0.82, 0, $font, $client);
        $lenB = abs($bboxB[2] - $bboxB[0]);
        $lenC = abs($bboxC[2] - $bboxC[0]);
        $row = 0;
        // Rotated text drifts upward, so start well below and to the left.
        for ($y = -$h * 0.2; $y < $h * 1.6; $y += $rowGap, $row++) {
            $isBrand = $row % 2 === 0;
            $text = $isBrand ? $brand : $client;
            $fs = $isBrand ? $size : $size * 0.82;
            $step = ($isBrand ? $lenB : $lenC) * cos(deg2rad($angle)) + $size * 5;
            $offset = ($row % 4) * $step / 4;
            for ($x = -$w * 0.6 - $offset; $x < $w * 1.2; $x += $step) {
                imagettftext($img, $fs, $angle, (int) $x + 1, (int) $y + 1, $dark, $font, $text);
                imagettftext($img, $fs, $angle, (int) $x, (int) $y, $light, $font, $text);
            }
        }
        return;
    }

    // Fallback for hosts without FreeType: horizontal staggered rows with a built-in font.
    $fontId = $w >= 480 ? 3 : 2;
    $cw = imagefontwidth($fontId);
    $rowGap = imagefontheight($fontId) * 4;
    $row = 0;
    for ($y = 4; $y < $h; $y += $rowGap, $row++) {
        $text = $row % 2 === 0 ? 'TANISH ARTS - PREVIEW' : ta_ascii($client);
        $step = strlen($text) * $cw + 60;
        for ($x = -($row % 3) * 70; $x < $w; $x += $step) {
            imagestring($img, $fontId, $x + 1, (int) $y + 1, $text, $dark);
            imagestring($img, $fontId, $x, (int) $y, $text, $light);
        }
    }
}

/** Square avatar, center-cropped to 176px, stored as JPEG. */
function ta_make_avatar(string $bytes, int $orientation): string
{
    ta_prepare_memory();
    $src = ta_decode($bytes, $orientation);
    $sw = imagesx($src);
    $sh = imagesy($src);
    $side = min($sw, $sh);
    $dst = imagecreatetruecolor(176, 176);
    imagefill($dst, 0, 0, imagecolorallocate($dst, 255, 255, 255));
    imagecopyresampled($dst, $src, 0, 0, (int) (($sw - $side) / 2), (int) (($sh - $side) / 2), 176, 176, $side, $side);
    imagedestroy($src);
    ob_start();
    imagejpeg($dst, null, 85);
    imagedestroy($dst);
    return ob_get_clean();
}

function ta_upper(string $s): string
{
    return function_exists('mb_strtoupper') ? mb_strtoupper($s, 'UTF-8') : strtoupper($s);
}

function ta_ascii(string $s): string
{
    $s = preg_replace('/[^\x20-\x7E]/', '', $s);
    return trim($s) === '' ? 'CLIENT' : $s;
}

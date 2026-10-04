<?php
declare(strict_types=1);
require_once __DIR__ . '/bootstrap.php';

function upload_image(string $field): string
{
    $file = $_FILES[$field] ?? null;
    if (!$file || $file['error'] === UPLOAD_ERR_NO_FILE) return '';
    if (!is_array($file) || !is_int($file['error']) || $file['error'] !== UPLOAD_ERR_OK || !is_uploaded_file($file['tmp_name'])) throw new InvalidArgumentException('Bildet kunne ikke lastes opp.');
    if ($file['size'] > 5 * 1024 * 1024) throw new InvalidArgumentException('Bildet kan være høyst 5 MB.');
    $mime = (new finfo(FILEINFO_MIME_TYPE))->file($file['tmp_name']);
    if (!in_array($mime, ['image/jpeg', 'image/png', 'image/webp'], true)) throw new InvalidArgumentException('Velg et ekte JPEG-, PNG- eller WebP-bilde.');
    $size = @getimagesize($file['tmp_name']);
    if (!$size || $size[0] < 1 || $size[1] < 1 || $size[0] > 12000 || $size[1] > 12000 || $size[0] * $size[1] > 20000000) throw new InvalidArgumentException('Bildets dimensjoner er for store eller ugyldige.');
    if (!function_exists('imagecreatefromstring')) throw new InvalidArgumentException('Bildebehandling er ikke tilgjengelig på serveren.');
    $image = @imagecreatefromstring(file_get_contents($file['tmp_name']));
    if (!$image) throw new InvalidArgumentException('Bildet kunne ikke leses.');
    $ratio = min(1, 1800 / max($size[0], $size[1]));
    $output = imagecreatetruecolor(max(1, (int)round($size[0] * $ratio)), max(1, (int)round($size[1] * $ratio)));
    imagefill($output, 0, 0, imagecolorallocate($output, 255, 255, 255));
    imagecopyresampled($output, $image, 0, 0, 0, 0, imagesx($output), imagesy($output), $size[0], $size[1]);
    $relative = 'assets/uploads/' . bin2hex(random_bytes(20)) . '.jpg';
    $absolute = dirname(__DIR__) . '/' . $relative;
    try {
        if (!imagejpeg($output, $absolute, 85)) throw new InvalidArgumentException('Bildet kunne ikke lagres. Kontroller skrivetilgang.');
        chmod($absolute, 0644);
        if (function_exists('imagewebp')) foreach ([320, 900] as $width) {
            $variantWidth = min($width, imagesx($output));
            $variant = imagescale($output, $variantWidth, -1, IMG_BICUBIC_FIXED);
            if (!$variant) continue;
            $variantFile = substr($absolute, 0, -4) . '-' . $width . '.webp';
            try {
                if (imagewebp($variant, $variantFile, 80)) chmod($variantFile, 0644);
                elseif (is_file($variantFile)) unlink($variantFile);
            } finally { imagedestroy($variant); }
        }
    } finally { imagedestroy($image); imagedestroy($output); }
    return $relative;
}

function remove_unused_upload(string $path): void
{
    if (!preg_match('~^assets/uploads/[a-f0-9]{40}\\.jpg$~D', $path)) return;
    $used = (int)query('SELECT (SELECT COUNT(*) FROM services WHERE image = ?) + (SELECT COUNT(*) FROM gallery_items WHERE image = ?)', [$path, $path])->fetchColumn();
    if ($used === 0) {
        $absolute = dirname(__DIR__) . '/' . $path;
        foreach ([$absolute, substr($absolute, 0, -4) . '-320.webp', substr($absolute, 0, -4) . '-900.webp'] as $file) {
            if (is_file($file) && !@unlink($file)) error_log('LILY unused upload cleanup failed');
        }
    }
}

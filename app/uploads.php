<?php
declare(strict_types=1);
require_once __DIR__ . '/request-photos.php';

const PUBLIC_IMAGE_BYTES = 5 * 1024 * 1024;

function encode_public_image(string $source, string $mime, string $absolute): void
{
    $size = @getimagesize($source);
    if (!$size || $size[0] < 1 || $size[1] < 1 || $size[0] > 12000 || $size[1] > 12000 || $size[0] * $size[1] > 20000000) throw new InvalidArgumentException('Bildets dimensjoner er for store eller ugyldige.');
    if (!function_exists('imagewebp')) throw new RuntimeException('WebP processing unavailable');
    $image = @imagecreatefromstring(file_get_contents($source));
    if (!$image) throw new InvalidArgumentException('Bildet kunne ikke leses.');
    $files = [];
    $pending = [];
    $created = [];
    try {
        $image = orient_request_photo($image, ['mime' => $mime, 'path' => $source]);
        foreach (['' => 1800, '-900' => 900, '-320' => 320] as $suffix => $edge) {
            $ratio = min(1, $edge / max(imagesx($image), imagesy($image)));
            $output = imagecreatetruecolor(max(1, (int)round(imagesx($image) * $ratio)), max(1, (int)round(imagesy($image) * $ratio)));
            try {
                imagefill($output, 0, 0, imagecolorallocate($output, 255, 255, 255));
                imagecopyresampled($output, $image, 0, 0, 0, 0, imagesx($output), imagesy($output), imagesx($image), imagesy($image));
                $target = substr($absolute, 0, -5) . $suffix . '.webp';
                $file = dirname($absolute) . '/tmp-' . bin2hex(random_bytes(20)) . '.webp';
                $files[] = $file;
                $pending[$file] = $target;
                if (!imagewebp($output, $file, $suffix === '' ? 78 : 74) || !is_file($file) || filesize($file) === 0 || filesize($file) > PUBLIC_IMAGE_BYTES || !@getimagesize($file)) throw new RuntimeException('WebP encoding failed');
                chmod($file, 0644);
            } finally { imagedestroy($output); }
        }
        // Promote only after all encodings succeed, preserving legacy derivatives on failure.
        foreach (array_reverse($pending, true) as $file => $target) {
            $existed = is_file($target);
            if (!rename($file, $target)) throw new RuntimeException('WebP save failed');
            if (!$existed) $created[] = $target;
        }
    } catch (Throwable $error) {
        foreach ([...$files, ...$created] as $file) if (is_file($file)) @unlink($file);
        throw $error;
    } finally { imagedestroy($image); }
}

function upload_image(string $field): string
{
    $file = $_FILES[$field] ?? null;
    if ($file === null) return '';
    if (!is_array($file) || !is_int($file['error'] ?? null)) throw new InvalidArgumentException('Bildet kunne ikke lastes opp.');
    if ($file['error'] === UPLOAD_ERR_NO_FILE) return '';
    if (in_array($file['error'], [UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE], true)) throw new InvalidArgumentException('Bildet kan være høyst 5 MB.');
    if ($file['error'] !== UPLOAD_ERR_OK || !is_string($file['tmp_name'] ?? null) || !is_uploaded_file($file['tmp_name'])) throw new InvalidArgumentException('Bildet kunne ikke lastes opp.');
    $bytes = filesize($file['tmp_name']);
    if ($bytes === false || $bytes < 1 || $bytes > PUBLIC_IMAGE_BYTES) throw new InvalidArgumentException('Bildet kan være høyst 5 MB og må inneholde et bilde.');
    $mime = (new finfo(FILEINFO_MIME_TYPE))->file($file['tmp_name']);
    if (!in_array($mime, ['image/jpeg', 'image/png', 'image/webp'], true)) throw new InvalidArgumentException('Velg et ekte JPEG-, PNG- eller WebP-bilde.');
    $relative = 'assets/uploads/' . bin2hex(random_bytes(20)) . '.webp';
    encode_public_image($file['tmp_name'], $mime, dirname(__DIR__) . '/' . $relative);
    return $relative;
}

function remove_unused_upload(string $path): void
{
    if (!preg_match('~^assets/uploads/[a-f0-9]{40}\.(?:jpg|png|webp)$~D', $path)) return;
    $base = preg_replace('/\.(?:jpg|png|webp)$/', '', $path);
    $paths = [$base . '.jpg', $base . '.png', $base . '.webp'];
    $used = (int)query('SELECT (SELECT COUNT(*) FROM services WHERE image IN (?,?,?)) + (SELECT COUNT(*) FROM gallery_items WHERE image IN (?,?,?))', [...$paths, ...$paths])->fetchColumn();
    if ($used === 0) foreach ([...$paths, $base . '-320.webp', $base . '-900.webp'] as $file) {
        $absolute = dirname(__DIR__) . '/' . $file;
        if (is_file($absolute) && !@unlink($absolute)) error_log('fiksitt unused upload cleanup failed');
    }
}

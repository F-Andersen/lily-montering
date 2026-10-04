<?php
declare(strict_types=1);
require_once __DIR__ . '/bootstrap.php';

const REQUEST_PHOTO_LIMIT = 5;
const REQUEST_PHOTO_BYTES = 5 * 1024 * 1024;
const REQUEST_BODY_BYTES = 26 * 1024 * 1024;

function request_photo_uploads(): array
{
    if (array_diff(array_keys($_FILES), ['photos'])) throw new InvalidArgumentException('Ugyldig bildefelt.');
    if (!isset($_FILES['photos'])) return [];
    $files = $_FILES['photos'];
    if (!is_array($files)) throw new InvalidArgumentException('Ugyldig bildeopplasting.');
    foreach (['name', 'type', 'tmp_name', 'error', 'size'] as $key) {
        if (!is_array($files[$key] ?? null)) throw new InvalidArgumentException('Ugyldig bildeopplasting.');
        if ($key !== 'name' && array_keys($files[$key]) !== array_keys($files['name'])) throw new InvalidArgumentException('Ugyldig bildeopplasting.');
    }
    if (count($files['name']) > REQUEST_PHOTO_LIMIT) throw new InvalidArgumentException('Du kan legge ved høyst 5 bilder.');
    $uploads = [];
    foreach (array_keys($files['name']) as $key) {
        $error = $files['error'][$key];
        if (!is_int($error)) throw new InvalidArgumentException('Ugyldig bildeopplasting.');
        if ($error === UPLOAD_ERR_NO_FILE) continue;
        if (in_array($error, [UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE], true)) throw new InvalidArgumentException('Hvert bilde kan være høyst 5 MB.');
        $path = $files['tmp_name'][$key];
        $bytes = $files['size'][$key];
        if ($error !== UPLOAD_ERR_OK || !is_string($path) || !is_int($bytes) || !is_uploaded_file($path)) throw new InvalidArgumentException('Et bilde kunne ikke lastes opp. Prøv igjen.');
        if ($bytes < 1 || $bytes > REQUEST_PHOTO_BYTES || filesize($path) > REQUEST_PHOTO_BYTES) throw new InvalidArgumentException('Hvert bilde kan være høyst 5 MB.');
        $mime = (new finfo(FILEINFO_MIME_TYPE))->file($path);
        if (!in_array($mime, ['image/jpeg', 'image/png', 'image/webp'], true)) throw new InvalidArgumentException('Velg ekte JPEG-, PNG- eller WebP-bilder. HEIC støttes ikke.');
        $size = @getimagesize($path);
        if (!$size || ($size['mime'] ?? '') !== $mime || $size[0] < 1 || $size[1] < 1 || $size[0] > 12000 || $size[1] > 12000 || $size[0] * $size[1] > 20000000) throw new InvalidArgumentException('Et bilde er ugyldig eller har mer enn 20 megapiksler.');
        $uploads[] = ['path' => $path, 'mime' => $mime];
    }
    return $uploads;
}

function request_photo_directory(): string
{
    $directory = rtrim((string)config()['request_photo_dir'], '/\\');
    if ($directory === '' || (!is_dir($directory) && !@mkdir($directory, 0700, true) && !is_dir($directory))) throw new RuntimeException('Private photo storage unavailable');
    $directory = realpath($directory);
    $root = realpath(dirname(__DIR__));
    if (!$directory || !$root || $directory === $root || str_starts_with($directory, $root . DIRECTORY_SEPARATOR) || !is_writable($directory)) throw new RuntimeException('Photo storage must be writable and outside the document root');
    return $directory;
}

function request_photo_path(string $filename, bool $thumbnail = false): string
{
    if (!preg_match('/^[a-f0-9]{40}\.webp$/D', $filename)) throw new InvalidArgumentException('Invalid managed filename');
    return request_photo_directory() . '/' . ($thumbnail ? substr($filename, 0, -5) . '-320.webp' : $filename);
}

function remove_request_photo_files(array $photos): void
{
    foreach ($photos as $photo) foreach ([false, true] as $thumbnail) {
        try {
            $path = request_photo_path($photo['filename'], $thumbnail);
            if (is_file($path) && !@unlink($path)) error_log('fiksitt private photo cleanup failed');
        } catch (Throwable $error) { safe_log('private photo cleanup unavailable', $error); }
    }
}

function orient_request_photo(GdImage $image, array $upload): GdImage
{
    $orientation = 1;
    if ($upload['mime'] === 'image/jpeg' && function_exists('exif_read_data')) {
        $exif = @exif_read_data($upload['path'], 'IFD0');
        $orientation = (int)($exif['Orientation'] ?? 1);
    }
    if (in_array($orientation, [2, 4, 5, 7], true)) imageflip($image, IMG_FLIP_HORIZONTAL);
    $angle = match ($orientation) { 3, 4 => 180, 5, 8 => 90, 6, 7 => -90, default => 0 };
    if ($angle !== 0) {
        $rotated = imagerotate($image, $angle, 0);
        if (!$rotated) throw new InvalidArgumentException('Bildet kunne ikke roteres.');
        imagedestroy($image);
        $image = $rotated;
    }
    return $image;
}

function write_private_webp(GdImage $image, string $path): int
{
    if (!imagewebp($image, $path, 78) || !is_file($path) || filesize($path) === 0 || !@getimagesize($path)) throw new RuntimeException('Photo encoding failed');
    if (!chmod($path, 0600)) throw new RuntimeException('Private photo permissions failed');
    return (int)filesize($path);
}

function prepare_request_photos(array $uploads): array
{
    if (!$uploads) return [];
    if (!function_exists('imagewebp')) throw new RuntimeException('WebP processing unavailable');
    request_photo_directory();
    $photos = [];
    try {
        foreach ($uploads as $upload) {
            $image = @imagecreatefromstring(file_get_contents($upload['path']));
            if (!$image) throw new InvalidArgumentException('Et bilde kunne ikke leses. Velg et annet bilde.');
            $output = $thumbnail = null;
            try {
                $image = orient_request_photo($image, $upload);
                $ratio = min(1, 1600 / max(imagesx($image), imagesy($image)));
                $width = max(1, (int)round(imagesx($image) * $ratio));
                $height = max(1, (int)round(imagesy($image) * $ratio));
                $output = imagecreatetruecolor($width, $height);
                imagefill($output, 0, 0, imagecolorallocate($output, 255, 255, 255));
                if (!imagecopyresampled($output, $image, 0, 0, 0, 0, $width, $height, imagesx($image), imagesy($image))) throw new RuntimeException('Photo resize failed');
                $filename = bin2hex(random_bytes(20)) . '.webp';
                $photos[] = ['filename' => $filename, 'width' => $width, 'height' => $height, 'bytes' => 0];
                $photos[array_key_last($photos)]['bytes'] = write_private_webp($output, request_photo_path($filename));
                $thumbnail = imagescale($output, min(320, $width), -1, IMG_BICUBIC_FIXED);
                if (!$thumbnail) throw new RuntimeException('Photo thumbnail failed');
                write_private_webp($thumbnail, request_photo_path($filename, true));
            } finally {
                imagedestroy($image);
                if ($output) imagedestroy($output);
                if ($thumbnail) imagedestroy($thumbnail);
            }
        }
        return $photos;
    } catch (Throwable $error) {
        remove_request_photo_files($photos);
        throw $error;
    }
}

function delete_request_with_photos(int $id): void
{
    $pdo = db();
    $pdo->beginTransaction();
    try {
        if (!query('SELECT id FROM contact_requests WHERE id = ? FOR UPDATE', [$id])->fetch()) throw new InvalidArgumentException('Forespørselen finnes ikke.');
        $photos = query('SELECT filename FROM request_photos WHERE request_id = ?', [$id])->fetchAll();
        query('DELETE FROM contact_requests WHERE id = ?', [$id]);
        $pdo->commit();
    } catch (Throwable $error) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        throw $error;
    }
    remove_request_photo_files($photos);
}

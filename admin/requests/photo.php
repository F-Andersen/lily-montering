<?php
declare(strict_types=1);
require_once dirname(__DIR__, 2) . '/app/auth.php';
require_once dirname(__DIR__, 2) . '/app/request-photos.php';
require_admin();
security_headers();
header('X-Robots-Tag: noindex, nofollow');
header('Cache-Control: private, no-store');
if (!in_array($_SERVER['REQUEST_METHOD'] ?? '', ['GET', 'HEAD'], true)) {
    header('Allow: GET, HEAD');
    http_response_code(405);
    exit;
}
try {
    $id = is_string($_GET['id'] ?? null) ? filter_var($_GET['id'], FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]) : false;
    $photo = $id ? query('SELECT p.filename FROM request_photos p JOIN contact_requests r ON r.id = p.request_id WHERE p.id = ?', [$id])->fetch() : false;
    if (!$photo) { http_response_code(404); exit; }
    $path = request_photo_path($photo['filename'], ($_GET['size'] ?? '') === 'thumb');
    if (!is_file($path) || !is_readable($path)) { http_response_code(404); exit; }
    header('Content-Type: image/webp');
    header('Content-Length: ' . filesize($path));
    header('Content-Disposition: ' . (($_GET['download'] ?? '') === '1' ? 'attachment' : 'inline') . '; filename="fiksitt-bilde-' . $id . '.webp"');
    session_write_close();
    if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'HEAD') readfile($path);
} catch (Throwable $error) {
    safe_log('request photo unavailable', $error);
    http_response_code(503);
}

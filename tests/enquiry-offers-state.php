<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') exit(1);
require dirname(__DIR__) . '/app/content.php';
require dirname(__DIR__) . '/app/request-photos.php';
$config = config();
if ($config['app_env'] !== 'local' || $config['database']['name'] !== 'fiksitt_reviews_qa' || $config['app_url'] !== 'http://127.0.0.1:18084' || $config['smtp']['host'] !== 'mailpit') throw new RuntimeException('Isolated QA/Mailpit only');
$tag = getenv('QA_TAG');
if (!is_string($tag) || !preg_match('/^QA OFFERS [a-f0-9]{12}$/D', $tag)) throw new RuntimeException('Invalid test tag');
$mode = $argv[1] ?? '';
if ($mode === 'info') {
    echo json_encode(['offers' => array_slice(home_service_offers(), 0, 4), 'legacy' => query('SELECT title FROM services WHERE active=1 ORDER BY id LIMIT 1')->fetchColumn()]);
} elseif ($mode === 'state') {
    $rows = query('SELECT id,name,service,message,email_sent FROM contact_requests WHERE name IN (?,?) ORDER BY id', [$tag, $tag . ' legacy'])->fetchAll();
    foreach ($rows as &$row) $row['photos'] = query('SELECT filename,bytes,width,height FROM request_photos WHERE request_id=?', [$row['id']])->fetchAll();
    echo json_encode($rows);
} elseif ($mode === 'cleanup') {
    $photos = query('SELECT p.filename FROM request_photos p JOIN contact_requests r ON r.id=p.request_id WHERE r.name IN (?,?)', [$tag, $tag . ' legacy'])->fetchAll();
    remove_request_photo_files($photos);
    query('DELETE FROM contact_requests WHERE name IN (?,?)', [$tag, $tag . ' legacy']);
    // Only this disposable container's limiter storage is reset.
    $dir = sys_get_temp_dir() . '/lily-limits-' . substr(hash('sha256', dirname(__DIR__)), 0, 12);
    foreach (glob($dir . '/*.json') ?: [] as $file) unlink($file);
    echo 'Own enquiry fixtures cleaned.';
} else throw new RuntimeException('Unknown mode');

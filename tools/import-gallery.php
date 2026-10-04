<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') exit(1);
require dirname(__DIR__) . '/app/bootstrap.php';
$items = json_decode(file_get_contents(dirname(__DIR__) . '/assets/images/gallery-selection.json'), true, 32, JSON_THROW_ON_ERROR);
foreach ($items as $item) {
    if (!str_starts_with($item['image'], 'assets/images/') || !str_ends_with($item['image'], '.webp') || !image_dimensions($item['image']) || mb_strlen($item['caption']) > 160 || mb_strlen($item['alt_text']) > 255) throw new RuntimeException('Invalid gallery manifest');
}
if (($argv[1] ?? '') !== '--apply') { echo 'Dry run: ' . count($items) . " curated gallery entries; use --apply after a DB backup.\n"; exit; }
$lock = 'fiksitt-gallery-import-' . config()['database']['name'];
if ((int)query('SELECT GET_LOCK(?, 5)', [$lock])->fetchColumn() !== 1) throw new RuntimeException('Import already running');
$inserted = 0;
try {
    db()->beginTransaction();
    foreach ($items as $item) {
        if (query('SELECT id FROM gallery_items WHERE image = ?', [$item['image']])->fetch()) continue;
        query('INSERT INTO gallery_items(image,caption,alt_text,sort_order,active,featured) VALUES (?,?,?,?,1,1)', [$item['image'], $item['caption'], $item['alt_text'], $item['sort_order']]);
        $inserted++;
    }
    db()->commit();
} catch (Throwable $error) { if (db()->inTransaction()) db()->rollBack(); throw $error; }
finally { query('SELECT RELEASE_LOCK(?)', [$lock]); }
echo "Added $inserted real work photos; existing entries unchanged.\n";

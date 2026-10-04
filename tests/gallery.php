<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') exit(1);
require dirname(__DIR__) . '/app/gallery.php';
require dirname(__DIR__) . '/app/uploads.php';
if (config()['app_env'] !== 'local' || config()['database']['name'] !== 'fiksitt_reviews_qa' || config()['app_url'] !== 'http://127.0.0.1:18084') throw new RuntimeException('Isolated QA only');
function check_gallery(bool $condition, string $message): void { if (!$condition) throw new RuntimeException($message); }
function render_gallery(array $items): string { ob_start(); gallery_section($items); return ob_get_clean(); }
$groups = [];
function gallery_pass(string $name): void { global $groups; $groups[] = $name; echo "PASS $name\n"; }
$selection = json_decode(file_get_contents(dirname(__DIR__) . '/assets/images/gallery-selection.json'), true, 32, JSON_THROW_ON_ERROR);
$before = query('SELECT * FROM gallery_items ORDER BY id')->fetchAll();
$beforeIds = array_column($before, 'id');
$created = [];
$legacy = 'assets/uploads/' . bin2hex(random_bytes(20)) . '.jpg';
try {
    foreach (glob(dirname(__DIR__) . '/assets/images/*.webp') as $file) {
        $size = getimagesize($file);
        check_gallery($size && $size['mime'] === 'image/webp' && filesize($file) > 0 && filesize($file) <= PUBLIC_IMAGE_BYTES, 'Invalid bundled WebP');
    }
    foreach ($selection as $item) check_gallery((bool)image_dimensions($item['image']), 'Missing curated photo');
    $html = image_html('assets/images/garderobe-under-skratt-tak.jpg', '<script>bad</script>', sizes: '64px');
    check_gallery(str_contains($html, 'garderobe-under-skratt-tak.webp') && !str_contains($html, '.jpg') && str_contains($html, '-320.webp') && str_contains($html, 'sizes="64px"') && str_contains($html, '&lt;script&gt;'), 'WebP selection or escaping failed');
    check_gallery(image_html('../config.php', '') === '' && image_html('assets/images/missing.webp', '') === '', 'Unsafe/missing image rendered');
    gallery_pass('Bundled WebP families, responsive legacy resolution, escaping and path safety');
    $empty = render_gallery([]);
    check_gallery(str_contains($empty, 'kommer snart') && !str_contains($empty, 'gallery-track'), 'Empty gallery is broken');
    $item = $selection[0]; $item['caption'] = '<img src=x onerror=alert(1)>';
    $html = render_gallery([$item, ['image' => 'assets/images/missing.webp', 'caption' => 'missing', 'alt_text' => 'missing']]);
    check_gallery(substr_count($html, '<figure') === 1 && str_contains($html, '&lt;img src=x') && !str_contains($html, '<img src=x') && str_contains($html, '<a href="') && str_contains($html, '<dialog'), 'Gallery markup/escaping/fallback failed');
    $mosaic = render_gallery(array_merge($selection, array_slice($selection, 0, 3)));
    check_gallery(substr_count($mosaic, 'data-gallery-page') === 3 && substr_count($mosaic, 'data-gallery-open=') === 9, 'Four-photo grouping lost or duplicated images');
    foreach (range(0, 8) as $index) check_gallery(substr_count($mosaic, 'data-gallery-open="' . $index . '"') === 1, 'Lightbox indices must remain continuous across pages');
    gallery_pass('Empty/single/missing gallery states, safe captions and native no-JS full-photo links');
    $tag = 'QA-GALLERY-' . bin2hex(random_bytes(6));
    foreach ([[0, 1], [1, 0]] as [$active, $featured]) {
        query('INSERT INTO gallery_items(image,caption,alt_text,active,featured) VALUES (?,?,?,?,?)', ['assets/images/garderobe-under-skratt-tak.webp', $tag, 'TEST ONLY', $active, $featured]);
        $created[] = (int)db()->lastInsertId();
    }
    foreach (public_gallery() as $row) check_gallery(!in_array((int)$row['id'], $created, true), 'Inactive/unfeatured gallery exposed');
    gallery_pass('Gallery visibility follows active/featured admin settings');
    $command = escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg(dirname(__DIR__) . '/tools/import-gallery.php') . ' --apply';
    exec($command, $output, $status); check_gallery($status === 0, 'Import failed');
    $after = query('SELECT * FROM gallery_items ORDER BY id')->fetchAll();
    $original = array_values(array_filter($after, static fn($row) => in_array($row['id'], $beforeIds, true)));
    check_gallery($before === $original, 'Existing gallery changed');
    foreach ($selection as $item) check_gallery((int)query('SELECT COUNT(*) FROM gallery_items WHERE image = ? AND caption <> ?', [$item['image'], $tag])->fetchColumn() === 1, 'Duplicate/missing curated import');
    exec($command, $output, $status); check_gallery($status === 0 && $after === query('SELECT * FROM gallery_items ORDER BY id')->fetchAll(), 'Import not idempotent');
    db()->beginTransaction();
    try {
        db()->exec(file_get_contents(dirname(__DIR__) . '/database/seed.sql'));
        check_gallery($after === query('SELECT * FROM gallery_items ORDER BY id')->fetchAll(), 'Fresh seed duplicated gallery entries');
    } finally { db()->rollBack(); }
    gallery_pass('Curated import is idempotent and preserves original records');
    $source = dirname(__DIR__) . '/' . $legacy;
    copy(dirname(__DIR__) . '/assets/images/garderobe-under-skratt-tak.jpg', $source);
    $target = preg_replace('/\.jpg$/', '.webp', $source);
    encode_public_image($source, 'image/jpeg', $target);
    query('INSERT INTO gallery_items(image,caption,alt_text) VALUES (?,?,?)', [preg_replace('/\.jpg$/', '.webp', $legacy), $tag, 'TEST ONLY']);
    $legacyId = (int)db()->lastInsertId();
    remove_unused_upload($legacy);
    check_gallery(is_file($source) && is_file($target), 'Legacy sibling reference not protected');
    query('DELETE FROM gallery_items WHERE id = ?', [$legacyId]);
    remove_unused_upload($legacy);
    check_gallery(!is_file($source) && !is_file($target) && !is_file(preg_replace('/\.webp$/', '-320.webp', $target)), 'Legacy family not cleaned');
    gallery_pass('Legacy public photo conversion and cross-extension reference-safe cleanup');
    file_put_contents(dirname(__DIR__) . '/.qa/gallery-core-results.json', json_encode(['passed' => true, 'groups' => $groups], JSON_PRETTY_PRINT));
} finally {
    foreach (query('SELECT id FROM gallery_items')->fetchAll() as $row) if (!in_array($row['id'], $beforeIds, true)) query('DELETE FROM gallery_items WHERE id = ?', [$row['id']]);
    remove_unused_upload($legacy);
    echo "Isolated gallery fixtures cleaned.\n";
}

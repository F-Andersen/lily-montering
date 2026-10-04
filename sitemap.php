<?php
declare(strict_types=1);
require_once __DIR__ . '/app/seo.php';
if (config()['pretty_urls']) public_alias_redirect('sitemap.xml', ['sitemap.php']);
header('Content-Type: application/xml; charset=UTF-8');
header('Cache-Control: no-store');
header('X-Robots-Tag: noindex');
$base = absolute_url();
$available = true;
$services = public_services($available);
if ($base === '' || !$available) { http_response_code(503); exit('<?xml version="1.0" encoding="UTF-8"?><urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9"/>'); }
echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n" . '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">';
foreach (['', config()['pretty_urls'] ? 'tjenester' : 'tjenester.php'] as $path) echo '<url><loc>' . e(absolute_url($path)) . '</loc></url>';
foreach ($services as $r) {
    $path = service_canonical_path($r['slug']);
    echo '<url><loc>' . e(absolute_url($path)) . '</loc><lastmod>' . e(substr($r['updated_at'], 0, 10)) . '</lastmod></url>';
}
echo '</urlset>';

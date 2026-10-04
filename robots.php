<?php
declare(strict_types=1);
require_once __DIR__ . '/app/seo.php';
header('Content-Type: text/plain; charset=UTF-8');
header('Cache-Control: no-store');
header('X-Robots-Tag: noindex');
echo "User-agent: *\n";
if (!seo_indexable()) { echo "Disallow: /\n"; exit; }
echo "Allow: /\n";
foreach (['admin/', 'app/', 'database/', 'tests/', 'foto/'] as $path) echo 'Disallow: ' . url($path) . "\n";
echo 'Sitemap: ' . absolute_url(config()['pretty_urls'] ? 'sitemap.xml' : 'sitemap.php') . "\n";

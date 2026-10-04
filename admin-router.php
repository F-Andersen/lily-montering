<?php
declare(strict_types=1);
require __DIR__ . '/app/bootstrap.php';
$path = parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH) ?: '';
$prefix = app_path() . '/' . admin_path();
$routes = [''=>'index.php', 'login.php'=>'login.php', 'logout.php'=>'logout.php', 'language.php'=>'language.php', 'setup.php'=>'setup.php', 'requests/'=>'requests/index.php', 'requests/photo.php'=>'requests/photo.php', 'reviews/'=>'reviews/index.php', 'services/'=>'services/index.php', 'categories/'=>'categories/index.php', 'gallery/'=>'gallery/index.php', 'seo/'=>'seo/index.php', 'settings/'=>'settings/index.php', 'account/'=>'account/index.php'];
if ($path === $prefix) { header('Location: ' . $prefix . '/', true, 308); exit; }
if (!str_starts_with($path, $prefix . '/')) { require __DIR__ . '/404.php'; exit; }
$route = substr($path, strlen($prefix) + 1);
if (in_array($route, ['admin.css', 'admin.js'], true)) {
    header('Content-Type: ' . ($route === 'admin.css' ? 'text/css' : 'application/javascript') . '; charset=UTF-8');
    header('X-Content-Type-Options: nosniff');
    header('X-Robots-Tag: noindex, nofollow');
    header('Cache-Control: private, max-age=3600');
    readfile(__DIR__ . '/admin/' . $route);
    exit;
}
if (!isset($routes[$route])) { require __DIR__ . '/404.php'; exit; }
require __DIR__ . '/admin/' . $routes[$route];

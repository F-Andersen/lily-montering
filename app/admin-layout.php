<?php
declare(strict_types=1);
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/seo.php';

function admin_header(string $title, bool $private = true): void
{
    security_headers();
    header('X-Robots-Tag: noindex, nofollow');
    $nav = ['' => 'Oversikt', 'requests/' => 'Forespørsler', 'reviews/' => 'Omtaler', 'services/' => 'Tjenester', 'categories/' => 'Kategorier', 'gallery/' => 'Bilder', 'seo/' => 'SEO', 'settings/' => 'Innstillinger', 'account/' => 'Konto'];
    ?><!doctype html>
<html lang="nb"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><meta name="robots" content="noindex,nofollow"><title><?= e($title) ?> | <?= e(settings()['company_name']) ?> admin</title><link rel="icon" href="<?= e(url('assets/fiksitt-icon-v2-32.png')) ?>"><link rel="stylesheet" href="<?= e(url('admin/admin.css?v=8')) ?>"><script src="<?= e(url('admin/admin.js?v=5')) ?>" defer></script></head>
<body><a class="skip-link" href="#main">Hopp til innhold</a>
<?php if ($private): ?>
<aside class="sidebar"><a class="admin-brand" href="<?= e(url('admin/')) ?>" aria-label="<?= e(settings()['company_name']) ?> admin"><img src="<?= e(url('assets/fiksitt-wordmark-v2.webp')) ?>" width="720" height="300" alt=""></a>
<details class="admin-nav" open><summary>Meny</summary><nav aria-label="Administrasjon">
<?php foreach ($nav as $path => $label): ?><a href="<?= e(url('admin/' . $path)) ?>" <?= $title === $label ? 'aria-current="page"' : '' ?>><?= e($label) ?></a><?php endforeach ?>
<a href="<?= e(url()) ?>">Åpne nettsiden ↗</a></nav></details>
<form method="post" action="<?= e(url('admin/logout.php')) ?>"><?= csrf_input() ?><button class="secondary" type="submit">Logg ut</button></form></aside>
<?php endif ?>
<main id="main" class="<?= $private ? 'admin-main' : 'auth-main' ?>"><?php if (!$private): ?><a class="admin-brand" href="<?= e(url()) ?>" aria-label="<?= e(settings()['company_name']) ?>"><img src="<?= e(url('assets/fiksitt-wordmark-v2.webp')) ?>" width="720" height="300" alt=""></a><?php endif ?><header class="page-heading"><h1><?= e($title) ?></h1></header>
<?php if (!empty($_SESSION['flash'])): ?><p class="notice" role="status"><?= e($_SESSION['flash']) ?></p><?php unset($_SESSION['flash']); endif ?>
<?php
}

function admin_footer(): void
{
    echo '</main></body></html>';
}

function admin_error(string $message): void
{
    echo '<p class="notice error" role="alert">' . e($message) . '</p>';
}

function admin_page(int $total, int $perPage = 30): array
{
    $requested = is_string($_GET['page'] ?? null) ? filter_var($_GET['page'], FILTER_VALIDATE_INT) : 1;
    $pages = max(1, (int)ceil($total / $perPage));
    $page = min($pages, max(1, $requested === false ? 1 : (int)$requested));
    return [$page, $pages, ($page - 1) * $perPage];
}

function admin_pagination(int $page, int $pages, array $filters): void
{
    if ($pages <= 1) return;
    ?><nav class="pagination" aria-label="Sider"><span>Side <?= $page ?> av <?= $pages ?></span><?php if ($page > 1): ?><a href="?<?= e(http_build_query($filters + ['page' => $page - 1])) ?>">← Forrige</a><?php endif ?><?php if ($page < $pages): ?><a href="?<?= e(http_build_query($filters + ['page' => $page + 1])) ?>">Neste →</a><?php endif ?></nav><?php
}

function admin_input(string $name, string $label, mixed $value, string $type = 'text', int $max = 160, bool $required = false): void
{
    ?><label class="field" for="<?= e($name) ?>"><span><?= e($label) ?></span><input id="<?= e($name) ?>" name="<?= e($name) ?>" type="<?= e($type) ?>" value="<?= e($value) ?>" <?= $type === 'number' ? 'min="-99999" max="99999"' : 'maxlength="' . $max . '"' ?> <?= $required ? 'required' : '' ?>></label><?php
}

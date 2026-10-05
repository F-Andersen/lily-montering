<?php
declare(strict_types=1);
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/seo.php';

function admin_header(string $title, bool $private = true): void
{
    security_headers();
    header('X-Robots-Tag: noindex, nofollow');
    $nav = ['' => admin_t('Oversikt'), 'requests/' => admin_t('Forespørsler'), 'reviews/' => admin_t('Omtaler'), 'services/' => admin_t('Tjenester'), 'categories/' => admin_t('Kategorier'), 'gallery/' => admin_t('Bilder'), 'seo/' => admin_t('SEO'), 'settings/' => admin_t('Innstillinger'), 'account/' => admin_t('Konto')];
    $current = parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH) ?: '';
    $prefix = app_path() . '/' . admin_path() . '/';
    $return = str_starts_with($current, $prefix) ? substr($current, strlen($prefix)) : 'login.php';
    if (!empty($_SERVER['QUERY_STRING'])) $return .= '?' . $_SERVER['QUERY_STRING'];
    ?><!doctype html>
<html lang="<?= e(admin_locale()) ?>"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><meta name="robots" content="noindex,nofollow"><title><?= e(admin_t($title)) ?> | <?= e(settings()['company_name']) ?> admin</title><link rel="icon" href="<?= e(url('assets/fiksitt-icon-v2-32.png')) ?>"><link rel="stylesheet" href="<?= e(url('admin/admin.css?v=11')) ?>"><script src="<?= e(url('admin/admin.js?v=6')) ?>" defer></script></head>
<body data-copy-success="<?= e(admin_t('Lenken er kopiert.')) ?>" data-copy-error="<?= e(admin_t('Kunne ikke kopiere lenken.')) ?>" data-upload-max="<?= e(admin_t('Bildet kan være høyst 5 MB og må inneholde et bilde.')) ?>" data-upload-type="<?= e(admin_t('Velg JPEG-, PNG- eller WebP-bilder.')) ?>" data-char-label="<?= e(admin_t('tegn')) ?>"><a class="skip-link" href="#main"><?= e(admin_t('Hopp til innhold')) ?></a>
<?php if ($private): ?>
<aside class="sidebar"><a class="admin-brand" href="<?= e(url('admin/')) ?>" aria-label="<?= e(settings()['company_name']) ?> admin"><img src="<?= e(url('assets/fiksitt-wordmark-v2.webp')) ?>" width="720" height="300" alt=""></a>
<details class="admin-nav" open><summary><?= e(admin_t('Meny')) ?></summary><nav aria-label="<?= e(admin_t('Administrasjon')) ?>">
<?php foreach ($nav as $path => $label): ?><a href="<?= e(url('admin/' . $path)) ?>" <?= $title === $label ? 'aria-current="page"' : '' ?>><?= e(admin_t($label)) ?></a><?php endforeach ?>
<a href="<?= e(url()) ?>"><?= e(admin_t('Åpne nettsiden ↗')) ?></a></nav></details>
<form method="post" action="<?= e(url('admin/logout.php')) ?>"><?= csrf_input() ?><button class="secondary" type="submit"><?= e(admin_t('Logg ut')) ?></button></form></aside>
<?php endif ?>
<main id="main" class="<?= $private ? 'admin-main' : 'auth-main' ?>"><?php if (!$private): ?><a class="admin-brand" href="<?= e(url()) ?>" aria-label="<?= e(settings()['company_name']) ?>"><img src="<?= e(url('assets/fiksitt-wordmark-v2.webp')) ?>" width="720" height="300" alt=""></a><?php endif ?><header class="page-heading"><h1><?= e(admin_t($title)) ?></h1>
<form class="language-switch" method="post" action="<?= e(url('admin/language.php')) ?>"><?= csrf_input() ?><input type="hidden" name="return" value="<?= e($return) ?>"><label for="admin-locale"><?= e(admin_t('Språk')) ?></label><select id="admin-locale" name="locale"><option value="nb" <?= admin_locale() === 'nb' ? 'selected' : '' ?>>Norsk</option><option value="uk" <?= admin_locale() === 'uk' ? 'selected' : '' ?>>Українська</option></select><button class="icon-button secondary" type="submit" title="<?= e(admin_t('Bytt språk')) ?>" aria-label="<?= e(admin_t('Bytt språk')) ?>"><img src="<?= e(url('assets/icons/chevron-right.svg')) ?>" width="20" height="20" alt=""></button></form></header>
<?php if (!empty($_SESSION['flash'])): ?><p class="notice" role="status"><?= e(admin_t($_SESSION['flash'])) ?></p><?php unset($_SESSION['flash']); endif ?>
<?php
}

function admin_footer(): void
{
    echo '</main></body></html>';
}

function admin_error(string $message): void
{
    echo '<p class="notice error" role="alert">' . e(admin_t($message)) . '</p>';
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
    ?><nav class="pagination" aria-label="<?= e(admin_t('Sider')) ?>"><span><?= e(admin_t('Side')) ?> <?= $page ?> <?= e(admin_t('av')) ?> <?= $pages ?></span><?php if ($page > 1): ?><a href="?<?= e(http_build_query($filters + ['page' => $page - 1])) ?>"><?= e(admin_t('← Forrige')) ?></a><?php endif ?><?php if ($page < $pages): ?><a href="?<?= e(http_build_query($filters + ['page' => $page + 1])) ?>"><?= e(admin_t('Neste →')) ?></a><?php endif ?></nav><?php
}

function admin_input(string $name, string $label, mixed $value, string $type = 'text', int $max = 160, bool $required = false): void
{
    ?><label class="field" for="<?= e($name) ?>"><span><?= e(admin_t($label)) ?></span><input id="<?= e($name) ?>" name="<?= e($name) ?>" type="<?= e($type) ?>" value="<?= e($value) ?>" <?= $type === 'number' ? 'min="-99999" max="99999"' : 'maxlength="' . $max . '"' ?> <?= $required ? 'required' : '' ?>></label><?php
}

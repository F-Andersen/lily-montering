<?php
declare(strict_types=1);
require_once __DIR__ . '/content.php';

function seo_text(string $text, int $limit = 170): string
{
    $text = trim((string)preg_replace('/\s+/u', ' ', strip_tags($text)));
    if (mb_strlen($text, 'UTF-8') <= $limit) return $text;
    $short = mb_substr($text, 0, $limit - 3, 'UTF-8');
    $space = mb_strrpos($short, ' ', 0, 'UTF-8');
    return ($space !== false && $space > $limit / 2 ? mb_substr($short, 0, $space, 'UTF-8') : $short) . '...';
}

function seo_domain_valid(string $domain): bool
{
    $parts = parse_url($domain);
    $host = strtolower($parts['host'] ?? '');
    return filter_var($domain, FILTER_VALIDATE_URL) !== false
        && ($parts['scheme'] ?? '') === 'https'
        && !isset($parts['user']) && !isset($parts['pass']) && !isset($parts['query']) && !isset($parts['fragment'])
        && !isset($parts['port']) && ($parts['path'] ?? '') === ''
        && str_contains($host, '.') && !filter_var($host, FILTER_VALIDATE_IP)
        && !preg_match('/(?:^|\.)(?:localhost|local|test|invalid|example)$/D', $host);
}

function seo_indexable(): bool
{
    $s = settings();
    return config()['app_env'] === 'production' && $s['seo_indexable'] === '1'
        && seo_domain_valid(rtrim($s['public_domain'], '/'));
}

function service_canonical_path(string $slug = ''): string
{
    return config()['pretty_urls'] ? 'tjenester' . ($slug === '' ? '' : '/' . $slug)
        : 'tjenester.php' . ($slug === '' ? '' : '?slug=' . rawurlencode($slug));
}

function service_metadata(array $service): array
{
    $s = settings();
    return [
        'title' => seo_text($service['seo_title'] ?: $service['title'] . ' i ' . $s['service_region'] . ' | ' . $s['company_name'], 160),
        'description' => seo_text($service['seo_description'] ?: $service['short_description']),
    ];
}

function home_metadata(): array
{
    $s = settings();
    return [
        'title' => $s['home_seo_title'] ?: 'Møbelmontering og handyman i ' . $s['service_region'] . ' | ' . $s['company_name'],
        'description' => $s['home_seo_description'] ?: 'Hjelp med garderober, IKEA-, Bohus- og JYSK-møbler i ' . $s['service_region'] . '. Montering, levering og bortkjøring av emballasje. Send bilder for et uforpliktende tilbud.',
    ];
}

function breadcrumb_schema(array $items): array
{
    $elements = [];
    foreach ($items as $path => $name) $elements[] = ['@type' => 'ListItem', 'position' => count($elements) + 1, 'name' => $name, 'item' => absolute_url((string)$path)];
    return ['@type' => 'BreadcrumbList', 'itemListElement' => $elements];
}

function public_alias_redirect(string $target, array $aliases): void
{
    if (!in_array($_SERVER['REQUEST_METHOD'] ?? 'GET', ['GET', 'HEAD'], true)) return;
    $requested = parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH);
    foreach ($aliases as $alias) if ($requested === url($alias)) {
        header('Location: ' . url($target), true, 301);
        exit;
    }
}

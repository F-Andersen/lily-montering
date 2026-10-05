<?php
declare(strict_types=1);
require_once __DIR__ . '/seo.php';

function business_schema(): array
{
    $s = settings();
    $schema = ['@context' => 'https://schema.org', '@type' => 'LocalBusiness', 'name' => $s['company_name'], 'description' => 'Møbelmontering og handyman-tjenester i ' . $s['service_region'] . '.', 'areaServed' => ['@type' => 'AdministrativeArea', 'name' => $s['service_region']]];
    if ($s['phone'] !== '') $schema['telephone'] = $s['phone'];
    if ($s['email'] !== '') $schema['email'] = $s['email'];
    if (absolute_url() !== '') $schema['url'] = absolute_url();
    if (absolute_url() !== '') {
        $schema['@id'] = absolute_url() . '#business';
        $schema['image'] = absolute_url('assets/images/og-montering.webp');
        $schema['logo'] = absolute_url('assets/fiksitt-wordmark-v2.webp');
    }
    if ($s['social_url'] !== '') $schema['sameAs'] = [$s['social_url']];
    return $schema;
}

function home_schema(array $metadata): array
{
    $business = business_schema();
    $base = absolute_url();
    $offers = [];
    foreach (array_slice(home_service_offers(), 0, 4) as $offer) {
        $offers[] = ['@type' => 'Offer', 'itemOffered' => ['@type' => 'Service', 'name' => $offer, 'provider' => ['@id' => $base . '#business'], 'areaServed' => settings()['service_region']]];
    }
    $business['hasOfferCatalog'] = ['@type' => 'OfferCatalog', 'name' => 'Tjenester', 'itemListElement' => $offers];
    unset($business['@context']);
    return ['@context' => 'https://schema.org', '@graph' => [
        $business,
        ['@type' => 'WebSite', '@id' => $base . '#website', 'url' => $base, 'name' => settings()['company_name'], 'inLanguage' => 'nb-NO', 'publisher' => ['@id' => $base . '#business']],
        ['@type' => 'WebPage', '@id' => $base . '#webpage', 'url' => $base, 'name' => seo_text($metadata['title'], 160), 'description' => seo_text($metadata['description']), 'inLanguage' => 'nb-NO', 'isPartOf' => ['@id' => $base . '#website'], 'about' => ['@id' => $base . '#business']],
    ]];
}

function public_head(string $title, string $description, string $path = '', ?array $schema = null, bool $hero = false, string $image = 'assets/images/og-montering.webp', string $imageAlt = 'Møbelmontering og handyman', bool $allowIndex = true): void
{
    $json = security_headers($schema, $hero);
    $canonical = http_response_code() >= 400 ? '' : absolute_url($path);
    $title = seo_text($title, 160);
    $description = seo_text($description);
    $robots = seo_indexable() && $allowIndex && http_response_code() < 400 ? 'index,follow,max-image-preview:large' : 'noindex,follow';
    header('X-Robots-Tag: ' . $robots);
    $image = preferred_image_path($image) ?: 'assets/images/og-montering.webp';
    $imageSize = image_dimensions($image);
    ?><!doctype html><html lang="nb" class="no-js"><head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e($title) ?></title><meta name="description" content="<?= e($description) ?>">
<meta name="robots" content="<?= e($robots) ?>">
<?php if ($canonical !== ''): ?><link rel="canonical" href="<?= e($canonical) ?>"><meta property="og:url" content="<?= e($canonical) ?>"><?php endif ?>
<meta property="og:title" content="<?= e($title) ?>"><meta property="og:description" content="<?= e($description) ?>"><meta property="og:type" content="website"><meta property="og:locale" content="nb_NO">
<meta property="og:site_name" content="<?= e(settings()['company_name']) ?>">
<?php if (absolute_url() !== ''): ?><meta property="og:image" content="<?= e(absolute_url($image)) ?>"><meta property="og:image:alt" content="<?= e($imageAlt) ?>"><?php if ($imageSize): ?><meta property="og:image:width" content="<?= $imageSize[0] ?>"><meta property="og:image:height" content="<?= $imageSize[1] ?>"><?php endif ?><meta name="twitter:image" content="<?= e(absolute_url($image)) ?>"><meta name="twitter:image:alt" content="<?= e($imageAlt) ?>"><?php endif ?>
<meta name="twitter:card" content="summary_large_image"><meta name="twitter:title" content="<?= e($title) ?>"><meta name="twitter:description" content="<?= e($description) ?>">
<meta name="theme-color" content="#fafaf8"><link rel="icon" href="<?= e(url('assets/fiksitt-icon-v2-32.png')) ?>" sizes="32x32" type="image/png"><link rel="apple-touch-icon" href="<?= e(url('assets/fiksitt-icon-v2-180.png')) ?>"><link rel="manifest" href="<?= e(url('site.webmanifest?v=3')) ?>">
<?php if ($hero): ?><link rel="preload" href="<?= e(url('assets/images/og-montering.webp')) ?>" imagesrcset="<?= e(url('assets/images/og-montering-900.webp')) ?> 900w, <?= e(url('assets/images/og-montering.webp')) ?> 1200w" imagesizes="100vw" as="image" type="image/webp" fetchpriority="high"><?php endif ?>
<link rel="stylesheet" href="<?= e(url('styles.css?v=client-20261005-2')) ?>"><script src="<?= e(url('script.js?v=client-20261005-3')) ?>" defer></script><?php if ($hero): ?><script src="<?= e(url('gallery.js?v=20261005')) ?>" defer></script><?php endif ?>
<?php if ($json !== ''): ?><script type="application/ld+json"><?= $json ?></script><?php endif ?>
</head><body class="public-site"><a class="skip-link" href="#main">Hopp til innhold</a>
<?php
}

function public_header(): void
{
    $s = settings();
    ?><header class="site-header" aria-label="Hovednavigasjon">
<a class="brand" href="<?= e(url()) ?>" aria-label="<?= e($s['company_name']) ?>"><img class="brand-logo" src="<?= e(url('assets/fiksitt-wordmark-v2.webp')) ?>" width="720" height="300" alt=""><span class="sr-only"><strong><?= e($s['company_name']) ?></strong></span></a>
<button class="menu-toggle" type="button" aria-expanded="false" aria-controls="primary-nav" aria-label="Åpne meny"><span aria-hidden="true"></span><span aria-hidden="true"></span><span aria-hidden="true"></span></button>
<nav class="nav-links" id="primary-nav" aria-label="Sidenavigasjon"><a href="<?= e(url('#kontakt')) ?>">Kontakt oss</a><a href="<?= e(url('#om-oss')) ?>">Om oss</a><a href="<?= e(url('#tjenester')) ?>">Tjenester</a></nav>
<div class="header-actions"><?php if ($s['phone']): ?><a class="contact-pill" href="tel:<?= e(preg_replace('/[^0-9+]/', '', $s['phone'])) ?>">Ring oss</a><?php endif ?><a class="header-cta" href="<?= e(url('#contact-form')) ?>" data-order-open>Bestill nå</a></div></header>
<?php
}

function public_footer(): void
{
    $s = settings();
    ?><footer class="site-footer"><p>© <?= date('Y') ?> <?= e($s['company_name']) ?>. Møbelmontering og handyman i <?= e($s['service_region']) ?>.<?php if ($s['organization_number']): ?> Org.nr. <?= e($s['organization_number']) ?><?php endif ?></p>
<div class="footer-links"><a href="<?= e(service_url()) ?>">Alle tjenester</a><a href="<?= e(url('#om-oss')) ?>">Om oss</a><a href="<?= e(url('#omtaler')) ?>">Anmeldelser</a><a href="<?= e(url('#kontakt')) ?>">Kontakt oss</a><?php if ($s['social_url']): ?><a href="<?= e($s['social_url']) ?>" rel="noopener">Sosiale medier</a><?php endif ?></div></footer>
<a class="mobile-action" href="<?= e(url('#contact-form')) ?>" data-order-open>Bestill nå</a></body></html>
<?php
}

function service_card(array $service): void
{
    [$image, $alt] = service_media($service);
    ?><article class="service-card catalog-card"><?php if ($image !== ''): ?><a class="service-photo" href="<?= e(service_url($service['slug'])) ?>" tabindex="-1" aria-hidden="true"><?= image_html($image, $alt) ?></a><?php endif ?>
<div class="service-body"><p class="eyebrow"><?= e($service['category_title']) ?></p><h3><a href="<?= e(service_url($service['slug'])) ?>"><?= e($service['title']) ?></a></h3><p><?= e($service['short_description']) ?></p><?php if ($service['price_text']): ?><p class="price"><?= e($service['price_text']) ?></p><?php endif ?><a class="service-link" href="<?= e(service_url($service['slug'])) ?>">Se tjenesten →</a></div></article>
<?php
}

function public_error(int $status, string $title, string $message): never
{
    http_response_code($status);
    public_head($title . ' | ' . settings()['company_name'], $message);
    public_header();
    ?><main id="main" class="section error-content"><p class="eyebrow"><?= $status ?></p><h1><?= e($title) ?></h1><p><?= e($message) ?></p><a class="button button-primary" href="<?= e(url()) ?>">Til forsiden</a></main><?php
    public_footer();
    exit;
}

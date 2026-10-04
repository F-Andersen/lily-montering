<?php
declare(strict_types=1);
require_once __DIR__ . '/app/public-layout.php';
$available = true;
$services = public_services($available);
if (!$available) public_error(503, 'Tjenestene er midlertidig utilgjengelige', 'Prøv igjen senere, eller kontakt oss direkte.');
$slug = is_string($_GET['slug'] ?? null) ? $_GET['slug'] : '';
$requestPath = parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH) ?: '';
if (config()['pretty_urls'] && preg_match('~^' . preg_quote(url('tjenester/'), '~') . '([a-z0-9]+(?:-[a-z0-9]+)*)/?$~D', $requestPath, $match)) $slug = $match[1];
if (isset($_GET['slug']) && !preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/D', $slug)) public_error(404, 'Siden finnes ikke', 'Tjenesten finnes ikke eller er ikke tilgjengelig.');
$s = settings();
if ($slug !== '') {
    $found = array_values(array_filter($services, static fn($r) => $r['slug'] === $slug));
    if (!$found) public_error(404, 'Siden finnes ikke', 'Tjenesten finnes ikke eller er ikke tilgjengelig.');
    $service = $found[0];
    [$image, $imageAlt] = service_media($service);
    $paragraphs = preg_split('/\R\s*\R/', trim($service['full_description']), -1, PREG_SPLIT_NO_EMPTY);
    if ($paragraphs && trim($paragraphs[0]) === trim($service['short_description'])) array_shift($paragraphs);
    $canonicalPath = service_canonical_path($slug);
    if (config()['pretty_urls']) public_alias_redirect($canonicalPath, ['tjenester.php', $canonicalPath . '/']);
    $serviceSchema = ['@type' => 'Service', '@id' => absolute_url($canonicalPath) . '#service', 'url' => absolute_url($canonicalPath), 'name' => $service['title'], 'serviceType' => $service['category_title'], 'description' => $service['short_description'], 'provider' => business_schema(), 'areaServed' => $s['service_region']];
    if ($image !== '') $serviceSchema['image'] = absolute_url($image);
    $schema = ['@context' => 'https://schema.org', '@graph' => [$serviceSchema, breadcrumb_schema(['' => 'Forside', service_canonical_path() => 'Tjenester', $canonicalPath => $service['title']])]];
    $meta = service_metadata($service);
    public_head($meta['title'], $meta['description'], $canonicalPath, $schema, false, $image, $imageAlt);
    public_header();
    ?><main id="main"><section class="section service-detail">
<nav class="breadcrumbs" aria-label="Brødsmuler"><a href="<?= e(url()) ?>">Forside</a><span aria-hidden="true">/</span><a href="<?= e(service_url()) ?>">Tjenester</a><span aria-hidden="true">/</span><span><?= e($service['title']) ?></span></nav>
<div class="detail-layout<?= $image === '' ? ' detail-layout-text' : '' ?>"><div><p class="eyebrow"><?= e($service['category_title']) ?></p><h1><?= e($service['title']) ?></h1><p class="detail-intro"><?= e($service['short_description']) ?></p>
<?php foreach ($paragraphs as $paragraph): ?><p class="detail-paragraph"><?= nl2br(e($paragraph)) ?></p><?php endforeach ?>
<?php if ($service['price_text']): ?><p><strong>Pris:</strong> <?= e($service['price_text']) ?></p><?php endif ?>
<?php if ($service['duration_text']): ?><p><strong>Varighet:</strong> <?= e($service['duration_text']) ?></p><?php endif ?>
<div class="detail-actions"><a class="button button-primary" href="<?= e(url('?service=' . rawurlencode($service['title']) . '#kontakt')) ?>">Be om tilbud</a><a class="service-link" href="<?= e(url('#kontakt')) ?>">Kontakt oss →</a></div></div>
<?php if ($image !== ''): ?><div class="detail-image"><?= image_html($image, $imageAlt, '', false) ?></div><?php endif ?></div></section>
<?php $related = array_slice(array_values(array_filter($services, static fn($r) => $r['category_id'] === $service['category_id'] && $r['id'] !== $service['id'])), 0, 3); if ($related): ?><section class="section"><h2>Relaterte tjenester</h2><div class="service-grid"><?php foreach ($related as $r) service_card($r); ?></div></section><?php endif ?>
</main><?php public_footer(); exit;
}
$category = is_string($_GET['category'] ?? null) ? $_GET['category'] : '';
$categories = [];
foreach ($services as $r) $categories[$r['category_slug']] = $r['category_title'];
if ($category !== '' && !isset($categories[$category])) public_error(404, 'Kategorien finnes ikke', 'Velg en kategori fra tjenesteoversikten.');
if (config()['pretty_urls']) public_alias_redirect(service_canonical_path() . ($category !== '' ? '?category=' . rawurlencode($category) : ''), ['tjenester.php', 'tjenester/']);
$visible = array_values(array_filter($services, static fn($r) => $category === '' || $r['category_slug'] === $category));
$items = [];
foreach ($visible as $r) $items[] = ['@type' => 'ListItem', 'position' => count($items) + 1, 'url' => absolute_url(service_canonical_path($r['slug'])), 'name' => $r['title']];
$schema = ['@context' => 'https://schema.org', '@graph' => [['@type' => 'ItemList', 'itemListElement' => $items], breadcrumb_schema(['' => 'Forside', service_canonical_path() => 'Tjenester'])]];
public_head('Tjenester for hjem og næring | ' . $s['company_name'], 'Møbelmontering, garderober, kjøkken, veggmontering og handyman-tjenester i ' . $s['service_region'] . '. Finn riktig tjeneste og be om tilbud.', service_canonical_path(), $schema);
public_header();
?>
<main id="main"><section class="section catalog-section"><div class="section-heading"><p class="eyebrow">Tjenester</p><h1>Montering for hjem, kontor og lokaler</h1><p>Fra en enkel hylle til større innredningsprosjekter. Finn hjelpen som passer oppdraget ditt.</p></div>
<form class="catalog-filter" method="get" action="<?= e(service_url()) ?>"><label for="category">Kategori</label><select id="category" name="category"><option value="">Alle tjenester</option><?php foreach ($categories as $key => $label): ?><option value="<?= e($key) ?>" <?= $category === $key ? 'selected' : '' ?>><?= e($label) ?></option><?php endforeach ?></select><button class="button button-primary" type="submit">Vis tjenester</button></form>
<div class="service-grid"><?php foreach ($visible as $r) service_card($r); ?></div><?php if (!$visible): ?><p>Ingen tjenester i denne kategorien.</p><?php endif ?>
</section><section class="catalog-cta"><div><h2>Hva skal monteres?</h2><p>Send en kort beskrivelse, så avklarer vi oppdraget.</p><a class="button button-primary" href="<?= e(url('#kontakt')) ?>">Be om tilbud</a></div></section></main>
<?php public_footer(); ?>

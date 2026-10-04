<?php
declare(strict_types=1);
require_once dirname(__DIR__, 2) . '/app/admin-layout.php';
require_admin();
$s = settings();
$available = true;
$services = public_services($available);
$checks = [
    'Miljø' => config()['app_env'] === 'production' ? 'Produksjon' : 'Lokalt / test',
    'Indeksering' => seo_indexable() ? 'Tillatt' : 'Av (noindex)',
    'HTTPS-domene' => seo_domain_valid(rtrim($s['public_domain'], '/')) ? $s['public_domain'] : 'Ikke konfigurert',
    'Firmanavn' => $s['company_name'],
    'Område' => $s['service_region'] ?: 'Ikke oppgitt',
    'Telefon' => $s['phone'] ?: 'Ikke oppgitt',
    'E-post' => $s['email'] ?: 'Ikke oppgitt',
    'Org.nr.' => $s['organization_number'] ?: 'Ikke oppgitt',
];
admin_header('SEO');
?>
<nav class="quick-actions" aria-label="SEO"><a href="<?= e(url('admin/settings/')) ?>">SEO-innstillinger</a><a href="<?= e(url('sitemap.php')) ?>" target="_blank" rel="noopener">Sitemap</a><a href="<?= e(url('robots.php')) ?>" target="_blank" rel="noopener">Robots.txt</a><a href="https://search.google.com/search-console" target="_blank" rel="noopener noreferrer">Search Console</a></nav>
<dl class="request-detail"><?php foreach ($checks as $label => $value): ?><dt><?= e($label) ?></dt><dd><?= e($value) ?></dd><?php endforeach ?></dl>
<h2>Forside</h2>
<div class="search-preview"><p class="preview-url"><?= e(absolute_url()) ?></p><h3><?= e($s['home_seo_title'] ?: 'Møbelmontering og handyman i ' . $s['service_region'] . ' | ' . $s['company_name']) ?></h3><p><?= e(seo_text($s['home_seo_description'] ?: 'Møbelmontering, garderober, kjøkken og veggmontering i ' . $s['service_region'] . '. Praktisk hjelp for hjem og bedrifter. Be om et uforpliktende tilbud.')) ?></p></div>
<h2>Tjenestesider</h2>
<?php if (!$available): admin_error('Kunne ikke laste tjenestene.'); else: ?>
<div class="table-scroll"><table><thead><tr><th>Tjeneste</th><th>Tittel</th><th>Beskrivelse</th><th>Bilde</th></tr></thead><tbody>
<?php foreach ($services as $service): $meta = service_metadata($service); [$image] = service_media($service); ?>
<tr><td><a href="<?= e(url('admin/services/?id=' . $service['id'])) ?>"><?= e($service['title']) ?></a></td><td><?= e($meta['title']) ?><small class="field-meta"><?= mb_strlen($meta['title']) ?> tegn</small></td><td><?= e($meta['description']) ?><small class="field-meta"><?= mb_strlen($meta['description']) ?> tegn</small></td><td><?= $image !== '' ? 'Tilgjengelig' : 'Mangler' ?></td></tr>
<?php endforeach ?></tbody></table></div>
<?php endif; admin_footer(); ?>

<?php
declare(strict_types=1);
require_once dirname(__DIR__, 2) . '/app/admin-layout.php';
require_admin();
$s = settings();
$available = true;
$services = public_services($available);
$checks = [
    admin_t('Miljø') => config()['app_env'] === 'production' ? admin_t('Produksjon') : admin_t('Lokalt / test'),
    admin_t('Indeksering') => seo_indexable() ? admin_t('Tillatt') : admin_t('Av (noindex)'),
    admin_t('HTTPS-domene') => seo_domain_valid(rtrim($s['public_domain'], '/')) ? $s['public_domain'] : admin_t('Ikke konfigurert'),
    admin_t('Firmanavn') => $s['company_name'],
    admin_t('Område') => $s['service_region'] ?: admin_t('Ikke oppgitt'),
    admin_t('Telefon') => $s['phone'] ?: admin_t('Ikke oppgitt'),
    admin_t('E-post') => $s['email'] ?: admin_t('Ikke oppgitt'),
    admin_t('Org.nr.') => $s['organization_number'] ?: admin_t('Ikke oppgitt'),
];
admin_header(admin_t('SEO'));
?>
<nav class="quick-actions" aria-label="<?= e(admin_t('SEO')) ?>"><a href="<?= e(url('admin/settings/')) ?>"><?= e(admin_t('SEO-innstillinger')) ?></a><a href="<?= e(url('sitemap.php')) ?>" target="_blank" rel="noopener"><?= e(admin_t('Sitemap')) ?></a><a href="<?= e(url('robots.php')) ?>" target="_blank" rel="noopener">Robots.txt</a><a href="https://search.google.com/search-console" target="_blank" rel="noopener noreferrer">Search Console</a></nav>
<dl class="request-detail"><?php foreach ($checks as $label => $value): ?><dt><?= e($label) ?></dt><dd><?= e($value) ?></dd><?php endforeach ?></dl>
<h2><?= e(admin_t('Forside')) ?></h2>
<div class="search-preview"><p class="preview-url"><?= e(absolute_url()) ?></p><h3><?= e($s['home_seo_title'] ?: 'Møbelmontering og handyman i ' . $s['service_region'] . ' | ' . $s['company_name']) ?></h3><p><?= e(seo_text($s['home_seo_description'] ?: 'Møbelmontering, garderober, kjøkken og veggmontering i ' . $s['service_region'] . '. Praktisk hjelp for hjem og bedrifter. Be om et uforpliktende tilbud.')) ?></p></div>
<h2><?= e(admin_t('Tjenestesider')) ?></h2>
<?php if (!$available): admin_error(admin_t('Kunne ikke laste tjenestene.')); else: ?>
<div class="table-scroll"><table><thead><tr><th><?= e(admin_t('Tjeneste')) ?></th><th><?= e(admin_t('Tittel')) ?></th><th><?= e(admin_t('Beskrivelse')) ?></th><th><?= e(admin_t('Bilde')) ?></th></tr></thead><tbody>
<?php foreach ($services as $service): $meta = service_metadata($service); [$image] = service_media($service); ?>
<tr><td><a href="<?= e(url('admin/services/?id=' . $service['id'])) ?>"><?= e($service['title']) ?></a></td><td><?= e($meta['title']) ?><small class="field-meta"><?= mb_strlen($meta['title']) ?> <?= e(admin_t('tegn')) ?></small></td><td><?= e($meta['description']) ?><small class="field-meta"><?= mb_strlen($meta['description']) ?> <?= e(admin_t('tegn')) ?></small></td><td><?= $image !== '' ? admin_t('Tilgjengelig') : admin_t('Mangler') ?></td></tr>
<?php endforeach ?></tbody></table></div>
<?php endif; admin_footer(); ?>

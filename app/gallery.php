<?php
declare(strict_types=1);
require_once __DIR__ . '/content.php';

function gallery_section(array $items): void
{
    $items = array_values(array_filter($items, static fn($item) => image_dimensions(preferred_image_path($item['image'])) !== false));
    ?>
<section class="section gallery-section" id="arbeid" aria-labelledby="gallery-title">
<div class="section-heading"><p class="eyebrow">Våre arbeider</p><h2 id="gallery-title">Montert med omtanke.<br>Tilpasset hverdagen.</h2><p>Fra kompakte garderober til romslig oppbevaring. Se detaljer fra monteringsjobber i ulike hjem.</p><a class="service-link" href="<?= e(url('#kontakt')) ?>">Fortell oss om prosjektet ditt →</a></div>
<?php if (!$items): ?><p>Flere bilder av utført arbeid kommer snart.</p><?php else: ?>
<div class="work-gallery" data-gallery>
<div class="gallery-controls" hidden><button class="gallery-icon" type="button" data-gallery-prev aria-label="Forrige bilder" title="Forrige bilder" aria-controls="gallery-track"><img src="<?= e(url('assets/icons/chevron-left.svg')) ?>" alt="" width="22" height="22"></button><output data-gallery-position aria-live="polite" aria-atomic="true">1 / <?= count($items) ?></output><button class="gallery-icon" type="button" data-gallery-next aria-label="Neste bilder" title="Neste bilder" aria-controls="gallery-track"><img src="<?= e(url('assets/icons/chevron-right.svg')) ?>" alt="" width="22" height="22"></button></div>
<div class="gallery-track" id="gallery-track" tabindex="0" role="region" aria-label="Bilder av utført arbeid">
<?php foreach (array_chunk($items, 4) as $page => $group): ?>
<div class="gallery-page" data-gallery-page>
<?php foreach ($group as $offset => $item): $index = $page * 4 + $offset; $path = preferred_image_path($item['image']); ?>
<figure class="work-photo"><a href="<?= e(url($path)) ?>" data-gallery-open="<?= $index ?>" aria-label="<?= e('Vis bilde: ' . ($item['caption'] ?: $item['alt_text'])) ?>"><?= image_html($path, $item['alt_text'], '', true, '(max-width: 680px) 70vw, (max-width: 980px) 40vw, 320px') ?><span class="photo-expand" aria-hidden="true"><img src="<?= e(url('assets/icons/expand.svg')) ?>" alt="" width="20" height="20"></span></a><figcaption><?= e($item['caption'] ?: $item['alt_text']) ?></figcaption></figure>
<?php endforeach ?></div>
<?php endforeach ?>
</div>
<dialog class="gallery-dialog" aria-labelledby="gallery-dialog-caption"><div class="gallery-dialog-toolbar"><output data-dialog-position aria-live="polite"></output><button class="gallery-icon" data-gallery-close type="button" aria-label="Lukk bilde" title="Lukk bilde"><img src="<?= e(url('assets/icons/x.svg')) ?>" alt="" width="24" height="24"></button></div><div class="gallery-dialog-stage"><button class="gallery-icon" data-dialog-prev type="button" aria-label="Forrige bilde" title="Forrige bilde"><img src="<?= e(url('assets/icons/chevron-left.svg')) ?>" alt="" width="24" height="24"></button><img data-dialog-image alt=""><button class="gallery-icon" data-dialog-next type="button" aria-label="Neste bilde" title="Neste bilde"><img src="<?= e(url('assets/icons/chevron-right.svg')) ?>" alt="" width="24" height="24"></button></div><p id="gallery-dialog-caption"></p><p data-gallery-error role="status" hidden>Bildet kunne ikke lastes. Prøv igjen.</p></dialog>
</div>
<?php endif ?></section>
<?php
}

<?php
declare(strict_types=1);
require_once __DIR__ . '/bootstrap.php';

function public_reviews(bool &$available): array
{
    try {
        $rows = query("SELECT v.display_name,v.rating,v.body,v.published_at,r.service
            FROM reviews v JOIN contact_requests r ON r.id=v.request_id
            WHERE v.status='approved' AND v.verified_at IS NOT NULL
              AND v.published_at IS NOT NULL AND v.published_at<=UTC_TIMESTAMP()
              AND r.status='completed'
            ORDER BY v.published_at DESC,v.id DESC LIMIT 6")->fetchAll();
        $available = true;
        return $rows;
    } catch (Throwable $error) {
        safe_log('public reviews unavailable', $error);
        $available = false;
        return [];
    }
}

function reviews_section(array $reviews, bool $available): void
{
    ?><section class="section reviews-section" id="omtaler" aria-labelledby="reviews-title">
<div class="reviews-heading"><div><p class="eyebrow">Fra kundene</p><h2 id="reviews-title">Kundeomtaler</h2></div>
<a class="service-link" href="<?= e(url('#arbeid')) ?>">Se utført arbeid →</a></div>
<?php if (!$reviews): ?>
<div class="reviews-empty"><p><?= $available ? 'Ingen kundeomtaler publisert ennå.' : 'Kundeomtaler er midlertidig utilgjengelige.' ?></p>
<a class="button button-primary" href="<?= e(url('#kontakt')) ?>"><?= e(settings()['primary_cta']) ?></a></div>
<?php if ($available): ?>
<figure class="review-demo" aria-label="Demonstrasjon, ikke en ekte kundeomtale">
<?= image_html('assets/images/hvitt-garderoberom.webp', 'Montert hvitt garderoberom fra vårt arbeidsfotogalleri', '', true, '(max-width: 680px) 100vw, 400px') ?>
<div class="review-demo-copy"><h3>Demonstrasjon – ikke en ekte kundeomtale</h3>
<blockquote><p>«Ryddig montering og god kommunikasjon. Garderoben ble tilpasset rommet, og arbeidsområdet ble ryddet etterpå.»</p></blockquote>
<figcaption><strong>Ole Hansen (fiktivt navn)</strong><span>Eksempel: garderobemontering</span><small>Illustrasjonsfoto av utført arbeid, ikke et bilde av kunden. Teksten er et demonstrasjonseksempel.</small></figcaption></div>
</figure>
<?php endif ?>
<?php else: ?><div class="reviews-grid">
<?php foreach ($reviews as $review):
    $rating = (int)$review['rating'];
    $date = new DateTimeImmutable($review['published_at'], new DateTimeZone('UTC'));
?>
<figure class="review-card">
<div class="review-meta"><span class="review-rating" role="img" aria-label="<?= $rating ?> av 5 stjerner"><?php for ($star=1; $star<=5; $star++): ?><img src="<?= e(url('assets/icons/star.svg')) ?>" width="18" height="18" alt="" aria-hidden="true"<?= $star>$rating ? ' class="is-muted"' : '' ?>><?php endfor ?></span>
<time datetime="<?= e($date->format('Y-m-d')) ?>"><?= e($date->format('d.m.Y')) ?></time></div>
<blockquote><?php if (mb_strlen($review['body'],'UTF-8')>320): ?>
<details class="review-text"><summary><span class="review-excerpt"><?= e(mb_substr($review['body'],0,240,'UTF-8')) ?>…</span><span class="review-read-more">Les hele omtalen</span><span class="review-read-less">Vis mindre</span></summary><p><?= e($review['body']) ?></p></details>
<?php else: ?><p><?= e($review['body']) ?></p><?php endif ?></blockquote>
<figcaption><strong><?= e($review['display_name']) ?></strong><span><?= e($review['service']) ?></span><small>Bekreftet kunde</small></figcaption>
</figure>
<?php endforeach ?></div><?php endif ?>
</section><?php
}

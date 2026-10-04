<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/app/admin-layout.php';
$admin = require_admin();
$error = '';
try {
    $counts = query("SELECT COUNT(*) AS total, COALESCE(SUM(status = 'new'),0) AS new_count FROM contact_requests WHERE archived = 0")->fetch();
    $stats = [
        ['label' => admin_t('Nye forespørsler'), 'count' => (int)$counts['new_count'], 'path' => 'requests/?status=new'],
        ['label' => admin_t('Forespørsler'), 'count' => (int)$counts['total'], 'path' => 'requests/'],
        ['label' => admin_t('Omtaler til gjennomgang'), 'count' => (int)query("SELECT COUNT(*) FROM reviews WHERE status='pending'")->fetchColumn(), 'path' => 'reviews/'],
        ['label' => admin_t('Tjenester'), 'count' => (int)query('SELECT COUNT(*) FROM services')->fetchColumn(), 'path' => 'services/'],
        ['label' => admin_t('Bilder'), 'count' => (int)query('SELECT COUNT(*) FROM gallery_items')->fetchColumn(), 'path' => 'gallery/'],
    ];
    $requests = query("SELECT id,name,service,status,created_at,email_sent FROM contact_requests WHERE archived = 0 ORDER BY created_at DESC,id DESC LIMIT 10")->fetchAll();
} catch (Throwable $ex) { safe_log('dashboard unavailable', $ex); $error = admin_t('Kunne ikke laste oversikten.'); }
admin_header(admin_t('Oversikt'));
if ($error) admin_error($error);
else {
?>
<p class="account-summary"><?= e($admin['email']) ?></p>
<div class="stats"><?php foreach ($stats as $stat): ?><a href="<?= e(url('admin/' . $stat['path'])) ?>"><span><?= e($stat['label']) ?></span><strong><?= $stat['count'] ?></strong></a><?php endforeach ?></div>
<nav class="quick-actions" aria-label="<?= e(admin_t('Snarveier')) ?>"><a href="<?= e(url('admin/requests/')) ?>"><?= e(admin_t('Åpne forespørsler')) ?></a><a href="<?= e(url('admin/services/?new=1')) ?>"><?= e(admin_t('Ny tjeneste')) ?></a><a href="<?= e(url('admin/gallery/?new=1')) ?>"><?= e(admin_t('Legg til bilde')) ?></a><a href="<?= e(url('admin/settings/')) ?>"><?= e(admin_t('Kontakt og innstillinger')) ?></a></nav>
<?php if (!config()['recipient_email'] || !config()['from_email']): ?><p class="notice error"><?= e(admin_t('E-postlevering er ikke konfigurert. Følg opp forespørslene her.')) ?> <a href="<?= e(url('admin/settings/')) ?>"><?= e(admin_t('Leveringsstatus')) ?></a></p><?php endif ?>
<h2><?= e(admin_t('Siste forespørsler')) ?></h2>
<div class="table-scroll"><table><thead><tr><th><?= e(admin_t('Navn')) ?></th><th><?= e(admin_t('Tjeneste')) ?></th><th><?= e(admin_t('Status')) ?></th><th><?= e(admin_t('E-post')) ?></th><th><?= e(admin_t('Dato')) ?></th></tr></thead><tbody>
<?php $statuses = ['new' => admin_t('Ny'), 'in_progress' => admin_t('Under arbeid'), 'completed' => admin_t('Fullført'), 'spam' => admin_t('Spam')]; foreach ($requests as $r): ?><tr><td><a href="<?= e(url('admin/requests/?id=' . $r['id'])) ?>"><?= e($r['name']) ?></a></td><td><?= e($r['service']) ?></td><td><?= e($statuses[$r['status']] ?? $r['status']) ?></td><td><?= $r['email_sent'] ? admin_t('Sendt') : admin_t('Ikke levert') ?></td><td><?= e($r['created_at']) ?></td></tr><?php endforeach ?>
</tbody></table></div>
<?php if (!$requests): ?><p><?= e(admin_t('Ingen forespørsler ennå.')) ?></p><?php endif ?>
<?php } admin_footer(); ?>

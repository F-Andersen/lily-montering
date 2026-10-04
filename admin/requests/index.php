<?php
declare(strict_types=1);
require_once dirname(__DIR__, 2) . '/app/crud.php';
require_once dirname(__DIR__, 2) . '/app/mail.php';
require_once dirname(__DIR__, 2) . '/app/request-photos.php';
require_once dirname(__DIR__, 2) . '/app/admin-reviews.php';
require_admin();
$statuses = ['new' => 'Ny', 'in_progress' => 'Under arbeid', 'completed' => 'Fullført', 'spam' => 'Spam'];
$id = max(0, (int)($_GET['id'] ?? $_POST['id'] ?? 0));
$error = '';
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    require_post_csrf();
    try {
        if (!$id || !query('SELECT id FROM contact_requests WHERE id = ?', [$id])->fetch()) throw new InvalidArgumentException('Forespørselen finnes ikke.');
        if(in_array($_POST['action']??'', ['create_review_invitation','send_review_invitation','revoke_review_invitation'],true)) review_invitation_action($_POST['action'],$id);
        if (($_POST['action'] ?? '') === 'send_email') {
            if (!rate_limit('admin-mail', (string)$_SESSION['admin_id'], 10, 600)) throw new InvalidArgumentException('For mange sendeforsøk. Prøv igjen senere.');
            if (!deliver_request($id)) throw new RuntimeException('Mail delivery failed');
            flash('Forespørselen er akseptert av e-postserveren.');
            redirect('admin/requests/?id=' . $id);
        } elseif (($_POST['action'] ?? '') === 'delete') {
            delete_request_with_photos($id);
            flash('Forespørselen er slettet.');
        } else {
            $status = post_text('status', 20, true);
            if (!isset($statuses[$status])) throw new InvalidArgumentException('Ugyldig status.');
            query('UPDATE contact_requests SET status = ?, archived = ? WHERE id = ?', [$status, isset($_POST['archived']) ? 1 : 0, $id]);
            flash('Forespørselen er oppdatert.');
        }
        redirect('admin/requests/');
    } catch (InvalidArgumentException $ex) { $error = $ex->getMessage(); }
    catch (Throwable $ex) { safe_log('request update unavailable', $ex); $error = match($_POST['action'] ?? '') {
        'send_email' => 'E-post kunne ikke sendes. Forespørselen er fortsatt lagret. Kontroller SMTP i innstillingene.',
        'send_review_invitation' => 'Invitasjonen kunne ikke sendes på e-post. Lenken er fortsatt gyldig. Kontroller SMTP i innstillingene.',
        default => 'Kunne ikke oppdatere forespørselen.',
    }; }
}
admin_header('Forespørsler');
if ($error) admin_error($error);
try {
    if ($id) {
        $r = query('SELECT * FROM contact_requests WHERE id = ?', [$id])->fetch();
        if (!$r) { http_response_code(404); admin_error('Forespørselen finnes ikke.'); }
        else {
            ?><a href="<?= e(url('admin/requests/')) ?>">← Til oversikten</a><h2><?= e($r['name']) ?></h2><dl class="request-detail">
<?php foreach (['phone' => 'Telefon', 'email' => 'E-post', 'service' => 'Tjeneste', 'area' => 'Område', 'created_at' => 'Dato'] as $k => $label): ?><dt><?= $label ?></dt><dd><?php if ($k === 'phone' && preg_match('/^\+[0-9]{7,15}$/D', $r[$k])): ?><a href="tel:<?= e($r[$k]) ?>"><?= e($r[$k]) ?></a><?php elseif ($k === 'email' && filter_var($r[$k], FILTER_VALIDATE_EMAIL)): ?><a href="mailto:<?= e($r[$k]) ?>"><?= e($r[$k]) ?></a><?php else: ?><?= e($r[$k]) ?><?php endif ?></dd><?php endforeach ?><dt>E-postlevering</dt><dd><?= $r['email_sent'] ? 'Akseptert av e-postserveren' : 'Ikke sendt; forespørselen er lagret her.' ?></dd></dl>
<?php if (!$r['email_sent'] && $r['status'] !== 'spam'): ?><form method="post"><?= csrf_input() ?><input type="hidden" name="id" value="<?= $id ?>"><input type="hidden" name="action" value="send_email"><p><button type="submit">Send e-post til administrator</button> <a href="<?= e(url('admin/settings/#delivery-heading')) ?>">E-postinnstillinger</a></p></form><?php endif ?>
<h2>Beskrivelse</h2><p class="message"><?= e($r['message']) ?></p>
<?php $photos = query('SELECT id,width,height,bytes FROM request_photos WHERE request_id = ? ORDER BY id', [$id])->fetchAll(); ?>
<h2>Bilder <span class="field-meta"><?= count($photos) ?> vedlegg</span></h2>
<?php if (!$photos): ?><p>Ingen bilder vedlagt.</p><?php else: ?><div class="request-photos">
<?php foreach ($photos as $number => $photo): $photoUrl = url('admin/requests/photo.php?id=' . $photo['id']); ?>
<figure><a href="<?= e($photoUrl) ?>" target="_blank" rel="noopener noreferrer" aria-label="Åpne bilde <?= $number + 1 ?>"><img src="<?= e($photoUrl . '&size=thumb') ?>" alt="Vedlagt bilde <?= $number + 1 ?>" width="<?= $photo['width'] ?>" height="<?= $photo['height'] ?>" loading="lazy"></a><figcaption>Bilde <?= $number + 1 ?> · <?= (int)ceil($photo['bytes'] / 1024) ?> KB <a href="<?= e($photoUrl . '&download=1') ?>">Last ned</a></figcaption></figure>
<?php endforeach ?></div><?php endif ?>
<?php review_invitation_panel($r); ?>
<form class="edit-form" method="post"><?= csrf_input() ?><input type="hidden" name="id" value="<?= $id ?>">
<label class="field" for="status"><span>Status</span><select id="status" name="status"><?php foreach ($statuses as $s => $label): ?><option value="<?= $s ?>" <?= $r['status'] === $s ? 'selected' : '' ?>><?= $label ?></option><?php endforeach ?></select></label>
<label class="check"><input type="checkbox" name="archived" <?= $r['archived'] ? 'checked' : '' ?>>Arkivert</label><button type="submit">Lagre status</button></form>
<form method="post" data-confirm="Slette forespørselen permanent?"><?= csrf_input() ?><input type="hidden" name="id" value="<?= $id ?>"><input type="hidden" name="action" value="delete"><p><button class="danger" type="submit">Slett forespørsel</button></p></form>
<?php
        }
    } else {
        $search = trim(is_string($_GET['q'] ?? null) ? mb_substr($_GET['q'], 0, 100) : '');
        $status = is_string($_GET['status'] ?? null) ? $_GET['status'] : '';
        $archived = ($_GET['archived'] ?? '') === '1' ? 1 : 0;
        $where = 'archived = ?';
        $params = [$archived];
        if (isset($statuses[$status])) { $where .= ' AND status = ?'; $params[] = $status; }
        if ($search !== '') {
            $where .= ' AND (name LIKE ? OR email LIKE ? OR service LIKE ? OR message LIKE ?)';
            $literal = '%' . strtr($search, ['!' => '!!', '%' => '!%', '_' => '!_']) . '%';
            $where = str_replace('LIKE ?', "LIKE ? ESCAPE '!'", $where);
            array_push($params, $literal, $literal, $literal, $literal);
        }
        $total = (int)query('SELECT COUNT(*) FROM contact_requests WHERE ' . $where, $params)->fetchColumn();
        [$page, $pages, $offset] = admin_page($total);
        $rows = query('SELECT id,name,service,status,created_at,email_sent,(SELECT COUNT(*) FROM request_photos WHERE request_id = contact_requests.id) AS photo_count FROM contact_requests WHERE ' . $where . ' ORDER BY created_at DESC,id DESC LIMIT 30 OFFSET ' . $offset, $params)->fetchAll();
        ?><form class="toolbar" method="get"><label class="field" for="q"><span>Søk</span><input id="q" name="q" value="<?= e($search) ?>" maxlength="100"></label><label class="field" for="filter-status"><span>Status</span><select id="filter-status" name="status"><option value="">Alle</option><?php foreach ($statuses as $s => $label): ?><option value="<?= $s ?>" <?= $status === $s ? 'selected' : '' ?>><?= $label ?></option><?php endforeach ?></select></label><label class="check"><input type="checkbox" name="archived" value="1" <?= $archived ? 'checked' : '' ?>>Arkiv</label><button type="submit">Søk</button></form>
<p class="list-summary"><?= $total ?> forespørsler</p><div class="table-scroll"><table><thead><tr><th>Navn</th><th>Tjeneste</th><th>Status</th><th>Bilder</th><th>E-post</th><th>Dato</th></tr></thead><tbody><?php foreach ($rows as $r): ?><tr><td><a href="?id=<?= $r['id'] ?>"><?= e($r['name']) ?></a></td><td><?= e($r['service']) ?></td><td><span class="status-badge status-<?= e($r['status']) ?>"><?= e($statuses[$r['status']]) ?></span></td><td><?= (int)$r['photo_count'] ?></td><td><?= $r['email_sent'] ? 'Sendt' : 'Ikke levert' ?></td><td><?= e($r['created_at']) ?></td></tr><?php endforeach ?></tbody></table></div>
<?php admin_pagination($page, $pages, ['q' => $search, 'status' => $status, 'archived' => $archived]); ?>
<?php if (!$rows): ?><p>Ingen forespørsler funnet.</p><?php endif ?>
<?php
    }
} catch (Throwable $ex) { safe_log('requests unavailable', $ex); admin_error('Kunne ikke laste forespørslene.'); }
admin_footer();

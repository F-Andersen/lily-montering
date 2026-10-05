<?php
declare(strict_types=1);
require_once dirname(__DIR__,2).'/app/crud.php';
require_once dirname(__DIR__,2).'/app/review-workflow.php';
$admin=require_admin();
$statuses=['pending'=>admin_t('Venter på gjennomgang'),'approved'=>admin_t('Publisert'),'rejected'=>admin_t('Avvist')];
$id=max(0,(int)($_GET['id']??$_POST['id']??0)); $error='';
if(($_SERVER['REQUEST_METHOD']??'')==='POST') {
    require_post_csrf();
    try {
        moderate_review($id,post_text('status',20,true),(int)$admin['id'],post_text('moderation_note',500));
        flash(admin_t('Omtalen er oppdatert.')); redirect('admin/reviews/?id='.$id);
    } catch(InvalidArgumentException $ex) { $error=$ex->getMessage(); }
    catch(Throwable $ex) { safe_log('review moderation unavailable',$ex); $error=admin_t('Kunne ikke oppdatere omtalen.'); }
}
admin_header(admin_t('Omtaler'));
if($error) admin_error($error);
$invitations = !$id && ($_GET['view'] ?? '') === 'invitations';
if (!$id): ?>
<nav class="review-tabs" aria-label="<?= e(admin_t('Omtaler')) ?>"><a href="<?= e(url('admin/reviews/')) ?>" <?= !$invitations ? 'aria-current="page"' : '' ?>><?= e(admin_t('Omtaler')) ?></a><a href="<?= e(url('admin/reviews/?view=invitations')) ?>" <?= $invitations ? 'aria-current="page"' : '' ?>><?= e(admin_t('Inviter kunder')) ?></a></nav>
<?php endif;
try {
    if ($invitations) {
        $where = "r.archived=0 AND r.status<>'spam' AND NOT EXISTS (SELECT 1 FROM reviews v WHERE v.request_id=r.id)";
        $total = (int)query('SELECT COUNT(*) FROM contact_requests r WHERE ' . $where)->fetchColumn();
        [$page, $pages, $offset] = admin_page($total);
        $requests = query("SELECT r.id,r.name,r.service,r.status,i.id AS invitation_id FROM contact_requests r LEFT JOIN review_invitations i ON i.request_id=r.id WHERE " . $where . " ORDER BY (r.status='completed') DESC,r.created_at DESC,r.id DESC LIMIT 30 OFFSET " . $offset)->fetchAll();
        $requestStatuses = ['new' => admin_t('Ny'), 'in_progress' => admin_t('Under arbeid'), 'completed' => admin_t('Fullført')];
        ?><h2><?= e(admin_t('Lenke til kundeomtale')) ?></h2><p class="list-summary"><?= $total ?> <?= e(admin_t('forespørsler')) ?></p>
<div class="table-scroll"><table><thead><tr><th><?= e(admin_t('Navn')) ?></th><th><?= e(admin_t('Tjeneste')) ?></th><th><?= e(admin_t('Status')) ?></th><th><?= e(admin_t('Handlinger')) ?></th></tr></thead><tbody>
<?php foreach ($requests as $request): $requestUrl = url('admin/requests/?id=' . (int)$request['id'] . '#invitation-heading'); ?>
<tr><td><a href="<?= e($requestUrl) ?>"><?= e($request['name']) ?></a></td><td><?= e($request['service']) ?></td><td><span class="status-badge status-<?= e($request['status']) ?>"><?= e($requestStatuses[$request['status']]) ?></span></td><td>
<?php if ($request['status'] !== 'completed'): ?><a href="<?= e($requestUrl) ?>"><?= e(admin_t('Fullfør oppdraget')) ?></a>
<?php elseif ($request['invitation_id']): ?><a class="action secondary" href="<?= e($requestUrl) ?>"><?= e(admin_t('Åpne invitasjon')) ?></a>
<?php else: ?><form method="post" action="<?= e($requestUrl) ?>"><?= csrf_input() ?><input type="hidden" name="id" value="<?= (int)$request['id'] ?>"><button type="submit" name="action" value="create_review_invitation"><?= e(admin_t('Opprett lenke')) ?></button></form><?php endif ?>
</td></tr><?php endforeach ?></tbody></table></div>
<?php if (!$requests): ?><p><?= e(admin_t('Ingen oppdrag venter på en omtale.')) ?></p><a href="<?= e(url('admin/requests/')) ?>"><?= e(admin_t('Forespørsler')) ?></a><?php endif;
        admin_pagination($page, $pages, ['view' => 'invitations']);
    } elseif($id) {
        $r=query('SELECT v.*,r.service,r.status AS request_status FROM reviews v JOIN contact_requests r ON r.id=v.request_id WHERE v.id=?',[$id])->fetch();
        if(!$r) { http_response_code(404); admin_error(admin_t('Omtalen finnes ikke.')); }
        else {
            ?><a href="<?= e(url('admin/reviews/')) ?>"><?= e(admin_t('← Til oversikten')) ?></a><h2><?= e($r['display_name']) ?></h2>
<dl class="request-detail"><dt><?= e(admin_t('Vurdering')) ?></dt><dd><?= (int)$r['rating'] ?> <?= e(admin_t('av 5')) ?></dd><dt><?= e(admin_t('Tjeneste')) ?></dt><dd><?= e($r['service']) ?></dd><dt><?= e(admin_t('Forespørsel')) ?></dt><dd><a href="<?= e(url('admin/requests/?id='.$r['request_id'])) ?>">#<?= (int)$r['request_id'] ?></a></dd><dt><?= e(admin_t('Mottatt (UTC)')) ?></dt><dd><?= e($r['created_at']) ?></dd><dt><?= e(admin_t('Bekreftet')) ?></dt><dd><?= $r['verified_at']?admin_t('Ja'):admin_t('Nei') ?></dd><dt><?= e(admin_t('Status')) ?></dt><dd><?= e($statuses[$r['status']]) ?></dd><?php if($r['moderated_at']): ?><dt><?= e(admin_t('Sist gjennomgått (UTC)')) ?></dt><dd><?= e($r['moderated_at']) ?> · <?= e(admin_t('Administrator')) ?> #<?= (int)$r['moderated_by'] ?></dd><?php endif ?></dl>
<blockquote class="message review-moderation-body"><?= e($r['body']) ?></blockquote>
<form class="edit-form" method="post"><?= csrf_input() ?><input type="hidden" name="id" value="<?= $id ?>"><label class="field" for="review-status"><span><?= e(admin_t('Status')) ?></span><select id="review-status" name="status"><?php foreach($statuses as $key=>$label): ?><option value="<?= e($key) ?>" <?= $r['status']===$key?'selected':'' ?> <?= $key==='approved' && (!$r['verified_at']||$r['request_status']!=='completed')?'disabled':'' ?>><?= e($label) ?></option><?php endforeach ?></select></label><label class="field" for="moderation-note"><span><?= e(admin_t('Internt notat')) ?></span><textarea id="moderation-note" name="moderation_note" maxlength="500" rows="3"><?= e($r['moderation_note']) ?></textarea></label><button type="submit"><?= e(admin_t('Lagre status')) ?></button></form>
<?php
        }
    } else {
        $status=is_string($_GET['status']??null)?$_GET['status']:'pending';
        $search=trim(is_string($_GET['q']??null)?mb_substr($_GET['q'],0,100,'UTF-8'):'');
        $where='1=1';$params=[];
        if(isset($statuses[$status])) {$where.=' AND v.status=?';$params[]=$status;}
        if($search!=='') {$where.=" AND (v.display_name LIKE ? ESCAPE '!' OR v.body LIKE ? ESCAPE '!')"; $literal='%'.strtr($search,['!'=>'!!','%'=>'!%','_'=>'!_']).'%';array_push($params,$literal,$literal);}
        $total=(int)query('SELECT COUNT(*) FROM reviews v WHERE '.$where,$params)->fetchColumn();
        [$page,$pages,$offset]=admin_page($total);
        $rows=query('SELECT v.id,v.display_name,v.rating,v.status,v.created_at,r.service FROM reviews v JOIN contact_requests r ON r.id=v.request_id WHERE '.$where.' ORDER BY v.created_at DESC,v.id DESC LIMIT 30 OFFSET '.$offset,$params)->fetchAll();
        ?><form class="toolbar" method="get"><label class="field" for="q"><span><?= e(admin_t('Søk')) ?></span><input id="q" name="q" maxlength="100" value="<?= e($search) ?>"></label><label class="field" for="filter-status"><span><?= e(admin_t('Status')) ?></span><select id="filter-status" name="status"><option value="" <?= $status===''?'selected':'' ?>><?= e(admin_t('Alle')) ?></option><?php foreach($statuses as $key=>$label): ?><option value="<?= e($key) ?>" <?= $status===$key?'selected':'' ?>><?= e($label) ?></option><?php endforeach ?></select></label><button type="submit"><?= e(admin_t('Søk')) ?></button></form>
<p class="list-summary"><?= $total ?> <?= e(admin_t('omtaler')) ?></p><div class="table-scroll"><table><thead><tr><th><?= e(admin_t('Navn')) ?></th><th><?= e(admin_t('Vurdering')) ?></th><th><?= e(admin_t('Tjeneste')) ?></th><th><?= e(admin_t('Status')) ?></th><th><?= e(admin_t('Mottatt')) ?></th></tr></thead><tbody><?php foreach($rows as $row): ?><tr><td><a href="?id=<?= (int)$row['id'] ?>"><?= e($row['display_name']) ?></a></td><td><?= (int)$row['rating'] ?> <?= e(admin_t('av 5')) ?></td><td><?= e($row['service']) ?></td><td><span class="status-badge status-<?= e($row['status']) ?>"><?= e($statuses[$row['status']]) ?></span></td><td><?= e($row['created_at']) ?></td></tr><?php endforeach ?></tbody></table></div>
<?php admin_pagination($page,$pages,['q'=>$search,'status'=>$status]); if(!$rows): ?><p><?= e(admin_t('Ingen omtaler funnet.')) ?></p><?php endif;
    }
} catch(Throwable $ex) {safe_log('admin reviews unavailable',$ex);admin_error(admin_t('Kunne ikke laste omtalene.'));}
admin_footer();

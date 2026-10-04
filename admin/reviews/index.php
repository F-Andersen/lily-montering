<?php
declare(strict_types=1);
require_once dirname(__DIR__,2).'/app/crud.php';
require_once dirname(__DIR__,2).'/app/review-workflow.php';
$admin=require_admin();
$statuses=['pending'=>'Venter på gjennomgang','approved'=>'Publisert','rejected'=>'Avvist'];
$id=max(0,(int)($_GET['id']??$_POST['id']??0)); $error='';
if(($_SERVER['REQUEST_METHOD']??'')==='POST') {
    require_post_csrf();
    try {
        moderate_review($id,post_text('status',20,true),(int)$admin['id'],post_text('moderation_note',500));
        flash('Omtalen er oppdatert.'); redirect('admin/reviews/?id='.$id);
    } catch(InvalidArgumentException $ex) { $error=$ex->getMessage(); }
    catch(Throwable $ex) { safe_log('review moderation unavailable',$ex); $error='Kunne ikke oppdatere omtalen.'; }
}
admin_header('Omtaler');
if($error) admin_error($error);
try {
    if($id) {
        $r=query('SELECT v.*,r.service,r.status AS request_status FROM reviews v JOIN contact_requests r ON r.id=v.request_id WHERE v.id=?',[$id])->fetch();
        if(!$r) { http_response_code(404); admin_error('Omtalen finnes ikke.'); }
        else {
            ?><a href="<?= e(url('admin/reviews/')) ?>">← Til oversikten</a><h2><?= e($r['display_name']) ?></h2>
<dl class="request-detail"><dt>Vurdering</dt><dd><?= (int)$r['rating'] ?> av 5</dd><dt>Tjeneste</dt><dd><?= e($r['service']) ?></dd><dt>Forespørsel</dt><dd><a href="<?= e(url('admin/requests/?id='.$r['request_id'])) ?>">#<?= (int)$r['request_id'] ?></a></dd><dt>Mottatt (UTC)</dt><dd><?= e($r['created_at']) ?></dd><dt>Bekreftet</dt><dd><?= $r['verified_at']?'Ja':'Nei' ?></dd><dt>Status</dt><dd><?= e($statuses[$r['status']]) ?></dd><?php if($r['moderated_at']): ?><dt>Sist gjennomgått (UTC)</dt><dd><?= e($r['moderated_at']) ?> · Administrator #<?= (int)$r['moderated_by'] ?></dd><?php endif ?></dl>
<blockquote class="message review-moderation-body"><?= e($r['body']) ?></blockquote>
<form class="edit-form" method="post"><?= csrf_input() ?><input type="hidden" name="id" value="<?= $id ?>"><label class="field" for="review-status"><span>Status</span><select id="review-status" name="status"><?php foreach($statuses as $key=>$label): ?><option value="<?= e($key) ?>" <?= $r['status']===$key?'selected':'' ?> <?= $key==='approved' && (!$r['verified_at']||$r['request_status']!=='completed')?'disabled':'' ?>><?= e($label) ?></option><?php endforeach ?></select></label><label class="field" for="moderation-note"><span>Internt notat</span><textarea id="moderation-note" name="moderation_note" maxlength="500" rows="3"><?= e($r['moderation_note']) ?></textarea></label><button type="submit">Lagre status</button></form>
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
        ?><form class="toolbar" method="get"><label class="field" for="q"><span>Søk</span><input id="q" name="q" maxlength="100" value="<?= e($search) ?>"></label><label class="field" for="filter-status"><span>Status</span><select id="filter-status" name="status"><option value="" <?= $status===''?'selected':'' ?>>Alle</option><?php foreach($statuses as $key=>$label): ?><option value="<?= e($key) ?>" <?= $status===$key?'selected':'' ?>><?= e($label) ?></option><?php endforeach ?></select></label><button type="submit">Søk</button></form>
<p class="list-summary"><?= $total ?> omtaler</p><div class="table-scroll"><table><thead><tr><th>Navn</th><th>Vurdering</th><th>Tjeneste</th><th>Status</th><th>Mottatt</th></tr></thead><tbody><?php foreach($rows as $row): ?><tr><td><a href="?id=<?= (int)$row['id'] ?>"><?= e($row['display_name']) ?></a></td><td><?= (int)$row['rating'] ?> av 5</td><td><?= e($row['service']) ?></td><td><span class="status-badge status-<?= e($row['status']) ?>"><?= e($statuses[$row['status']]) ?></span></td><td><?= e($row['created_at']) ?></td></tr><?php endforeach ?></tbody></table></div>
<?php admin_pagination($page,$pages,['q'=>$search,'status'=>$status]); if(!$rows): ?><p>Ingen omtaler funnet.</p><?php endif;
    }
} catch(Throwable $ex) {safe_log('admin reviews unavailable',$ex);admin_error('Kunne ikke laste omtalene.');}
admin_footer();

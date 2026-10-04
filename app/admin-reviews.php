<?php
declare(strict_types=1);
require_once __DIR__.'/crud.php';
require_once __DIR__.'/review-workflow.php';

function review_invitation_action(string $action, int $requestId): void
{
    if(!rate_limit('admin-review-actions',(string)$_SESSION['admin_id'],30,3600)) throw new InvalidArgumentException('For mange forsøk. Prøv igjen senere.');
    if($action==='create_review_invitation') {
        $_SESSION['review_link']=create_review_invitation($requestId,(int)$_SESSION['admin_id'])+['session_expires'=>time()+1800];
        flash('Invitasjonen er opprettet.');
    } elseif($action==='send_review_invitation') {
        $link=$_SESSION['review_link']??null;
        if(!$link || (int)$link['request_id']!==$requestId || $link['session_expires']<=time()) throw new InvalidArgumentException('Opprett en ny invitasjon før sending.');
        send_review_invitation($requestId,$link['token']);
        flash('Invitasjonen er akseptert av e-postserveren.');
    } elseif($action==='revoke_review_invitation') {
        revoke_review_invitation($requestId);
        unset($_SESSION['review_link']);
        flash('Invitasjonen er trukket tilbake.');
    } else throw new InvalidArgumentException('Ugyldig handling.');
    redirect('admin/requests/?id='.$requestId);
}

function review_invitation_panel(array $request): void
{
    $id=(int)$request['id'];
    $review=query('SELECT id,status FROM reviews WHERE request_id=?',[$id])->fetch();
    $invitation=query('SELECT *,expires_at>UTC_TIMESTAMP() AS current_invitation FROM review_invitations WHERE request_id=?',[$id])->fetch();
    $link=$_SESSION['review_link']??null;
    if($link && ($link['session_expires']??0)<=time()) { unset($_SESSION['review_link']); $link=null; }
    ?><section class="review-invitation" aria-labelledby="invitation-heading"><h2 id="invitation-heading">Kundeomtale</h2>
<?php if($review): ?><p><a href="<?= e(url('admin/reviews/?id='.$review['id'])) ?>">Åpne kundeomtalen →</a></p>
<?php elseif($request['status']!=='completed' || $request['archived']): ?><p>Invitasjon er tilgjengelig etter fullført oppdrag.</p>
<?php else: ?>
<?php if($invitation): ?><dl class="request-detail"><dt>Invitasjon</dt><dd><?= $invitation['revoked_at']?'Trukket tilbake':($invitation['current_invitation']?'Aktiv':'Utløpt') ?></dd><dt>Gyldig til (UTC)</dt><dd><?= e($invitation['expires_at']) ?></dd><dt>E-postlevering</dt><dd><?= $invitation['mail_sent_at']?'Akseptert av e-postserveren':'Ikke sendt' ?></dd></dl><?php endif ?>
<?php if($link && (int)$link['request_id']===$id && $invitation && hash_equals($invitation['token_hash'],hash('sha256',$link['token'])) && $invitation['current_invitation'] && !$invitation['revoked_at']): ?>
<div class="invitation-link"><label class="field" for="review-link"><span>Personlig lenke</span><input id="review-link" value="<?= e(review_invitation_url($link['token'])) ?>" readonly autocomplete="off"></label><button class="icon-button secondary" type="button" data-copy-target="review-link" aria-label="Kopier lenke" title="Kopier lenke"><img src="<?= e(url('assets/icons/copy.svg')) ?>" width="20" height="20" alt=""></button><span data-copy-status role="status"></span></div>
<?php if(!$invitation['mail_sent_at'] && filter_var($request['email'],FILTER_VALIDATE_EMAIL)): ?><form method="post"><?= csrf_input() ?><input type="hidden" name="id" value="<?= $id ?>"><button type="submit" name="action" value="send_review_invitation">Send invitasjon på e-post</button></form><?php endif ?>
<?php endif ?>
<div class="review-invitation-actions"><form method="post" <?= $invitation?'data-confirm="Den gamle lenken slutter å virke. Opprette en ny?"':'' ?>><?= csrf_input() ?><input type="hidden" name="id" value="<?= $id ?>"><button class="secondary" type="submit" name="action" value="create_review_invitation"><?= $invitation?'Opprett ny invitasjon':'Opprett invitasjon' ?></button></form>
<?php if($invitation && !$invitation['revoked_at'] && $invitation['current_invitation']): ?><form method="post"><?= csrf_input() ?><input type="hidden" name="id" value="<?= $id ?>"><button class="danger" type="submit" name="action" value="revoke_review_invitation">Trekk tilbake</button></form><?php endif ?></div>
<?php endif ?></section><?php
}

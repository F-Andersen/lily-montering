<?php
declare(strict_types=1);
require_once __DIR__.'/crud.php';
require_once __DIR__.'/review-workflow.php';

function review_invitation_action(string $action, int $requestId): void
{
    if(!rate_limit('admin-review-actions',(string)$_SESSION['admin_id'],30,3600)) throw new InvalidArgumentException(admin_t('For mange forsøk. Prøv igjen senere.'));
    if($action==='create_review_invitation') {
        $_SESSION['review_link']=create_review_invitation($requestId,(int)$_SESSION['admin_id'])+['session_expires'=>time()+1800];
        flash(admin_t('Invitasjonen er opprettet.'));
    } elseif($action==='send_review_invitation') {
        $link=$_SESSION['review_link']??null;
        if(!$link || (int)$link['request_id']!==$requestId || $link['session_expires']<=time()) throw new InvalidArgumentException(admin_t('Opprett en ny invitasjon før sending.'));
        send_review_invitation($requestId,$link['token']);
        flash(admin_t('Invitasjonen er akseptert av e-postserveren.'));
    } elseif($action==='revoke_review_invitation') {
        revoke_review_invitation($requestId);
        unset($_SESSION['review_link']);
        flash(admin_t('Invitasjonen er trukket tilbake.'));
    } else throw new InvalidArgumentException(admin_t('Ugyldig handling.'));
    redirect('admin/requests/?id='.$requestId.'#invitation-heading');
}

function review_invitation_panel(array $request): void
{
    $id=(int)$request['id'];
    $review=query('SELECT id,status FROM reviews WHERE request_id=?',[$id])->fetch();
    $invitation=query('SELECT *,expires_at>UTC_TIMESTAMP() AS current_invitation FROM review_invitations WHERE request_id=?',[$id])->fetch();
    $link=$_SESSION['review_link']??null;
    if($link && ($link['session_expires']??0)<=time()) { unset($_SESSION['review_link']); $link=null; }
    ?><section class="review-invitation" aria-labelledby="invitation-heading"><h2 id="invitation-heading"><?= e(admin_t('Kundeomtale')) ?></h2>
<?php if($review): ?><p><a href="<?= e(url('admin/reviews/?id='.$review['id'])) ?>"><?= e(admin_t('Åpne kundeomtalen →')) ?></a></p>
<?php elseif($request['status']!=='completed' || $request['archived']): ?><p><?= e(admin_t('Invitasjon er tilgjengelig etter fullført oppdrag.')) ?></p>
<?php else: ?>
<?php if($invitation): ?><dl class="request-detail"><dt><?= e(admin_t('Invitasjon')) ?></dt><dd><?= $invitation['revoked_at']?admin_t('Trukket tilbake'):($invitation['current_invitation']?admin_t('Aktiv'):admin_t('Utløpt')) ?></dd><dt><?= e(admin_t('Gyldig til (UTC)')) ?></dt><dd><?= e($invitation['expires_at']) ?></dd><dt><?= e(admin_t('E-postlevering')) ?></dt><dd><?= $invitation['mail_sent_at']?admin_t('Akseptert av e-postserveren'):admin_t('Ikke sendt') ?></dd></dl><?php endif ?>
<?php if($link && (int)$link['request_id']===$id && $invitation && hash_equals($invitation['token_hash'],hash('sha256',$link['token'])) && $invitation['current_invitation'] && !$invitation['revoked_at']): ?>
<div class="invitation-link"><label class="field" for="review-link"><span><?= e(admin_t('Personlig lenke')) ?></span><input id="review-link" value="<?= e(review_invitation_url($link['token'])) ?>" readonly autocomplete="off"></label><button class="icon-button secondary" type="button" data-copy-target="review-link" aria-label="<?= e(admin_t('Kopier lenke')) ?>" title="<?= e(admin_t('Kopier lenke')) ?>"><img src="<?= e(url('assets/icons/copy.svg')) ?>" width="20" height="20" alt=""></button><span data-copy-status role="status"></span></div>
<?php if(!$invitation['mail_sent_at'] && filter_var($request['email'],FILTER_VALIDATE_EMAIL)): ?><form method="post"><?= csrf_input() ?><input type="hidden" name="id" value="<?= $id ?>"><button type="submit" name="action" value="send_review_invitation"><?= e(admin_t('Send invitasjon på e-post')) ?></button></form><?php endif ?>
<?php endif ?>
<div class="review-invitation-actions"><form method="post" <?php if ($invitation): ?>data-confirm="<?= e(admin_t('Den gamle lenken slutter å virke. Opprette en ny?')) ?>"<?php endif ?>><?= csrf_input() ?><input type="hidden" name="id" value="<?= $id ?>"><button class="secondary" type="submit" name="action" value="create_review_invitation"><?= $invitation?admin_t('Opprett ny invitasjon'):admin_t('Opprett invitasjon') ?></button></form>
<?php if($invitation && !$invitation['revoked_at'] && $invitation['current_invitation']): ?><form method="post"><?= csrf_input() ?><input type="hidden" name="id" value="<?= $id ?>"><button class="danger" type="submit" name="action" value="revoke_review_invitation"><?= e(admin_t('Trekk tilbake')) ?></button></form><?php endif ?></div>
<?php endif ?></section><?php
}

<?php
declare(strict_types=1);
require_once __DIR__.'/app/public-layout.php';
require_once __DIR__.'/app/review-workflow.php';
start_session();
security_headers();
header('Referrer-Policy: no-referrer');
header('X-Robots-Tag: noindex, nofollow');
$method=$_SERVER['REQUEST_METHOD']??'GET';
if(!in_array($method,['GET','POST'],true)) { header('Allow: GET, POST'); http_response_code(405); exit; }
if((int)($_SERVER['CONTENT_LENGTH']??0)>32768 || $_FILES) { http_response_code(413); exit('Skjemaet er for stort.'); }
$error=''; $available=true; $invitation=false;
$values=['display_name'=>'','body'=>'','rating'=>'','consent'=>''];
try {
    if($method==='POST') {
        if(!csrf_valid()) { http_response_code(403); throw new InvalidArgumentException('Skjemaet er utløpt. Last inn siden på nytt.'); }
        if(!rate_limit('review-post',client_ip(),30,3600)) { http_response_code(429); throw new InvalidArgumentException('For mange forsøk. Prøv igjen senere.'); }
        if(($_POST['action']??'')==='redeem') {
            unset($_SESSION['review_grant'],$_SESSION['review_received']);
            $token=review_token($_POST['invitation']??null);
            $invitation=$token!==''?review_invitation_by_hash(hash('sha256',$token)):false;
            if(!review_invitation_usable($invitation)) {
                if($invitation && $invitation['used_at'] && $invitation['submitted']) {
                    $_SESSION['review_received']=true; redirect('omtale.php');
                }
                throw new InvalidArgumentException('Invitasjonen er utløpt, brukt eller trukket tilbake. Kontakt oss for en ny lenke.');
            }
            session_regenerate_id(true);
            $_SESSION['review_grant']=['hash'=>hash('sha256',$token),'expires'=>time()+1800];
            redirect('omtale.php');
        }
    }
    $grant=$_SESSION['review_grant']??null;
    if(is_array($grant) && ($grant['expires']??0)>time()) $invitation=review_invitation_by_hash($grant['hash']);
    if(!review_invitation_usable($invitation)) {
        unset($_SESSION['review_grant']);
        if($method==='POST' && ($_POST['action']??'')==='submit') throw new InvalidArgumentException('Invitasjonen er utløpt, brukt eller trukket tilbake. Åpne den opprinnelige lenken på nytt.');
        $invitation=false;
    }
    if($method==='POST' && ($_POST['action']??'')==='submit') {
        foreach($values as $key=>$_) $values[$key]=is_string($_POST[$key]??null)?$_POST[$key]:'';
        $data=review_fields($_POST);
        if(!rate_limit('review-invitation',$grant['hash'],10,3600)) { http_response_code(429); throw new InvalidArgumentException('For mange forsøk. Prøv igjen senere.'); }
        submit_review($grant['hash'],$data);
        unset($_SESSION['review_grant']);
        $_SESSION['review_received']=true;
        redirect('omtale.php');
    }
} catch(InvalidArgumentException $ex) { if(http_response_code()<400) http_response_code(422); $error=$ex->getMessage(); }
catch(Throwable $ex) { safe_log('review form unavailable',$ex); http_response_code(503); $available=false; $error='Omtalen kunne ikke lagres nå. Prøv igjen senere.'; }
?><!doctype html><html lang="nb"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><meta name="robots" content="noindex,nofollow"><title>Din omtale | fiksitt</title><link rel="icon" href="<?= e(url('assets/fiksitt-icon-v2-32.png')) ?>"><link rel="stylesheet" href="<?= e(url('styles.css?v=client-20261005-6')) ?>">
<script src="<?= e(url('script.js?v=6')) ?>" defer></script><script src="<?= e(url('review.js?v=3')) ?>" defer></script>
</head><body class="review-route"><a class="skip-link" href="#main">Hopp til innhold</a>
<?php public_header(); ?>
<?php if($available): ?><form id="review-fragment" hidden method="post" action="<?= e(url('omtale.php')) ?>"><?= csrf_input() ?><input type="hidden" name="action" value="redeem"><input type="hidden" name="invitation"></form><?php endif ?>
<main id="main" class="review-page"><p class="eyebrow">Din erfaring</p><h1>Hvordan var jobben?</h1>
<?php if($error): ?><p class="form-status is-error" role="alert"><?= e($error) ?></p><?php endif ?>
<?php if($available && !empty($_SESSION['review_received']) && !$invitation): ?>
<h2>Takk for omtalen.</h2><p>Omtalen er mottatt og venter på gjennomgang før publisering.</p><a class="service-link" href="<?= e(url('#omtaler')) ?>">Til nettsiden →</a>
<?php elseif($invitation): ?>
<form class="review-form" method="post" action="<?= e(url('omtale.php')) ?>"><?= csrf_input() ?><input type="hidden" name="action" value="submit">
<fieldset class="review-score"><legend>Din vurdering</legend><div class="review-score-options">
<?php for($rating=1;$rating<=5;$rating++): ?><label class="review-choice"><input type="radio" name="rating" value="<?= $rating ?>" required <?= $values['rating']===(string)$rating?'checked':'' ?>><span><img src="<?= e(url('assets/icons/star.svg')) ?>" width="24" height="24" alt=""><span><?= $rating ?><span class="sr-only"> av 5 stjerner</span></span></span></label><?php endfor ?>
</div></fieldset>
<label class="field" for="display-name"><span>Navn som vises offentlig</span><input id="display-name" name="display_name" maxlength="80" required autocomplete="nickname" value="<?= e($values['display_name']) ?>"></label>
<label class="field" for="review-body"><span>Din omtale</span><textarea id="review-body" name="body" minlength="10" maxlength="1500" rows="6" required><?= e($values['body']) ?></textarea></label>
<label class="check consent"><input type="checkbox" name="consent" value="1" required <?= $values['consent']==='1'?'checked':'' ?>><span>Jeg samtykker til at navnet, vurderingen og omtalen min publiseres på nettsiden. Kontaktopplysninger og bilder fra forespørselen publiseres ikke.</span></label>
<button class="button button-primary" type="submit">Send omtale</button>
</form>
<?php elseif($available): ?>
<form id="review-redeem" class="review-form" method="post" action="<?= e(url('omtale.php')) ?>"><?= csrf_input() ?><input type="hidden" name="action" value="redeem"><label class="field" for="invitation"><span>Invitasjonskode eller personlig lenke</span><input id="invitation" name="invitation" maxlength="512" required autocomplete="off" spellcheck="false"></label><button class="button button-primary" type="submit">Åpne omtaleskjema</button></form>
<?php endif ?>
</main><?php public_footer(); ?>

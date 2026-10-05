<?php
declare(strict_types=1);
require_once __DIR__.'/bootstrap.php';
require_once __DIR__.'/mail.php';

function review_token(mixed $value): string
{
    if (!is_string($value) || strlen($value)>512) return '';
    $value=trim($value);
    $prefix=rtrim(config()['app_url'],'/').'/omtale.php#token=';
    if (str_starts_with($value,$prefix)) $value=substr($value,strlen($prefix));
    return preg_match('/^[a-f0-9]{64}$/D',$value) ? $value : '';
}

function review_invitation_url(string $token): string
{
    if (review_token($token)==='') throw new InvalidArgumentException('Ugyldig invitasjon.');
    // Fragments are not sent in HTTP requests, referrers or access logs.
    return rtrim(config()['app_url'],'/').'/omtale.php#token='.$token;
}

function create_review_invitation(int $requestId, int $adminId): array
{
    db()->beginTransaction();
    try {
        $request=query('SELECT status,archived FROM contact_requests WHERE id=? FOR UPDATE',[$requestId])->fetch();
        if (!$request || $request['status']!=='completed' || $request['archived']) throw new InvalidArgumentException('Invitasjoner krever en fullført, ikke arkivert forespørsel.');
        if (query('SELECT id FROM reviews WHERE request_id=?',[$requestId])->fetch()) throw new InvalidArgumentException('Kunden har allerede sendt en omtale.');
        $token=bin2hex(random_bytes(32));
        $expires=gmdate('Y-m-d H:i:s',time()+30*86400);
        query('INSERT INTO review_invitations(request_id,token_hash,expires_at,created_by) VALUES (?,?,?,?)
            ON DUPLICATE KEY UPDATE token_hash=VALUES(token_hash),expires_at=VALUES(expires_at),used_at=NULL,revoked_at=NULL,mail_sent_at=NULL,created_by=VALUES(created_by),created_at=CURRENT_TIMESTAMP',[$requestId,hash('sha256',$token),$expires,$adminId]);
        db()->commit();
        return ['token'=>$token,'expires_at'=>$expires,'request_id'=>$requestId];
    } catch(Throwable $error) { if(db()->inTransaction()) db()->rollBack(); throw $error; }
}

function revoke_review_invitation(int $requestId): void
{
    db()->beginTransaction();
    try {
        query('SELECT id FROM contact_requests WHERE id=? FOR UPDATE',[$requestId]);
        query('UPDATE review_invitations SET revoked_at=UTC_TIMESTAMP() WHERE request_id=? AND used_at IS NULL',[$requestId]);
        db()->commit();
    } catch(Throwable $error) { if(db()->inTransaction()) db()->rollBack(); throw $error; }
}

function review_invitation_by_hash(string $hash, bool $lock = false): array|false
{
    return query("SELECT i.id,i.request_id,i.token_hash,i.used_at,i.expires_at,i.revoked_at,r.status AS request_status,
        (i.expires_at>UTC_TIMESTAMP()) AS current_invitation,
        (SELECT COUNT(*) FROM reviews v WHERE v.request_id=i.request_id) AS submitted
        FROM review_invitations i JOIN contact_requests r ON r.id=i.request_id WHERE i.token_hash=?".($lock?' FOR UPDATE':''),[$hash])->fetch();
}

function review_invitation_usable(array|false $invitation): bool
{
    return $invitation && $invitation['request_status']==='completed' && !$invitation['revoked_at']
        && $invitation['current_invitation'] && !$invitation['used_at'] && !$invitation['submitted'];
}

function review_fields(array $input): array
{
    $data=[];
    foreach(['display_name'=>80,'body'=>1500] as $key=>$limit) {
        $value=$input[$key]??null;
        if(!is_string($value)||preg_match('//u',$value)!==1||str_contains($value,"\0")) throw new InvalidArgumentException('Kontroller navn og omtale.');
        $value=trim($value);
        if($value===''||mb_strlen($value,'UTF-8')>$limit) throw new InvalidArgumentException('Navn kan ha inntil 80 tegn og omtalen inntil 1500 tegn.');
        $data[$key]=$value;
    }
    if(mb_strlen($data['body'],'UTF-8')<10) throw new InvalidArgumentException('Omtalen må ha minst 10 tegn.');
    if(!is_string($input['rating']??null)||!preg_match('/^[1-5]$/D',$input['rating'])) throw new InvalidArgumentException('Velg en vurdering fra 1 til 5.');
    if(($input['consent']??null)!=='1') throw new InvalidArgumentException('Bekreft samtykke til publisering.');
    $data['rating']=(int)$input['rating'];
    return $data;
}

function submit_review(string $hash, array $data): int
{
    // Both submission and admin invitation changes lock the enquiry first.
    db()->beginTransaction();
    try {
        $invitation=review_invitation_by_hash($hash);
        if(!$invitation) throw new InvalidArgumentException('Invitasjonen er ikke tilgjengelig.');
        $request=query('SELECT status FROM contact_requests WHERE id=? FOR UPDATE',[$invitation['request_id']])->fetch();
        $invitation=review_invitation_by_hash($hash,true);
        if(!$request || !review_invitation_usable($invitation)) throw new InvalidArgumentException('Invitasjonen er utløpt, brukt eller trukket tilbake.');
        $data=review_fields(['display_name'=>$data['display_name']??null,'body'=>$data['body']??null,'rating'=>(string)($data['rating']??''),'consent'=>'1']);
        query("INSERT INTO reviews(request_id,display_name,rating,body,status,verified_at) VALUES (?,?,?,?,'pending',UTC_TIMESTAMP())",[$invitation['request_id'],$data['display_name'],$data['rating'],$data['body']]);
        $id=(int)db()->lastInsertId();
        query('UPDATE review_invitations SET used_at=UTC_TIMESTAMP() WHERE id=?',[$invitation['id']]);
        db()->commit();
        return $id;
    } catch(Throwable $error) { if(db()->inTransaction()) db()->rollBack(); throw $error; }
}

function send_review_invitation(int $requestId, string $token): bool
{
    if(review_token($token)==='') throw new InvalidArgumentException('Opprett en ny invitasjon.');
    db()->beginTransaction();
    try {
        $request=query('SELECT email,status FROM contact_requests WHERE id=? FOR UPDATE',[$requestId])->fetch();
        $invitation=query('SELECT *,expires_at>UTC_TIMESTAMP() AS current_invitation FROM review_invitations WHERE request_id=? FOR UPDATE',[$requestId])->fetch();
        if(!$request || $request['status']!=='completed' || !$invitation || !hash_equals($invitation['token_hash'],hash('sha256',$token)) || !$invitation['current_invitation'] || $invitation['revoked_at'] || $invitation['used_at']) throw new InvalidArgumentException('Invitasjonen er ikke tilgjengelig. Opprett en ny ved behov.');
        if(!filter_var($request['email'],FILTER_VALIDATE_EMAIL) || preg_match('/[\r\n]/',$request['email'])) throw new InvalidArgumentException('Forespørselen har ingen gyldig e-postadresse. Del lenken direkte med kunden.');
        if($invitation['mail_sent_at']) { db()->commit(); return true; }
        $mailConfig=config();
        $mailConfig['recipient_email']=$request['email'];
        $body="Takk for at du valgte fiksitt.\n\nDu kan dele din erfaring med jobben her:\n".review_invitation_url($token)."\n\nLenken gjelder i 30 dager og kan brukes til én omtale.\nOmtalen publiseres etter gjennomgang. Det er frivillig å svare.\n";
        if(!send_notification($mailConfig,'Hvordan var jobben? | fiksitt',$body)) throw new RuntimeException('Invitation mail failed');
        query('UPDATE review_invitations SET mail_sent_at=UTC_TIMESTAMP() WHERE id=?',[$invitation['id']]);
        db()->commit();
        return true;
    } catch(Throwable $error) { if(db()->inTransaction()) db()->rollBack(); throw $error; }
}

function moderate_review(int $id, string $status, int $adminId, string $note = ''): void
{
    if(!in_array($status,['approved','pending','rejected'],true)) throw new InvalidArgumentException('Ugyldig status.');
    if(preg_match('//u',$note)!==1 || str_contains($note,"\0") || mb_strlen($note,'UTF-8')>500) throw new InvalidArgumentException('Notatet kan ha inntil 500 tegn.');
    db()->beginTransaction();
    try {
        $row=query('SELECT request_id FROM reviews WHERE id=?',[$id])->fetch();
        if(!$row) throw new InvalidArgumentException('Omtalen finnes ikke.');
        $request=query('SELECT status FROM contact_requests WHERE id=? FOR UPDATE',[$row['request_id']])->fetch();
        $review=query('SELECT verified_at FROM reviews WHERE id=? FOR UPDATE',[$id])->fetch();
        if(!$review || !$request) throw new InvalidArgumentException('Omtalen finnes ikke.');
        if($status==='approved' && (!$review['verified_at'] || $request['status']!=='completed')) throw new InvalidArgumentException('Kun bekreftede omtaler til fullførte forespørsler kan publiseres.');
        query('UPDATE reviews SET status=?,published_at='.($status==='approved'?'COALESCE(published_at,UTC_TIMESTAMP())':'NULL').',moderated_by=?,moderated_at=UTC_TIMESTAMP(),moderation_note=? WHERE id=?',[$status,$adminId,trim($note),$id]);
        db()->commit();
    } catch(Throwable $error) { if(db()->inTransaction()) db()->rollBack(); throw $error; }
}

function delete_review(int $id): void
{
    db()->beginTransaction();
    try {
        $row = query('SELECT request_id FROM reviews WHERE id=?', [$id])->fetch();
        if (!$row) throw new InvalidArgumentException('Omtalen finnes ikke.');
        query('SELECT id FROM contact_requests WHERE id=? FOR UPDATE', [$row['request_id']]);
        $review = query('SELECT request_id FROM reviews WHERE id=? FOR UPDATE', [$id])->fetch();
        if (!$review) throw new InvalidArgumentException('Omtalen finnes ikke.');
        // Deletion must not make an already delivered invitation usable again.
        query('UPDATE review_invitations SET revoked_at=UTC_TIMESTAMP() WHERE request_id=?', [$row['request_id']]);
        query('DELETE FROM reviews WHERE id=?', [$id]);
        db()->commit();
    } catch (Throwable $error) {
        if (db()->inTransaction()) db()->rollBack();
        throw $error;
    }
}

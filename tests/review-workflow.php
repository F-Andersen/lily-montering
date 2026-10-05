<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli') {http_response_code(404);exit;}
require dirname(__DIR__).'/app/reviews.php';
require dirname(__DIR__).'/app/review-workflow.php';
if(config()['app_env']!=='local'||config()['database']['name']!=='fiksitt_reviews_qa'||config()['app_url']!=='http://127.0.0.1:18084') throw new RuntimeException('Isolated QA only');
function expect(bool $ok,string $message): void {if(!$ok) throw new RuntimeException($message);}
function rejects(callable $action): void {try{$action();}catch(InvalidArgumentException $ex){return;}throw new RuntimeException('Invalid input accepted');}
function pass(string $name): void {global $groups;$groups[]=$name;echo 'PASS '.$name.PHP_EOL;}
if(getenv('QA_CLEANUP_UI')==='1') {
    $f=json_decode(file_get_contents(dirname(__DIR__).'/.qa/review-workflow-fixture.json'),true,512,JSON_THROW_ON_ERROR);
    expect(preg_match('/^QA WORKFLOW [a-f0-9]{12}$/D',$f['tag'])===1,'Wrong fixture');
    query('DELETE FROM contact_requests WHERE name=?',[$f['tag']]);
    query('DELETE FROM admins WHERE id=? AND email=?',[$f['adminId'],$f['email']]);
    echo "Own UI request, reviews, invitations and account cleaned.\n";exit;
}
$tag='QA WORKFLOW '.bin2hex(random_bytes(6));$ids=[];$adminId=0;$keep=false;$groups=[];
function request_fixture(string $status='completed',int $archived=0): int {
    global $tag,$ids;
    query('INSERT INTO contact_requests(name,phone,email,service,area,message,status,archived) VALUES (?,?,?,?,?,?,?,?)',[$tag,'+4791234567','review-client@example.test','Veggmontering','QA','Synthetic review workflow test.',$status,$archived]);
    $id=(int)db()->lastInsertId();$ids[]=$id;return $id;
}
try {
    db()->exec(file_get_contents(dirname(__DIR__).'/database/migrations/20261004_reviews.sql'));
    $before=query('SELECT COUNT(*) FROM contact_requests')->fetchColumn();
    $migration=file_get_contents(dirname(__DIR__).'/database/migrations/20261004_review_workflow.sql');db()->exec($migration);db()->exec($migration);
    expect(query('SELECT COUNT(*) FROM contact_requests')->fetchColumn()===$before,'Migration changed enquiries');
    pass('Workflow migration is additive and idempotent');
    $email='qa-review-'.bin2hex(random_bytes(6)).'@example.test';$password=bin2hex(random_bytes(20));
    query('INSERT INTO admins(email,password_hash) VALUES (?,?)',[$email,password_hash($password,PASSWORD_DEFAULT)]);$adminId=(int)db()->lastInsertId();
    rejects(fn()=>create_review_invitation(request_fixture('new'),$adminId));
    rejects(fn()=>create_review_invitation(request_fixture('completed',1),$adminId));
    rejects(fn()=>create_review_invitation(PHP_INT_MAX,$adminId));
    $id=request_fixture();$a=create_review_invitation($id,$adminId);$hash=hash('sha256',$a['token']);
    $row=query('SELECT * FROM review_invitations WHERE request_id=?',[$id])->fetch();
    expect(strlen($a['token'])===64 && $row['token_hash']===$hash && $row['token_hash']!==$a['token'],'Token not random hashed secret');
    expect(abs(strtotime($row['expires_at'].' UTC')-time()-30*86400)<5,'Wrong lifetime');
    expect(!parse_url(review_invitation_url($a['token']),PHP_URL_QUERY),'Token in HTTP query');
    expect(review_token(review_invitation_url($a['token']))===$a['token'] && review_token(['x'])==='' && review_token(str_repeat('a',65))==='','Bad token parsing');
    pass('Completed-only invitations; 256-bit tokens, hash-only storage, 30-day expiry, URL fragment');
    $b=create_review_invitation($id,$adminId);expect(review_invitation_by_hash($hash)===false,'Old token survived rotation');
    $hash=hash('sha256',$b['token']);expect(review_invitation_usable(review_invitation_by_hash($hash)),'Fresh token invalid');
    expect(review_invitation_usable(review_invitation_by_hash($hash)),'Read consumed invite');
    revoke_review_invitation($id);expect(!review_invitation_usable(review_invitation_by_hash($hash)),'Revoked token valid');
    $a=create_review_invitation($id,$adminId);$hash=hash('sha256',$a['token']);
    query('UPDATE review_invitations SET expires_at=UTC_TIMESTAMP()-INTERVAL 1 SECOND WHERE request_id=?',[$id]);
    expect(!review_invitation_usable(review_invitation_by_hash($hash)),'Expired token valid');
    rejects(fn()=>submit_review($hash,['display_name'=>'QA','body'=>'Test body valid','rating'=>1]));
    $a=create_review_invitation($id,$adminId);$hash=hash('sha256',$a['token']);query("UPDATE contact_requests SET status='in_progress' WHERE id=?",[$id]);
    expect(!review_invitation_usable(review_invitation_by_hash($hash)),'Unfinished job token valid');query("UPDATE contact_requests SET status='completed' WHERE id=?",[$id]);
    pass('Rotation, revocation, expiry and job status checked; reads do not consume tokens');
    $input=['display_name'=>'QA Public Name','body'=>'TEST ONLY: honest one-star review.','rating'=>'1','consent'=>'1'];
    expect(mb_strlen(review_fields(array_replace($input,['body'=>str_repeat("\u{1F600}",1500)]))['body'],'UTF-8')===1500,'UTF-8 boundary rejected');
    foreach([['rating'=>'6'],['rating'=>'1.0'],['rating'=>['5']],['body'=>'short'],['body'=>str_repeat('x',1501)],['display_name'=>str_repeat('x',81)],['display_name'=>"bad\0name"],['body'=>"bad\xff"],['consent'=>'0']] as $invalid) rejects(fn()=>review_fields(array_replace($input,$invalid)));
    pass('Rating, UTF-8, length, scalar fields and publication consent enforced');
    if(getenv('QA_SMTP_FAILURE')==='1') {
        try {send_review_invitation($id,$a['token']);throw new LogicException('SMTP should fail');}catch(LogicException $ex){throw $ex;}catch(Throwable $ex){}
        $saved=query('SELECT token_hash,mail_sent_at FROM review_invitations WHERE request_id=?',[$id])->fetch();
        expect($saved['token_hash']===$hash && !$saved['mail_sent_at'],'SMTP failure lost invite or claimed delivery');
        pass('SMTP outage keeps invitation and never marks mail accepted');
    } else {
        expect(send_review_invitation($id,$a['token']),'Mailpit rejected invite');
        expect(send_review_invitation($id,$a['token']),'Idempotent send failed');
        expect((bool)query('SELECT mail_sent_at FROM review_invitations WHERE request_id=?',[$id])->fetchColumn(),'Missing mail flag');
        pass('Mailpit accepts customer invitation; repeated send is idempotent');
    }
    db()->exec("CREATE TRIGGER qa_review_use_failure BEFORE UPDATE ON review_invitations FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Synthetic token update failure'");
    try {
        try { submit_review($hash,review_fields($input)); throw new RuntimeException('Expected rollback'); }
        catch(PDOException $error) { expect($error->getCode()==='45000','Unexpected rollback exception'); }
    } finally { db()->exec('DROP TRIGGER qa_review_use_failure'); }
    expect((int)query('SELECT COUNT(*) FROM reviews WHERE request_id=?',[$id])->fetchColumn()===0 && review_invitation_usable(review_invitation_by_hash($hash)),'Partial review or consumed token survived rollback');
    pass('Failed token consumption rolls back review and preserves usable invitation');
    $reviewId=submit_review($hash,review_fields($input));
    $review=query('SELECT * FROM reviews WHERE id=?',[$reviewId])->fetch();$available=true;
    expect($review['status']==='pending' && $review['verified_at'] && !$review['published_at'] && public_reviews($available)===[],'Review leaked before approval');
    expect(!review_invitation_usable(review_invitation_by_hash($hash)),'Token not consumed');
    rejects(fn()=>submit_review($hash,review_fields($input)));rejects(fn()=>create_review_invitation($id,$adminId));
    pass('Atomic verified pending review and token consumption; duplicates blocked');
    moderate_review($reviewId,'approved',$adminId,'QA internal note');
    $public=public_reviews($available);expect(count($public)===1 && (int)$public[0]['rating']===1,'Low rating not published faithfully');
    expect(!array_key_exists('moderation_note',$public[0]),'Internal note leaked');
    $review=query('SELECT * FROM reviews WHERE id=?',[$reviewId])->fetch();expect((int)$review['moderated_by']===$adminId && $review['moderated_at'] && $review['body']===$input['body'],'Audit missing or text changed');
    moderate_review($reviewId,'pending',$adminId);expect(public_reviews($available)===[],'Unpublished review still visible');
    moderate_review($reviewId,'rejected',$adminId,'QA reason');expect(public_reviews($available)===[],'Rejected review visible');
    rejects(fn()=>moderate_review($reviewId,'unknown',$adminId));
    query('UPDATE reviews SET verified_at=NULL WHERE id=?',[$reviewId]);rejects(fn()=>moderate_review($reviewId,'approved',$adminId));
    query('UPDATE reviews SET verified_at=UTC_TIMESTAMP() WHERE id=?',[$reviewId]);query("UPDATE contact_requests SET status='in_progress' WHERE id=?",[$id]);rejects(fn()=>moderate_review($reviewId,'approved',$adminId));
    pass('Rating-neutral approval, withdrawal, rejection and moderator audit; no content editing');
    query("UPDATE contact_requests SET status='completed' WHERE id=?", [$id]);
    db()->exec("CREATE TRIGGER qa_review_delete_failure BEFORE DELETE ON reviews FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Synthetic delete failure'");
    try {
        try { delete_review($reviewId); throw new RuntimeException('Expected delete rollback'); }
        catch (PDOException $error) { expect($error->getCode()==='45000', 'Unexpected delete exception'); }
    } finally { db()->exec('DROP TRIGGER qa_review_delete_failure'); }
    expect((bool)query('SELECT id FROM reviews WHERE id=?', [$reviewId])->fetch(), 'Failed delete lost review');
    expect(!query('SELECT revoked_at FROM review_invitations WHERE request_id=?', [$id])->fetchColumn(), 'Failed delete revoked invitation');
    delete_review($reviewId);
    expect(!query('SELECT id FROM reviews WHERE id=?', [$reviewId])->fetch(), 'Deleted review survived');
    expect((bool)query('SELECT id FROM contact_requests WHERE id=?', [$id])->fetch(), 'Deletion removed enquiry');
    expect((bool)query('SELECT revoked_at FROM review_invitations WHERE request_id=?', [$id])->fetchColumn(), 'Old invite not revoked');
    rejects(fn()=>submit_review($hash,review_fields($input)));
    rejects(fn()=>delete_review($reviewId));
    foreach (['pending','approved','rejected'] as $state) {
        $job = request_fixture();
        $invite = create_review_invitation($job, $adminId);
        $review = submit_review(hash('sha256', $invite['token']), review_fields($input));
        if ($state !== 'pending') moderate_review($review, $state, $adminId);
        $original = query('SELECT * FROM contact_requests WHERE id=?', [$job])->fetch();
        delete_review($review);
        expect(!query('SELECT id FROM reviews WHERE id=?', [$review])->fetch(), 'State-specific deletion failed');
        expect(query('SELECT * FROM contact_requests WHERE id=?', [$job])->fetch() === $original, 'Deletion changed enquiry');
    }
    pass('Review deletion is atomic, preserves enquiry and cannot reactivate the old invitation');
    query('DELETE FROM contact_requests WHERE id=? AND name=?',[$id,$tag]);
    expect(!query('SELECT id FROM review_invitations WHERE request_id=?',[$id])->fetch() && !query('SELECT id FROM reviews WHERE request_id=?',[$id])->fetch(),'Cascade failed');
    pass('Deleting enquiry cascades invitation and review');
    if(getenv('QA_KEEP_FOR_UI')==='1') {
        $uiId=request_fixture();
        file_put_contents(dirname(__DIR__).'/.qa/review-workflow-fixture.json',json_encode(['tag'=>$tag,'requestId'=>$uiId,'adminId'=>$adminId,'email'=>$email,'password'=>$password]));
        $keep=true;
    }
} finally {
    foreach($ids as $id) if(!$keep || $id!==$uiId) query('DELETE FROM contact_requests WHERE id=? AND name=?',[$id,$tag]);
    if(!$keep && $adminId) query('DELETE FROM admins WHERE id=? AND email=?',[$adminId,$email]);
    $resultFile=getenv('QA_SMTP_FAILURE')==='1'?'review-workflow-mail-failure.json':'review-workflow-results.json';
    file_put_contents(dirname(__DIR__).'/.qa/'.$resultFile,json_encode(['groups'=>$groups,'passed'=>count($groups)===10,'keptForUI'=>$keep],JSON_PRETTY_PRINT));
}

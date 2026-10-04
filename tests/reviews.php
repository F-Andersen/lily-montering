<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require dirname(__DIR__) . '/app/reviews.php';
if (config()['app_env'] !== 'local' || config()['database']['name'] !== 'fiksitt_reviews_qa' || config()['app_url'] !== 'http://127.0.0.1:18084') throw new RuntimeException('Isolated reviews QA only');
function check(bool $condition, string $message): void { if (!$condition) throw new RuntimeException($message); }
function render_reviews(array $rows, bool $available): string { ob_start(); reviews_section($rows,$available); return ob_get_clean(); }
$tag = 'QA REVIEWS ' . bin2hex(random_bytes(6));
$ids = [];
$keep = false;
$groups = [];
function passed(string $name): void { global $groups; $groups[]=$name; echo 'PASS '.$name.PHP_EOL; }
try {
    $before = query('SELECT COUNT(*) FROM contact_requests')->fetchColumn();
    $migration = file_get_contents(dirname(__DIR__).'/database/migrations/20261004_reviews.sql');
    db()->exec($migration); db()->exec($migration);
    check(query('SELECT COUNT(*) FROM contact_requests')->fetchColumn()===$before,'Migration changed requests');
    passed('Additive idempotent migration preserves requests');
    $available = true;
    check(public_reviews($available)===[] && $available,'Expected empty QA database');
    $empty=render_reviews([],true);
    check(str_contains($empty,'Ingen kundeomtaler publisert ennå.'),'Missing honest empty state');
    check(!str_contains($empty,'review-rating') && !str_contains($empty,'Bekreftet kunde'),'Empty section must not invent ratings');
    check(str_contains($empty,'ikke en ekte kundeomtale') && str_contains($empty,'fiktivt navn'),'Demonstration must be clearly labeled');
    check(str_contains($empty,'hvitt-garderoberom.webp'),'Demonstration work photo missing');
    check(!str_contains(render_reviews([],false),'review-demo'),'Do not display demonstration during storage outage');
    passed('Honest empty state without ratings or review invitation');
    $now=time();
    $variants=[['pending',true,-100,'completed'],['rejected',true,-100,'completed'],['approved',false,-100,'completed'],['approved',true,3600,'completed'],['approved',true,-100,'in_progress']];
    for($i=0;$i<13;$i++) {
        [$status,$verified,$offset,$jobStatus]=$variants[$i]??['approved',true,-100-$i,'completed'];
        query('INSERT INTO contact_requests(name,phone,service,area,message,status) VALUES (?,?,?,?,?,?)',[$tag,'+4791234567','Veggmontering','QA','Synthetic review test, never production.',$jobStatus]);
        $id=(int)db()->lastInsertId(); $ids[]=$id;
        $name=$i===5?'<script>QA</script>':'TEST Kunde '.$i;
        $body=$i===5?'TEST ONLY: <img src=x onerror=alert(1)> & "quote".':($i===6?'TEST ONLY: '.str_repeat('LongWord',175):'TEST ONLY: responsive review fixture, not a real customer testimonial.');
        query('INSERT INTO reviews(request_id,display_name,rating,body,status,verified_at,published_at) VALUES (?,?,?,?,?,?,?)',[$id,$name,($i%5)+1,$body,$status,$verified?gmdate('Y-m-d H:i:s',$now-200):null,gmdate('Y-m-d H:i:s',$now+$offset)]);
    }
    $rows=public_reviews($available);
    check($available && count($rows)===6,'Only six eligible reviews should be public');
    check($rows[0]['display_name']==='<script>QA</script>' && $rows[5]['display_name']==='TEST Kunde 10','Wrong newest-first order');
    foreach($rows as $row) check(array_keys($row)===['display_name','rating','body','published_at','service'],'Private request data exposed');
    passed('Only approved verified completed past-dated reviews; newest six; public fields only');
    $html=render_reviews($rows,true);
    check(!str_contains($html,'review-demo'),'Demonstration must disappear when real reviews exist');
    check(!str_contains($html,'<script>QA</script>') && !str_contains($html,'<img src=x'),'Stored HTML injection');
    check(str_contains($html,'&lt;script&gt;QA&lt;/script&gt;') && str_contains($html,'&lt;img src=x'),'Escaping missing');
    check(substr_count($html,'src="/assets/icons/star.svg"')===30,'Wrong icon count');
    check(str_contains($html,'aria-label="1 av 5 stjerner"'),'Accessible rating missing');
    check(substr_count($html,'<details class="review-text">')===1,'Long reviews must have a native disclosure');
    check(str_contains($html,e(mb_substr($rows[1]['body'],0,240,'UTF-8')).'…'),'Long review excerpt missing');
    check(str_contains($html,'<p>'.e($rows[1]['body']).'</p>'),'Full review text missing');
    check(!str_contains($html,'AggregateRating'),'Do not add self-serving aggregate schema');
    passed('Escaped text, accessible ratings, long-text disclosure, no fabricated aggregate structured data');
    foreach([0,6] as $rating) {
        try { query('UPDATE reviews SET rating=? WHERE request_id=?',[$rating,$ids[5]]); throw new RuntimeException('Invalid rating accepted'); }
        catch(PDOException $error) { check($error->getCode()==='23000','Unexpected constraint error'); }
    }
    try { query('INSERT INTO reviews(request_id,display_name,rating,body) VALUES (?,?,?,?)',[$ids[5],'duplicate',5,'test']); throw new RuntimeException('Duplicate request review accepted'); }
    catch(PDOException $error) { check($error->getCode()==='23000','Unexpected unique error'); }
    passed('Rating constraints and one-review-per-request enforced');
    db()->exec('RENAME TABLE reviews TO qa_reviews_unavailable');
    try { $unavailable=public_reviews($available); check(!$available && $unavailable===[],'Outage not handled'); check(str_contains(render_reviews($unavailable,$available),'midlertidig utilgjengelige'),'Wrong outage state'); }
    finally { db()->exec('RENAME TABLE qa_reviews_unavailable TO reviews'); }
    passed('Missing storage yields temporary unavailable state, not false no-reviews claim');
    query('DELETE FROM contact_requests WHERE id=? AND name=?',[$ids[12],$tag]);
    check(!query('SELECT id FROM reviews WHERE request_id=?',[$ids[12]])->fetch(),'Review not removed with private request');
    passed('Deleting enquiry cascades its review');
    if(getenv('QA_KEEP_FOR_UI')==='1') {
        file_put_contents(dirname(__DIR__).'/.qa/reviews-fixture.json',json_encode(['tag'=>$tag,'ids'=>$ids]));
        $keep=true;
    }
} finally {
    if(!$keep) foreach($ids as $id) query('DELETE FROM contact_requests WHERE id=? AND name=?',[$id,$tag]);
    file_put_contents(dirname(__DIR__).'/.qa/reviews-results.json',json_encode(['groups'=>$groups,'passed'=>count($groups)===7,'keptForUI'=>$keep],JSON_PRETTY_PRINT));
}

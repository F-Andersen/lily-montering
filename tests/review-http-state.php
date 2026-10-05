<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli') exit(1);
require dirname(__DIR__).'/app/review-workflow.php';
if(config()['app_env']!=='local'||config()['database']['name']!=='fiksitt_reviews_qa'||config()['app_url']!=='http://127.0.0.1:18084') throw new RuntimeException('Isolated QA only');
$tag=getenv('QA_TAG');
if(!is_string($tag)||!preg_match('/^QA HTTP REVIEWS [a-f0-9]{12}$/D',$tag)) throw new RuntimeException('Invalid test tag');
$mode=$argv[1]??'';$email='qa-http-'.substr($tag,-12).'@example.test';
$demoFile = dirname(__DIR__).'/.qa/review-demo-'.substr($tag,-12).'.json';
if($mode==='create' || $mode==='create-ui') {
 $demo = query('SELECT setting_value FROM site_settings WHERE setting_key=?', ['review_demo_enabled'])->fetchColumn();
 file_put_contents($demoFile, json_encode(['value'=>$demo], JSON_THROW_ON_ERROR));
 $password=bin2hex(random_bytes(20));query('INSERT INTO admins(email,password_hash) VALUES (?,?)',[$email,password_hash($password,PASSWORD_DEFAULT)]);$adminId=(int)db()->lastInsertId();
 query('INSERT INTO contact_requests(name,phone,email,service,area,message,status) VALUES (?,?,?,?,?,?,?)',[$tag,'+4791234567','http-customer@example.test','Veggmontering','QA','HTTP fixture','completed']);$id=(int)db()->lastInsertId();
 query('INSERT INTO contact_requests(name,phone,email,service,area,message,status) VALUES (?,?,?,?,?,?,?)',[$tag.' new','+4791234567','http-customer@example.test','Veggmontering','QA','HTTP fixture','new']);$newId=(int)db()->lastInsertId();
 $fixture=['tag'=>$tag,'id'=>$id,'newId'=>$newId,'adminId'=>$adminId,'email'=>$email,'password'=>$password];
 if ($mode === 'create-ui') {
  file_put_contents(dirname(__DIR__).'/.qa/review-invitation-ui.json', json_encode($fixture, JSON_THROW_ON_ERROR));
  echo json_encode(['id'=>$id,'newId'=>$newId]);
 } else echo json_encode($fixture);
} elseif($mode==='create-ui-review') {
 $request = query('SELECT id FROM contact_requests WHERE name=? AND status=?', [$tag, 'completed'])->fetch();
 if (!$request) throw new RuntimeException('Own completed UI fixture required');
 $owner = query('SELECT id FROM admins WHERE email=?', [$email])->fetch();
 $invitation = create_review_invitation((int)$request['id'], (int)$owner['id']);
 $id = submit_review(hash('sha256', $invitation['token']), ['display_name'=>'QA customer, not a real review', 'body'=>'Synthetic customer review for the admin deletion confirmation test.', 'rating'=>4]);
 echo json_encode(['reviewId'=>$id]);
} elseif($mode==='state') {
 $r=query('SELECT id FROM contact_requests WHERE name=?',[$tag])->fetch();
 echo json_encode(['reviews'=>query('SELECT id,display_name,rating,status,moderated_by,moderated_at,moderation_note FROM reviews WHERE request_id=?',[$r['id']])->fetchAll(),'invitations'=>query('SELECT used_at,mail_sent_at,revoked_at FROM review_invitations WHERE request_id=?',[$r['id']])->fetchAll()]);
} elseif($mode==='cleanup') {
 if (is_file($demoFile)) {
  $original = json_decode(file_get_contents($demoFile), true, 512, JSON_THROW_ON_ERROR)['value'];
  if ($original === false) query('DELETE FROM site_settings WHERE setting_key=?', ['review_demo_enabled']);
  else query('INSERT INTO site_settings(setting_key,setting_value) VALUES (?,?) ON DUPLICATE KEY UPDATE setting_value=VALUES(setting_value)', ['review_demo_enabled', $original]);
  unlink($demoFile);
 }
 query('DELETE FROM contact_requests WHERE name IN (?,?)',[$tag,$tag.' new']);query('DELETE FROM admins WHERE email=?',[$email]);
 // This guarded disposable container owns its own rate-limit storage.
 $dir=sys_get_temp_dir().'/lily-limits-'.substr(hash('sha256',dirname(__DIR__)),0,12);
 foreach(glob($dir.'/*.json')?:[] as $file) unlink($file);
 echo 'Own HTTP fixtures and isolated test rate limits cleaned.';
} else throw new RuntimeException('Unknown mode');

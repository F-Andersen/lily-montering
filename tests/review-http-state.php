<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli') exit(1);
require dirname(__DIR__).'/app/review-workflow.php';
if(config()['app_env']!=='local'||config()['database']['name']!=='fiksitt_reviews_qa'||config()['app_url']!=='http://127.0.0.1:18084') throw new RuntimeException('Isolated QA only');
$tag=getenv('QA_TAG');
if(!is_string($tag)||!preg_match('/^QA HTTP REVIEWS [a-f0-9]{12}$/D',$tag)) throw new RuntimeException('Invalid test tag');
$mode=$argv[1]??'';$email='qa-http-'.substr($tag,-12).'@example.test';
if($mode==='create') {
 $password=bin2hex(random_bytes(20));query('INSERT INTO admins(email,password_hash) VALUES (?,?)',[$email,password_hash($password,PASSWORD_DEFAULT)]);$adminId=(int)db()->lastInsertId();
 query('INSERT INTO contact_requests(name,phone,email,service,area,message,status) VALUES (?,?,?,?,?,?,?)',[$tag,'+4791234567','http-customer@example.test','Veggmontering','QA','HTTP fixture','completed']);$id=(int)db()->lastInsertId();
 echo json_encode(['id'=>$id,'adminId'=>$adminId,'email'=>$email,'password'=>$password]);
} elseif($mode==='state') {
 $r=query('SELECT id FROM contact_requests WHERE name=?',[$tag])->fetch();
 echo json_encode(['reviews'=>query('SELECT id,display_name,rating,status,moderated_by,moderated_at,moderation_note FROM reviews WHERE request_id=?',[$r['id']])->fetchAll(),'invitations'=>query('SELECT used_at,mail_sent_at,revoked_at FROM review_invitations WHERE request_id=?',[$r['id']])->fetchAll()]);
} elseif($mode==='cleanup') {
 query('DELETE FROM contact_requests WHERE name=?',[$tag]);query('DELETE FROM admins WHERE email=?',[$email]);
 // This guarded disposable container owns its own rate-limit storage.
 $dir=sys_get_temp_dir().'/lily-limits-'.substr(hash('sha256',dirname(__DIR__)),0,12);
 foreach(glob($dir.'/*.json')?:[] as $file) unlink($file);
 echo 'Own HTTP fixtures and isolated test rate limits cleaned.';
} else throw new RuntimeException('Unknown mode');

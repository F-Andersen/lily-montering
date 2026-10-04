<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') exit(1);
require dirname(__DIR__) . '/app/uploads.php';
if (config()['app_env'] !== 'local' || config()['database']['name'] !== 'fiksitt_reviews_qa' || config()['app_url'] !== 'http://127.0.0.1:18084') throw new RuntimeException('Isolated QA only');
$tag = getenv('QA_TAG');
if (!is_string($tag) || !preg_match('/^QA GALLERY [a-f0-9]{12}$/D', $tag)) throw new RuntimeException('Invalid fixture tag');
$email = 'qa-gallery-' . substr($tag, -12) . '@example.test';
$mode = $argv[1] ?? '';
$trigger = 'qa_gallery_' . substr($tag, -12);
if ($mode === 'create') {
    $password = bin2hex(random_bytes(20));
    query('INSERT INTO admins(email,password_hash) VALUES (?,?)', [$email, password_hash($password, PASSWORD_DEFAULT)]);
    echo json_encode(['email' => $email, 'password' => $password]);
} elseif ($mode === 'rows') {
    $rows = query("SELECT * FROM gallery_items WHERE caption LIKE ?", [$tag . '%'])->fetchAll();
    foreach ($rows as &$row) {
        $row['files'] = [];
        foreach (['', '-900', '-320'] as $suffix) {
            $path = dirname(__DIR__) . '/' . preg_replace('/\.webp$/', $suffix . '.webp', $row['image']);
            $size = @getimagesize($path);
            $row['files'][] = ['size' => is_file($path) ? filesize($path) : 0, 'dimensions' => $size ? [$size[0], $size[1]] : false, 'mime' => is_file($path) ? (new finfo(FILEINFO_MIME_TYPE))->file($path) : 'missing'];
        }
    }
    unset($row);
    echo json_encode($rows);
} elseif ($mode === 'share') {
    $row = query('SELECT * FROM gallery_items WHERE caption = ?', [$tag . ' webp'])->fetch();
    if (!$row) throw new RuntimeException('Missing own image');
    query('INSERT INTO gallery_items(image,caption,alt_text) VALUES (?,?,?)', [$row['image'], $tag . ' shared', 'QA shared image']);
    echo db()->lastInsertId();
} elseif ($mode === 'files') {
    echo json_encode(count(glob(dirname(__DIR__) . '/assets/uploads/*.webp') ?: []));
} elseif ($mode === 'fail-insert') {
    db()->exec("CREATE TRIGGER $trigger BEFORE INSERT ON gallery_items FOR EACH ROW BEGIN IF NEW.caption LIKE '$tag%' THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'QA insert failure'; END IF; END");
    echo 'true';
} elseif ($mode === 'failure-off') {
    db()->exec("DROP TRIGGER IF EXISTS $trigger"); echo 'true';
} elseif ($mode === 'cleanup') {
    db()->exec("DROP TRIGGER IF EXISTS $trigger");
    $rows = query('SELECT image FROM gallery_items WHERE caption LIKE ?', [$tag . '%'])->fetchAll();
    query('DELETE FROM gallery_items WHERE caption LIKE ?', [$tag . '%']);
    foreach ($rows as $row) remove_unused_upload($row['image']);
    query('DELETE FROM admins WHERE email = ?', [$email]);
    echo 'Own gallery fixtures cleaned.';
} else throw new RuntimeException('Unknown mode');

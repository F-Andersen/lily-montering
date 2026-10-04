<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') exit(1);
require dirname(__DIR__) . '/app/bootstrap.php';
if (config()['app_env'] !== 'local' || config()['database']['name'] !== 'fiksitt_reviews_qa' || config()['app_url'] !== 'http://127.0.0.1:18084') throw new RuntimeException('Isolated QA only');
$tag = getenv('QA_TAG');
if (!is_string($tag) || !preg_match('/^QA I18N [a-f0-9]{12}$/D', $tag)) throw new RuntimeException('Invalid fixture tag');
$email = 'qa-i18n-' . substr($tag, -12) . '@example.test';
if (($argv[1] ?? '') === 'create') {
    $password = bin2hex(random_bytes(20));
    query('INSERT INTO admins(email,password_hash) VALUES (?,?)', [$email, password_hash($password, PASSWORD_DEFAULT)]);
    query('INSERT INTO contact_requests(name,phone,email,service,area,message) VALUES (?,?,?,?,?,?)', [$tag, '+4712345678', $email, 'Lagre', 'Lagre', 'Lagre']);
    echo json_encode(['email' => $email, 'password' => $password, 'id' => (int)db()->lastInsertId()]);
} elseif (($argv[1] ?? '') === 'cleanup') {
    query('DELETE FROM contact_requests WHERE email = ? AND name = ?', [$email, $tag]);
    query('DELETE FROM admins WHERE email = ?', [$email]);
    echo 'Own i18n fixtures cleaned.';
} else throw new RuntimeException('Unknown mode');

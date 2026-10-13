<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require dirname(__DIR__) . '/app/auth.php';
$config = config();
if ($config['app_env'] !== 'local' || $config['database']['name'] !== 'fiksitt_auth_qa'
    || $config['app_url'] !== 'http://127.0.0.1' || getenv('FIKSITT_AUTH_QA') !== '1') throw new RuntimeException('Isolated authentication QA only');
$tag = getenv('AUTH_QA_TAG');
if (!is_string($tag) || !preg_match('/^[a-f0-9]{12}$/D', $tag)) throw new RuntimeException('Invalid authentication fixture tag');
$email = 'qa-auth-' . $tag . '@example.test';
$password = hash('sha256', 'synthetic-auth-password:' . $tag);
$mode = $argv[1] ?? '';
function auth_check(bool $condition, string $message): void { if (!$condition) throw new RuntimeException($message); }
function auth_row(string $email): array|false { return query('SELECT * FROM admins WHERE email = ?', [$email])->fetch(); }

if ($mode === 'create') {
    $hash = password_hash($password, PASSWORD_DEFAULT);
    foreach ([$email, 'other-' . $email] as $address) query('INSERT INTO admins(email,password_hash) VALUES (?,?)', [$address, $hash]);
    $aliases = [$email, str_replace('qa-auth-', "q\u{00e1}-auth-", $email), str_replace('qa-auth-', "q\u{00e4}-auth-", $email)];
    $id = (int)auth_row($email)['id'];
    foreach ($aliases as $alias) auth_check((int)auth_row($alias)['id'] === $id, 'Alias is not SQL-equivalent');
    echo json_encode(['email' => $email, 'otherEmail' => 'other-' . $email, 'password' => $password, 'aliases' => $aliases]);
} elseif ($mode === 'legacy') {
    query('UPDATE admins SET password_hash = ?, active = 1 WHERE email = ?', [password_hash($password, PASSWORD_BCRYPT, ['cost' => 4]), $email]);
    echo 'true';
} elseif ($mode === 'inactive') {
    query('UPDATE admins SET active = 0 WHERE email = ?', [$email]);
    echo 'true';
} elseif ($mode === 'state') {
    $row = auth_row($email);
    echo json_encode(['needsRehash' => password_needs_rehash($row['password_hash'], PASSWORD_DEFAULT), 'originalPasswordWorks' => password_verify($password, $row['password_hash'])]);
} elseif ($mode === 'reset-limits') {
    // This guarded, unique QA container owns this temporary limiter directory.
    $dir = sys_get_temp_dir() . '/lily-limits-' . substr(hash('sha256', dirname(__DIR__)), 0, 12);
    foreach (glob($dir . '/*.json') ?: [] as $file) unlink($file);
    echo 'true';
} elseif ($mode === 'core') {
    $c = $config['database'];
    $peer = new PDO('mysql:host=' . $c['host'] . ';port=' . (int)$c['port'] . ';dbname=' . $c['name'] . ';charset=utf8mb4', $c['user'], $c['password'], [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_EMULATE_PREPARES => false]);
    $groups = [];
    $ids = [];
    try {
        foreach (['normal', 'change', 'rehash', 'inactive', 'deleted', 'binary'] as $case) {
            $old = password_hash($password, PASSWORD_BCRYPT, ['cost' => 4]);
            query('INSERT INTO admins(email,password_hash) VALUES (?,?)', [$case . '-' . $email, $old]);
            $id = (int)db()->lastInsertId();
            $ids[] = $id;
            $snapshot = query('SELECT * FROM admins WHERE id = ?', [$id])->fetch();
            auth_check(password_verify($password, $snapshot['password_hash']), 'Initial verification failed');
            if ($case === 'normal') {
                $result = refresh_admin_hash($snapshot, $password);
                auth_check($result !== false && !password_needs_rehash($result['password_hash'], PASSWORD_DEFAULT), 'Normal upgrade failed');
                auth_check(password_verify($password, $result['password_hash']), 'Upgrade changed password');
                auth_check(refresh_admin_hash($result, $password) === $result, 'Current hash was unnecessarily changed');
            } else {
                $replacement = $case === 'rehash' ? password_hash($password, PASSWORD_DEFAULT) : password_hash('changed-' . $password, PASSWORD_DEFAULT);
                if ($case === 'binary') {
                    $replacement = $old;
                    for ($i = 7; $i < strlen($replacement); $i++) {
                        if (ctype_alpha($replacement[$i])) {
                            $replacement[$i] = ctype_lower($replacement[$i]) ? strtoupper($replacement[$i]) : strtolower($replacement[$i]);
                            break;
                        }
                    }
                    auth_check($replacement !== $old, 'Missing hash case variant');
                }
                $sql = match ($case) {
                    'inactive' => 'UPDATE admins SET active = 0 WHERE id = ?',
                    'deleted' => 'DELETE FROM admins WHERE id = ?',
                    default => 'UPDATE admins SET password_hash = ? WHERE id = ?',
                };
                $peer->prepare($sql)->execute(in_array($case, ['inactive', 'deleted'], true) ? [$id] : [$replacement, $id]);
                if ($case === 'binary') auth_check((int)query('SELECT password_hash = ? FROM admins WHERE id = ?', [$old, $id])->fetchColumn() === 1, 'Test requires case-insensitive hash collation');
                auth_check(refresh_admin_hash($snapshot, $password) === false, 'Stale or inactive credential was upgraded: ' . $case);
                $current = query('SELECT * FROM admins WHERE id = ?', [$id])->fetch();
                if ($case === 'deleted') auth_check($current === false, 'Deleted administrator recreated');
                elseif ($case === 'inactive') auth_check((int)$current['active'] === 0 && $current['password_hash'] === $old, 'Inactive hash changed');
                else auth_check($current['password_hash'] === $replacement, 'Concurrent hash overwritten: ' . $case);
            }
            $groups[] = $case;
        }
        echo json_encode(['groups' => $groups, 'passed' => true]);
    } finally {
        foreach ($ids as $id) query('DELETE FROM admins WHERE id = ?', [$id]);
    }
} elseif ($mode === 'cleanup') {
    query('DELETE FROM admins WHERE email IN (?,?)', [$email, 'other-' . $email]);
    echo 'Own authentication fixtures cleaned.';
} else throw new RuntimeException('Unknown authentication QA mode');

<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/app/admin-layout.php';
start_session();
if (!empty($_SESSION['admin_id'])) { require_admin(); redirect('admin/'); }
$error = '';
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    require_post_csrf();
    $email = strtolower(trim(is_string($_POST['email'] ?? null) ? $_POST['email'] : ''));
    $password = is_string($_POST['password'] ?? null) ? $_POST['password'] : '';
    $ip = (string)($_SERVER['REMOTE_ADDR'] ?? '');
    if (!rate_limit('login-ip', $ip, 20, 900) || !rate_limit('login-account', $email, 8, 900)) {
        http_response_code(429);
        $error = 'For mange forsøk. Prøv igjen om 15 minutter.';
    } else {
        try {
            $admin = query('SELECT * FROM admins WHERE email = ? AND active = 1', [$email])->fetch();
            // A fixed work factor also applies to unknown accounts.
            $dummy = PHP_VERSION_ID >= 80400
                ? '$2y$12$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2uheWG/igi.'
                : '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2uheWG/igi.';
            if (strlen($password) <= 200 && password_verify($password, $admin['password_hash'] ?? $dummy) && $admin) {
                session_regenerate_id(true);
                if (password_needs_rehash($admin['password_hash'], PASSWORD_DEFAULT)) {
                    $admin['password_hash'] = password_hash($password, PASSWORD_DEFAULT);
                    query('UPDATE admins SET password_hash = ? WHERE id = ?', [$admin['password_hash'], $admin['id']]);
                }
                $_SESSION = ['admin_id' => $admin['id'], 'last_activity' => time(), 'csrf' => bin2hex(random_bytes(32)), 'auth_version' => hash('sha256', $admin['password_hash'])];
                redirect('admin/');
            }
            $error = 'Feil e-postadresse eller passord.';
        } catch (Throwable $ex) { safe_log('login unavailable', $ex); http_response_code(503); $error = 'Innlogging er midlertidig utilgjengelig.'; }
    }
}
admin_header('Logg inn', false);
if ($error) admin_error($error);
?>
<form class="edit-form" method="post"><?= csrf_input() ?>
<label class="field" for="email"><span>E-post</span><input id="email" name="email" type="email" maxlength="190" autocomplete="username" required></label>
<label class="field" for="password"><span>Passord</span><input id="password" name="password" type="password" maxlength="200" autocomplete="current-password" required></label>
<button type="submit">Logg inn</button><a href="<?= e(url()) ?>">Til nettsiden</a></form>
<?php admin_footer(); ?>

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
    $ip = client_ip();
    try {
        $ipAllowed = rate_limit('login-ip', $ip, 20, 900);
        $admin = $ipAllowed ? query('SELECT * FROM admins WHERE email = ?', [$email])->fetch() : false;
        $identity = $admin ? 'admin:' . $admin['id'] : 'unknown:' . $email;
        if (!$ipAllowed || !rate_limit('login-account', $identity, 8, 900)) {
            http_response_code(429);
            header('Retry-After: 900');
            $error = admin_t('For mange forsøk. Prøv igjen om 15 minutter.');
        } else {
            $active = $admin && (int)$admin['active'] === 1;
            // A fixed work factor also applies to unknown accounts.
            $dummy = PHP_VERSION_ID >= 80400
                ? '$2y$12$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2uheWG/igi.'
                : '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2uheWG/igi.';
            if (strlen($password) <= 200 && password_verify($password, $active ? $admin['password_hash'] : $dummy) && $active) {
                $admin = refresh_admin_hash($admin, $password);
                if ($admin) {
                    session_regenerate_id(true);
                    $_SESSION = ['admin_id' => $admin['id'], 'last_activity' => time(), 'csrf' => bin2hex(random_bytes(32)), 'auth_version' => hash('sha256', $admin['password_hash']), 'admin_locale' => admin_locale()];
                    redirect('admin/');
                }
            }
            $error = admin_t('Feil e-postadresse eller passord.');
        }
    } catch (Throwable $ex) { safe_log('login unavailable', $ex); http_response_code(503); $error = admin_t('Innlogging er midlertidig utilgjengelig.'); }
}
admin_header(admin_t('Logg inn'), false);
if ($error) admin_error($error);
?>
<form class="edit-form" method="post"><?= csrf_input() ?>
<label class="field" for="email"><span><?= e(admin_t('E-post')) ?></span><input id="email" name="email" type="email" maxlength="190" autocomplete="username" required></label>
<label class="field" for="password"><span><?= e(admin_t('Passord')) ?></span><input id="password" name="password" type="password" maxlength="200" autocomplete="current-password" required></label>
<button type="submit"><?= e(admin_t('Logg inn')) ?></button><a href="<?= e(url()) ?>"><?= e(admin_t('Til nettsiden')) ?></a></form>
<?php admin_footer(); ?>

<?php
declare(strict_types=1);
require_once dirname(__DIR__, 2) . '/app/admin-layout.php';
$admin = require_admin();
$error = '';
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    require_post_csrf();
    $current = is_string($_POST['current_password'] ?? null) ? $_POST['current_password'] : '';
    $password = is_string($_POST['new_password'] ?? null) ? $_POST['new_password'] : '';
    $confirm = is_string($_POST['confirm_password'] ?? null) ? $_POST['confirm_password'] : '';
    try {
        if (!rate_limit('password-change', $admin['id'] . ':' . ($_SERVER['REMOTE_ADDR'] ?? ''), 5, 900)) {
            http_response_code(429);
            $error = admin_t('For mange forsøk. Prøv igjen om 15 minutter.');
        } else {
            $hash = query('SELECT password_hash FROM admins WHERE id = ? AND active = 1', [$admin['id']])->fetchColumn();
            if (strlen($current) > 200 || !$hash || !password_verify($current, $hash)) $error = admin_t('Nåværende passord er feil.');
            elseif (strlen($password) < 12 || strlen($password) > 72 || str_contains($password, "\0")) $error = admin_t('Det nye passordet må være mellom 12 og 72 byte.');
            elseif ($password !== $confirm) $error = admin_t('De nye passordene er ikke like.');
            elseif (password_verify($password, $hash)) $error = admin_t('Velg et annet passord enn det nåværende.');
            else {
                $newHash = password_hash($password, PASSWORD_DEFAULT);
                // Compare the previous hash to avoid racing another password change.
                $updated = query('UPDATE admins SET password_hash = ? WHERE id = ? AND password_hash = ?', [$newHash, $admin['id'], $hash]);
                if ($updated->rowCount() !== 1) throw new RuntimeException('Password changed concurrently');
                session_regenerate_id(true);
                $_SESSION['auth_version'] = hash('sha256', $newHash);
                $_SESSION['csrf'] = bin2hex(random_bytes(32));
                flash(admin_t('Passordet er endret. Andre innlogginger er avsluttet.'));
                redirect('admin/account/');
            }
            if ($error) http_response_code(422);
        }
    } catch (Throwable $ex) { safe_log('password change unavailable', $ex); http_response_code(503); $error = admin_t('Kunne ikke endre passordet. Prøv igjen senere.'); }
}
admin_header(admin_t('Konto'));
if ($error) admin_error($error);
?>
<dl class="request-detail"><dt><?= e(admin_t('Innlogget som')) ?></dt><dd><?= e($admin['email']) ?></dd></dl>
<h2><?= e(admin_t('Endre passord')) ?></h2>
<form class="edit-form" method="post"><?= csrf_input() ?>
<label class="field" for="current_password"><span><?= e(admin_t('Nåværende passord')) ?></span><input id="current_password" name="current_password" type="password" maxlength="200" autocomplete="current-password" required></label>
<label class="field" for="new_password"><span><?= e(admin_t('Nytt passord')) ?></span><input id="new_password" name="new_password" type="password" minlength="12" maxlength="72" autocomplete="new-password" required></label>
<label class="field" for="confirm_password"><span><?= e(admin_t('Bekreft nytt passord')) ?></span><input id="confirm_password" name="confirm_password" type="password" minlength="12" maxlength="72" autocomplete="new-password" required></label>
<button type="submit"><?= e(admin_t('Endre passord')) ?></button></form>
<?php admin_footer(); ?>

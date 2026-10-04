<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/app/admin-layout.php';
start_session();
$error = '';
$disabled = false;
try {
    $disabled = (int)query('SELECT COUNT(*) FROM admins')->fetchColumn() !== 0 || config()['setup_token'] === '';
    if ($disabled) http_response_code(404);
    elseif (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
        require_post_csrf();
        $ip = (string)($_SERVER['REMOTE_ADDR'] ?? '');
        $token = is_string($_POST['setup_token'] ?? null) ? $_POST['setup_token'] : '';
        $email = strtolower(trim(is_string($_POST['email'] ?? null) ? $_POST['email'] : ''));
        $password = is_string($_POST['password'] ?? null) ? $_POST['password'] : '';
        if (!rate_limit('setup', $ip, 8, 900)) { http_response_code(429); $error = admin_t('For mange forsøk. Prøv igjen senere.'); }
        elseif (!hash_equals(config()['setup_token'], $token)) $error = admin_t('Ugyldig oppsettnøkkel.');
        elseif (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 190) $error = admin_t('Skriv en gyldig e-postadresse.');
        elseif (strlen($password) < 12 || strlen($password) > 72) $error = admin_t('Passordet må være mellom 12 og 72 byte.');
        else {
            // A connection-scoped lock makes concurrent first-admin requests safe.
            $locked = (int)query("SELECT GET_LOCK('lily_first_admin', 5)")->fetchColumn() === 1;
            if (!$locked) throw new RuntimeException('Setup lock unavailable');
            try {
                if ((int)query('SELECT COUNT(*) FROM admins')->fetchColumn() !== 0) { $disabled = true; http_response_code(404); }
                else {
                    query('INSERT INTO admins (email,password_hash) VALUES (?,?)', [$email, password_hash($password, PASSWORD_DEFAULT)]);
                    flash(admin_t('Administrator opprettet. Logg inn.'));
                    redirect('admin/login.php');
                }
            } finally { query("SELECT RELEASE_LOCK('lily_first_admin')"); }
        }
    }
} catch (Throwable $ex) { safe_log('setup unavailable', $ex); http_response_code(503); $error = admin_t('Oppsett er midlertidig utilgjengelig. Kontroller databaseoppsettet.'); }
admin_header(admin_t('Opprett administrator'), false);
if ($disabled) admin_error(admin_t('Denne siden er ikke tilgjengelig.'));
elseif ($error) admin_error($error);
if (!$disabled):
?>
<form class="edit-form" method="post"><?= csrf_input() ?>
<label class="field" for="setup_token"><span><?= e(admin_t('Oppsettnøkkel')) ?></span><input id="setup_token" name="setup_token" type="password" autocomplete="off" required></label>
<?php admin_input('email', admin_t('E-post'), '', 'email', 190, true) ?>
<label class="field" for="password"><span><?= e(admin_t('Passord')) ?></span><input id="password" name="password" type="password" minlength="12" maxlength="72" autocomplete="new-password" required></label>
<button type="submit"><?= e(admin_t('Opprett administrator')) ?></button></form>
<?php endif; admin_footer(); ?>

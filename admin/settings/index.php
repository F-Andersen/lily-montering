<?php
declare(strict_types=1);
require_once dirname(__DIR__, 2) . '/app/crud.php';
require_once dirname(__DIR__, 2) . '/app/mail.php';
require_admin();
$labels = ['company_name' => admin_t('Firmanavn'), 'slogan' => admin_t('Slagord'), 'phone' => admin_t('Telefon'), 'email' => admin_t('E-post'), 'service_region' => admin_t('Område'), 'public_domain' => admin_t('Offentlig nettadresse'), 'organization_number' => admin_t('Organisasjonsnummer'), 'primary_cta' => admin_t('Primær knapptekst'), 'social_url' => admin_t('Sosial lenke')];
$labels += ['home_seo_title' => admin_t('SEO-tittel: forside'), 'home_seo_description' => admin_t('SEO-beskrivelse: forside')];
$values = settings();
$error = '';
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    require_post_csrf();
    try {
        if (($_POST['action'] ?? '') === 'test_email') {
            if (!rate_limit('admin-mail-test', (string)$_SESSION['admin_id'], 3, 600)) throw new InvalidArgumentException(admin_t('For mange testforsøk. Prøv igjen senere.'));
            if ($mailError = mail_configuration_error(config())) throw new InvalidArgumentException($mailError);
            if (!send_notification(config(), 'fiksitt: test av e-postlevering', "Dette er en testmelding fra fiksitt-administrasjonen.\nTidspunkt: " . gmdate('c'))) throw new RuntimeException('Test delivery failed');
            flash(admin_t('Testmeldingen er akseptert av e-postserveren. Kontroller mottakerens innboks og spammappe.'));
            redirect('admin/settings/#delivery-heading');
        }
        foreach ($labels as $key => $label) $values[$key] = post_text($key, $key === 'home_seo_description' ? 300 : ($key === 'public_domain' || $key === 'social_url' ? 255 : 160), in_array($key, ['company_name', 'service_region', 'primary_cta'], true));
        $values['seo_indexable'] = isset($_POST['seo_indexable']) ? '1' : '0';
        if ($values['seo_indexable'] === '1' && !seo_domain_valid(rtrim($values['public_domain'], '/'))) throw new InvalidArgumentException(admin_t('Indeksering krever et offentlig HTTPS-domene uten sti eller port.'));
        if ($values['email'] !== '' && !filter_var($values['email'], FILTER_VALIDATE_EMAIL)) throw new InvalidArgumentException(admin_t('Ugyldig e-postadresse.'));
        if ($values['phone'] !== '' && !preg_match('/^[0-9+() .-]{6,32}$/D', $values['phone'])) throw new InvalidArgumentException(admin_t('Ugyldig telefonnummer.'));
        foreach (['public_domain', 'social_url'] as $key) if ($values[$key] !== '') {
            $parts = parse_url($values[$key]);
            if (!filter_var($values[$key], FILTER_VALIDATE_URL) || ($parts['scheme'] ?? '') !== 'https' || isset($parts['user']) || isset($parts['pass']) || isset($parts['query']) || isset($parts['fragment'])) throw new InvalidArgumentException(admin_t('Nettadressen må være en gyldig HTTPS-adresse uten innlogging eller parametere.'));
        }
        db()->beginTransaction();
        foreach ($labels as $key => $label) query('INSERT INTO site_settings (setting_key,setting_value) VALUES (?,?) ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)', [$key, $values[$key]]);
        query('INSERT INTO site_settings (setting_key,setting_value) VALUES (?,?) ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)', ['seo_indexable', $values['seo_indexable']]);
        db()->commit();
        flash(admin_t('Innstillingene er lagret.'));
        redirect('admin/settings/');
    } catch (InvalidArgumentException $ex) { $error = $ex->getMessage(); }
    catch (Throwable $ex) {
        try { if (db()->inTransaction()) db()->rollBack(); } catch (Throwable $ignored) {}
        safe_log('settings update unavailable', $ex);
        $error = ($_POST['action'] ?? '') === 'test_email' ? admin_t('Testmeldingen kunne ikke sendes. Kontroller SMTP-innstillingene på serveren.') : admin_t('Kunne ikke lagre innstillingene.');
    }
}
admin_header(admin_t('Innstillinger'));
if ($error) admin_error($error);
?>
<form class="edit-form" method="post"><?= csrf_input() ?>
<?php foreach ($labels as $key => $label) admin_input($key, $label, $values[$key], $key === 'email' ? 'email' : (in_array($key, ['public_domain', 'social_url'], true) ? 'url' : 'text'), $key === 'home_seo_description' ? 300 : (in_array($key, ['public_domain', 'social_url'], true) ? 255 : 160), in_array($key, ['company_name', 'service_region', 'primary_cta'], true)); ?>
<label class="check"><input type="checkbox" name="seo_indexable" value="1" <?= $values['seo_indexable'] === '1' ? 'checked' : '' ?>><?= e(admin_t('Tillat indeksering på publisert HTTPS-domene')) ?></label>
<button type="submit"><?= e(admin_t('Lagre innstillinger')) ?></button></form>
<?php $mail = config(); ?>
<section aria-labelledby="delivery-heading">
<h2 id="delivery-heading"><?= e(admin_t('Levering av forespørsler')) ?></h2>
<dl class="request-detail">
<dt><?= e(admin_t('Lagrede forespørsler')) ?></dt><dd><a href="<?= e(url('admin/requests/')) ?>"><?= e(admin_t('Åpne forespørsler')) ?></a></dd>
<dt><?= e(admin_t('E-postmottaker')) ?></dt><dd><?= e($mail['recipient_email'] ?: admin_t('Ikke konfigurert')) ?></dd>
<dt><?= e(admin_t('Avsender')) ?></dt><dd><?= e($mail['from_email'] ?: admin_t('Ikke konfigurert')) ?></dd>
<dt><?= e(admin_t('Transport')) ?></dt><dd><?= $mail['smtp']['enabled'] ? 'SMTP' : 'PHP mail()' ?></dd>
</dl>
<?php if ($mailError = mail_configuration_error($mail)): ?><p class="notice error"><?= e(admin_t($mailError)) ?> <?= e(admin_t('Lagrede forespørsler må følges opp her i administrasjonen.')) ?></p><?php else: ?>
<form method="post"><?= csrf_input() ?><input type="hidden" name="action" value="test_email"><button type="submit"><?= e(admin_t('Send testmelding')) ?></button></form>
<?php endif ?>
</section>
<?php admin_footer(); ?>

<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/app/auth.php';
require_post_csrf();
$locale = $_POST['locale'] ?? null;
if (!is_string($locale) || !in_array($locale, ['uk', 'nb'], true)) {
    http_response_code(422);
    exit(admin_t('Ugyldig felt.'));
}
$return = $_POST['return'] ?? '';
if (!is_string($return) || !preg_match('~^(?:login\.php|(?:requests|reviews|services|categories|gallery|seo|settings|account)/)?(?:\?[^\r\n#]*)?$~D', $return)) $return = 'login.php';
$_SESSION['admin_locale'] = $locale;
redirect('admin/' . $return);

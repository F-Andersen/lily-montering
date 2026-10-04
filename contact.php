<?php
declare(strict_types=1);
require_once __DIR__ . '/app/content.php';
require_once __DIR__ . '/app/mail.php';
require_once __DIR__ . '/app/phone.php';
require_once __DIR__ . '/app/request-photos.php';
start_session();
security_headers();
header('X-Robots-Tag: noindex, nofollow');

function json_response(int $status, bool $ok, string $message, array $extra = []): never
{
    http_response_code($status);
    if (str_contains($_SERVER['HTTP_ACCEPT'] ?? '', 'application/json')) {
        header('Content-Type: application/json; charset=UTF-8');
        echo json_encode(array_merge(['ok' => $ok, 'message' => $message], $extra), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    } else {
        header('Content-Type: text/html; charset=UTF-8');
        echo '<!doctype html><html lang="nb"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="robots" content="noindex,nofollow"><title>Forespørsel | ' . e(settings()['company_name']) . '</title><link rel="stylesheet" href="' . e(url('styles.css')) . '"></head><body><main class="section"><h1>' . ($ok ? 'Takk for forespørselen' : 'Forespørselen kunne ikke sendes') . '</h1><p>' . e($message) . '</p>';
        foreach ($extra['errors'] ?? [] as $error) echo '<p>' . e($error) . '</p>';
        echo '<a class="button button-primary" href="' . e(url('#kontakt')) . '">Til kontaktskjemaet</a></main></body></html>';
    }
    exit;
}

function has_header_injection(string $value): bool
{
    return preg_match('/[\\r\\n]/', $value) === 1;
}

function read_field(string $key, int $maxLength, bool $required, array &$errors, bool $singleLine = true): string
{
    if (!is_string($_POST[$key] ?? '')) { $errors[$key] = 'Ugyldig felt.'; return ''; }
    $value = trim($_POST[$key] ?? '');
    if (($required && $value === '') || preg_match('//u', $value) !== 1 || str_contains($value, "\0") || mb_strlen($value, 'UTF-8') > $maxLength || ($singleLine && has_header_injection($value))) {
        $errors[$key] = 'Kontroller feltet.';
        return '';
    }
    return $value;
}

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') { header('Allow: POST'); json_response(405, false, 'Ugyldig metode.'); }
$contentType = strtolower($_SERVER['CONTENT_TYPE'] ?? '');
if (!str_starts_with($contentType, 'application/x-www-form-urlencoded') && !str_starts_with($contentType, 'multipart/form-data')) json_response(415, false, 'Ugyldig innholdstype.');
$bodyLimit = str_starts_with($contentType, 'multipart/form-data') ? REQUEST_BODY_BYTES : 20000;
if ((int)($_SERVER['CONTENT_LENGTH'] ?? 0) > $bodyLimit) json_response(413, false, 'Forespørselen er for stor. Velg høyst 5 bilder på inntil 5 MB hver.');
if (!csrf_valid()) json_response(403, false, 'Skjemaet er utløpt. Last inn siden på nytt.');
if (!empty($_POST['website'])) json_response(400, false, 'Kunne ikke sende forespørselen.');
$startedAt = is_scalar($_POST['started_at'] ?? null) ? (int)$_POST['started_at'] : 0;
$elapsed = ((int)floor(microtime(true) * 1000) - $startedAt) / 1000;
if ($elapsed < 4 || $elapsed > 86400) json_response(400, false, 'Last inn siden på nytt og prøv igjen.');
if (!rate_limit('contact', client_ip(), 5, 1800)) json_response(429, false, 'For mange forsøk på kort tid. Prøv igjen senere.');

$errors = [];
$fields = [
    'name' => read_field('name', 80, true, $errors),
    'phone' => read_field('phone', 32, true, $errors),
    'email' => read_field('email', 120, false, $errors),
    'service' => read_field('service', 160, true, $errors),
    'area' => read_field('area', 80, true, $errors),
    'message' => read_field('message', 1500, true, $errors, false),
];
if ($fields['email'] !== '' && !filter_var($fields['email'], FILTER_VALIDATE_EMAIL)) $errors['email'] = 'Skriv en gyldig e-postadresse.';
$phoneCountry = isset($_POST['phone_country']) ? read_field('phone_country', 5, true, $errors) : 'NO';
$phone = normalize_phone($fields['phone'], $phoneCountry);
if ($phone === null || isset($errors['phone_country'])) $errors['phone'] = 'Skriv et gyldig telefonnummer med riktig landskode.';
else $fields['phone'] = $phone;
if (!in_array($fields['service'], contact_services(), true)) $errors['service'] = 'Velg en gyldig tjeneste.';
if (mb_strlen($fields['message'], 'UTF-8') < 20) $errors['message'] = 'Skriv litt mer om oppdraget.';
if (($_POST['privacy'] ?? '') !== '1') $errors['privacy'] = 'Samtykke må bekreftes.';
$uploads = [];
try { $uploads = request_photo_uploads(); }
catch (InvalidArgumentException $error) { $errors['photos'] = $error->getMessage(); }
if ($errors) json_response(422, false, 'Kontroller feltene og prøv igjen.', ['errors' => $errors]);

$id = null;
$photos = [];
$pdo = null;
try {
    $photos = prepare_request_photos($uploads);
    $pdo = db();
    $pdo->beginTransaction();
    query('INSERT INTO contact_requests (name,phone,email,service,area,message) VALUES (?,?,?,?,?,?)', array_values($fields));
    $id = (int)db()->lastInsertId();
    foreach ($photos as $photo) query('INSERT INTO request_photos (request_id,filename,width,height,bytes) VALUES (?,?,?,?,?)', [$id, $photo['filename'], $photo['width'], $photo['height'], $photo['bytes']]);
    $pdo->commit();
} catch (Throwable $error) {
    try { if ($pdo && $pdo->inTransaction()) $pdo->rollBack(); } catch (Throwable $rollbackError) { safe_log('contact rollback unavailable', $rollbackError); }
    $id = null;
    remove_request_photo_files($photos);
    if ($error instanceof InvalidArgumentException) json_response(422, false, 'Kontroller bildene og prøv igjen.', ['errors' => ['photos' => $error->getMessage()]]);
    safe_log('contact storage failed', $error);
    if ($uploads) json_response(503, false, 'Kunne ikke lagre forespørselen med bilder. Prøv igjen senere.');
}

$sent = false;
try {
    $sent = $id ? deliver_request($id) : send_notification(config(), 'Ny forespørsel fra fiksitt-nettsiden', build_message($fields), $fields['email']);
    if (!$sent) throw new RuntimeException('Mail delivery failed');
} catch (Throwable $error) { safe_log('contact email failed', $error); }

if ($id || $sent) json_response(200, true, $sent ? 'Takk. Forespørselen er sendt.' : 'Takk. Forespørselen er registrert. Vi følger den opp.');
json_response(503, false, 'Kunne ikke registrere forespørselen nå. Prøv igjen senere eller bruk direkte kontakt.');

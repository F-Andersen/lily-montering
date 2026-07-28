<?php
declare(strict_types=1);

session_start();
header('Content-Type: application/json; charset=UTF-8');
header('X-Content-Type-Options: nosniff');

const MAX_MESSAGE_LENGTH = 1500;
const MIN_COMPLETION_SECONDS = 4;
const MAX_COMPLETION_SECONDS = 86400;
const RATE_LIMIT_WINDOW_SECONDS = 1800;
const RATE_LIMIT_MAX_REQUESTS = 5;

function json_response(int $status, bool $ok, string $message, array $extra = []): void
{
    http_response_code($status);
    echo json_encode(
        array_merge(['ok' => $ok, 'message' => $message], $extra),
        JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
    );
    exit;
}

function text_length(string $value): int
{
    return function_exists('mb_strlen') ? mb_strlen($value, 'UTF-8') : strlen($value);
}

function is_valid_utf8(string $value): bool
{
    return $value === '' || preg_match('//u', $value) === 1;
}

function has_header_injection(string $value): bool
{
    return preg_match('/[\r\n]/', $value) === 1;
}

function read_field(string $key, int $maxLength, bool $required, array &$errors, bool $singleLine = true): string
{
    $value = trim((string)($_POST[$key] ?? ''));
    $value = str_replace("\0", '', $value);

    if ($required && $value === '') {
        $errors[$key] = 'Feltet må fylles ut.';
        return '';
    }

    if (!is_valid_utf8($value)) {
        $errors[$key] = 'Feltet inneholder ugyldige tegn.';
        return '';
    }

    if ($singleLine && has_header_injection($value)) {
        $errors[$key] = 'Feltet inneholder ugyldige linjeskift.';
        return '';
    }

    if (text_length($value) > $maxLength) {
        $errors[$key] = 'Feltet er for langt.';
        return '';
    }

    return $value;
}

function client_ip(): string
{
    $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    return preg_replace('/[^a-fA-F0-9:\.]/', '', $ip) ?: 'unknown';
}

function check_rate_limit(): bool
{
    $dir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'lily-contact-rate';

    if (!is_dir($dir) && !mkdir($dir, 0700, true) && !is_dir($dir)) {
        return true;
    }

    $file = $dir . DIRECTORY_SEPARATOR . hash('sha256', client_ip()) . '.json';
    $now = time();
    $timestamps = [];

    if (is_file($file)) {
        $decoded = json_decode((string)file_get_contents($file), true);
        if (is_array($decoded)) {
            $timestamps = array_filter(
                array_map('intval', $decoded),
                static fn (int $timestamp): bool => $timestamp > ($now - RATE_LIMIT_WINDOW_SECONDS)
            );
        }
    }

    if (count($timestamps) >= RATE_LIMIT_MAX_REQUESTS) {
        return false;
    }

    $timestamps[] = $now;
    file_put_contents($file, json_encode(array_values($timestamps)), LOCK_EX);
    return true;
}

function encode_header(string $value): string
{
    if (function_exists('mb_encode_mimeheader')) {
        return mb_encode_mimeheader($value, 'UTF-8', 'B', "\r\n");
    }

    return '=?UTF-8?B?' . base64_encode($value) . '?=';
}

function format_address(string $email, string $name = ''): string
{
    if ($name === '') {
        return $email;
    }

    return encode_header($name) . ' <' . $email . '>';
}

function build_message(array $fields): string
{
    $lines = [
        'Ny forespørsel fra LILY Montering-nettsiden',
        '',
        'Navn: ' . $fields['name'],
        'Telefon: ' . $fields['phone'],
        'E-post: ' . ($fields['email'] !== '' ? $fields['email'] : 'Ikke oppgitt'),
        'Tjeneste: ' . $fields['service'],
        'By/område: ' . $fields['area'],
        '',
        'Beskrivelse:',
        $fields['message'],
        '',
        'Teknisk informasjon:',
        'IP: ' . client_ip(),
        'Tidspunkt: ' . gmdate('c'),
        'User-Agent: ' . substr((string)($_SERVER['HTTP_USER_AGENT'] ?? 'Ukjent'), 0, 180),
    ];

    return implode("\n", $lines);
}

function build_headers(array $config, string $subject, string $replyTo = '', bool $includeSubject = true): string
{
    $fromName = (string)($config['from_name'] ?? 'LILY Montering');
    $fromEmail = (string)($config['from_email'] ?? '');
    $recipient = (string)($config['recipient_email'] ?? '');

    $headers = [
        'Date: ' . date(DATE_RFC2822),
        'From: ' . format_address($fromEmail, $fromName),
        'To: ' . $recipient,
        'MIME-Version: 1.0',
        'Content-Type: text/plain; charset=UTF-8',
        'Content-Transfer-Encoding: 8bit',
        'X-Mailer: PHP/' . PHP_VERSION,
    ];

    if ($includeSubject) {
        array_splice($headers, 3, 0, 'Subject: ' . encode_header($subject));
    }

    if ($replyTo !== '') {
        $headers[] = 'Reply-To: ' . $replyTo;
    }

    return implode("\r\n", $headers);
}

function smtp_read_response($socket): array
{
    $response = '';

    while (($line = fgets($socket, 515)) !== false) {
        $response .= $line;
        if (strlen($line) < 4 || $line[3] !== '-') {
            break;
        }
    }

    return [(int)substr($response, 0, 3), $response];
}

function smtp_command($socket, ?string $command, array $expectedCodes): string
{
    if ($command !== null) {
        fwrite($socket, $command . "\r\n");
    }

    [$code, $response] = smtp_read_response($socket);

    if (!in_array($code, $expectedCodes, true)) {
        throw new RuntimeException('SMTP error: ' . trim($response));
    }

    return $response;
}

function smtp_send(array $config, string $subject, string $body, string $replyTo = ''): bool
{
    $smtp = $config['smtp'] ?? [];
    $host = (string)($smtp['host'] ?? '');
    $port = (int)($smtp['port'] ?? 587);
    $username = (string)($smtp['username'] ?? '');
    $password = (string)($smtp['password'] ?? '');
    $encryption = strtolower((string)($smtp['encryption'] ?? 'tls'));
    $timeout = (int)($smtp['timeout'] ?? 15);

    if ($host === '') {
        throw new RuntimeException('SMTP host is missing.');
    }

    $remote = $encryption === 'ssl' ? 'ssl://' . $host . ':' . $port : $host . ':' . $port;
    $socket = @stream_socket_client($remote, $errno, $errstr, $timeout, STREAM_CLIENT_CONNECT);

    if (!is_resource($socket)) {
        throw new RuntimeException('Could not connect to SMTP server: ' . $errstr);
    }

    stream_set_timeout($socket, $timeout);
    $serverName = preg_replace('/[^A-Za-z0-9\.\-]/', '', (string)($_SERVER['SERVER_NAME'] ?? 'localhost')) ?: 'localhost';

    try {
        smtp_command($socket, null, [220]);
        smtp_command($socket, 'EHLO ' . $serverName, [250]);

        if ($encryption === 'tls') {
            smtp_command($socket, 'STARTTLS', [220]);
            if (!stream_socket_enable_crypto($socket, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) {
                throw new RuntimeException('Could not enable TLS.');
            }
            smtp_command($socket, 'EHLO ' . $serverName, [250]);
        }

        if ($username !== '' || $password !== '') {
            smtp_command($socket, 'AUTH LOGIN', [334]);
            smtp_command($socket, base64_encode($username), [334]);
            smtp_command($socket, base64_encode($password), [235]);
        }

        $fromEmail = (string)$config['from_email'];
        $recipient = (string)$config['recipient_email'];
        $headers = build_headers($config, $subject, $replyTo);
        $payload = preg_replace('/^\./m', '..', $headers . "\r\n\r\n" . $body);

        smtp_command($socket, 'MAIL FROM:<' . $fromEmail . '>', [250]);
        smtp_command($socket, 'RCPT TO:<' . $recipient . '>', [250, 251]);
        smtp_command($socket, 'DATA', [354]);
        smtp_command($socket, $payload . "\r\n.", [250]);
        smtp_command($socket, 'QUIT', [221]);
    } finally {
        fclose($socket);
    }

    return true;
}

function native_mail_send(array $config, string $subject, string $body, string $replyTo = ''): bool
{
    $recipient = (string)$config['recipient_email'];
    $fromEmail = (string)$config['from_email'];
    $headers = build_headers($config, $subject, $replyTo, false);

    return mail($recipient, encode_header($subject), $body, $headers, '-f' . $fromEmail);
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_response(405, false, 'Ugyldig metode.');
}

$contentType = strtolower((string)($_SERVER['CONTENT_TYPE'] ?? ''));
$allowedContentTypes = ['application/x-www-form-urlencoded', 'multipart/form-data'];
$isAllowedContentType = false;

foreach ($allowedContentTypes as $allowedType) {
    if (str_starts_with($contentType, $allowedType)) {
        $isAllowedContentType = true;
        break;
    }
}

if (!$isAllowedContentType) {
    json_response(415, false, 'Ugyldig innholdstype.');
}

if (!empty($_POST['website'])) {
    json_response(400, false, 'Kunne ikke sende forespørselen.');
}

$startedAt = (int)($_POST['started_at'] ?? 0);
$elapsedSeconds = $startedAt > 0 ? ((int)floor(microtime(true) * 1000) - $startedAt) / 1000 : 0;

if ($elapsedSeconds < MIN_COMPLETION_SECONDS || $elapsedSeconds > MAX_COMPLETION_SECONDS) {
    json_response(400, false, 'Last inn siden på nytt og prøv igjen.');
}

if (!check_rate_limit()) {
    json_response(429, false, 'For mange forsøk på kort tid. Prøv igjen senere.');
}

$errors = [];
$allowedServices = [
    'Møbelmontering hjemme',
    'Kontor, skole eller næring',
    'Garderobe eller skyvedører',
    'Kjøkken eller avansert montering',
    'Henge opp TV, hyller eller speil',
    'Annet handyman-oppdrag',
];

$fields = [
    'name' => read_field('name', 80, true, $errors),
    'phone' => read_field('phone', 32, true, $errors),
    'email' => read_field('email', 120, false, $errors),
    'service' => read_field('service', 80, true, $errors),
    'area' => read_field('area', 80, true, $errors),
    'message' => read_field('message', MAX_MESSAGE_LENGTH, true, $errors, false),
];

if ($fields['email'] !== '' && !filter_var($fields['email'], FILTER_VALIDATE_EMAIL)) {
    $errors['email'] = 'Skriv en gyldig e-postadresse.';
}

if (!preg_match('/^[0-9\+\(\)\s\.\-]{6,32}$/', $fields['phone'])) {
    $errors['phone'] = 'Skriv et gyldig telefonnummer.';
}

if (!in_array($fields['service'], $allowedServices, true)) {
    $errors['service'] = 'Velg en gyldig tjeneste.';
}

if (text_length($fields['message']) < 20) {
    $errors['message'] = 'Skriv litt mer om oppdraget.';
}

if (($_POST['privacy'] ?? '') !== '1') {
    $errors['privacy'] = 'Samtykke må bekreftes.';
}

if ($errors !== []) {
    json_response(422, false, 'Kontroller feltene og prøv igjen.', ['errors' => $errors]);
}

$configPath = __DIR__ . DIRECTORY_SEPARATOR . 'config.php';

if (!is_file($configPath)) {
    json_response(503, false, 'Kontaktskjemaet er ikke konfigurert ennå. Bruk direkte kontakt når telefon eller e-post er lagt inn.');
}

$config = require $configPath;

if (!is_array($config)) {
    json_response(500, false, 'Ugyldig skjemaoppsett.');
}

$recipientEmail = (string)($config['recipient_email'] ?? '');
$fromEmail = (string)($config['from_email'] ?? '');

if (!filter_var($recipientEmail, FILTER_VALIDATE_EMAIL) || !filter_var($fromEmail, FILTER_VALIDATE_EMAIL)) {
    json_response(500, false, 'Kontaktskjemaet mangler gyldig e-postoppsett.');
}

foreach ([$recipientEmail, $fromEmail, (string)($config['from_name'] ?? '')] as $headerValue) {
    if (has_header_injection($headerValue)) {
        json_response(500, false, 'Kontaktskjemaet har ugyldig e-postoppsett.');
    }
}

$subject = 'Ny forespørsel fra LILY Montering-nettsiden';
$replyTo = $fields['email'] !== '' ? $fields['email'] : '';
$message = build_message($fields);

try {
    $smtpEnabled = (bool)($config['smtp']['enabled'] ?? false);
    $sent = $smtpEnabled
        ? smtp_send($config, $subject, $message, $replyTo)
        : native_mail_send($config, $subject, $message, $replyTo);

    if (!$sent) {
        throw new RuntimeException('Delivery failed.');
    }

    json_response(200, true, 'Takk. Forespørselen er sendt.');
} catch (Throwable $error) {
    error_log('LILY contact form error: ' . $error->getMessage());
    json_response(500, false, 'Kunne ikke sende forespørselen akkurat nå. Prøv direkte kontakt når den er lagt inn.');
}

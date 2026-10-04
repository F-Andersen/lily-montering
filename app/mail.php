<?php
declare(strict_types=1);

use PHPMailer\PHPMailer\PHPMailer;

require_once __DIR__ . '/vendor/phpmailer/src/Exception.php';
require_once __DIR__ . '/vendor/phpmailer/src/PHPMailer.php';
require_once __DIR__ . '/vendor/phpmailer/src/SMTP.php';

function build_message(array $fields, ?int $id = null): string
{
    $lines = [
        'Ny forespørsel fra fiksitt-nettsiden',
        $id ? 'Forespørsel #' . $id : '',
        '',
        'Navn: ' . $fields['name'],
        'Telefon: ' . $fields['phone'],
        'E-post: ' . ($fields['email'] !== '' ? $fields['email'] : 'Ikke oppgitt'),
        'Tjeneste: ' . $fields['service'],
        'By/område: ' . $fields['area'],
        'Bilder: ' . (int)($fields['photo_count'] ?? 0) . ' (se administrasjonen)',
        '',
        'Beskrivelse:',
        $fields['message'],
        '',
        'Tidspunkt (UTC): ' . ($fields['created_at'] ?? gmdate('c')),
    ];
    // The configured origin is trusted, never the inbound Host header.
    $origin = rtrim((string)(config()['app_url'] ?? ''), '/');
    if ($id && $origin !== '') $lines[] = 'Administrasjon: ' . admin_absolute_url('requests/?id=' . $id);
    return implode("\n", $lines);
}

function mail_configuration_error(array $config): string
{
    foreach (['recipient_email', 'from_email'] as $key) {
        $value = (string)($config[$key] ?? '');
        if (!filter_var($value, FILTER_VALIDATE_EMAIL) || preg_match('/[\r\n]/', $value)) return 'Mottaker og avsender må være gyldige e-postadresser.';
    }
    if (preg_match('/[\r\n]/', (string)($config['from_name'] ?? ''))) return 'Ugyldig avsendernavn.';
    if (empty($config['smtp']['enabled'])) return '';
    $smtp = $config['smtp'];
    $host = (string)($smtp['host'] ?? '');
    $port = (int)($smtp['port'] ?? 0);
    $encryption = (string)($smtp['encryption'] ?? 'tls');
    if (!preg_match('/^[A-Za-z0-9.-]+$/D', $host) || $port < 1 || $port > 65535 || !in_array($encryption, ['tls', 'ssl', 'none'], true)) return 'Kontroller SMTP-vert, port og kryptering.';
    $user = (string)($smtp['username'] ?? '');
    $password = (string)($smtp['password'] ?? '');
    if (($user === '') !== ($password === '')) return 'SMTP-bruker og passord må konfigureres sammen.';
    if ($encryption === 'none' && (($config['app_env'] ?? 'production') !== 'local' || $user !== '')) return 'SMTP krever TLS. Ukryptert transport er kun for lokal testpost uten innlogging.';
    return '';
}

function configured_mailer(array $config, string $subject, string $body, string $replyTo = ''): PHPMailer
{
    if ($error = mail_configuration_error($config)) throw new RuntimeException($error);
    if (preg_match('/[\r\n]/', $subject)) throw new InvalidArgumentException('Invalid mail subject');
    if ($replyTo !== '' && (!filter_var($replyTo, FILTER_VALIDATE_EMAIL) || preg_match('/[\r\n]/', $replyTo))) throw new InvalidArgumentException('Invalid reply address');
    $mail = new PHPMailer(true);
    $mail->CharSet = PHPMailer::CHARSET_UTF8;
    $mail->Encoding = PHPMailer::ENCODING_QUOTED_PRINTABLE;
    $mail->XMailer = 'fiksitt';
    $mail->setFrom($config['from_email'], $config['from_name'] ?? 'fiksitt');
    $mail->addAddress($config['recipient_email']);
    if ($replyTo !== '') $mail->addReplyTo($replyTo);
    $mail->Subject = $subject;
    $mail->Body = $body;
    $mail->isHTML(false);
    return $mail;
}

function smtp_send(array $config, string $subject, string $body, string $replyTo = ''): bool
{
    $mail = configured_mailer($config, $subject, $body, $replyTo);
    $smtp = $config['smtp'];
    $mail->isSMTP();
    $mail->Host = $smtp['host'];
    $mail->Port = (int)$smtp['port'];
    $mail->Timeout = max(1, min(30, (int)($smtp['timeout'] ?? 10)));
    $mail->getSMTPInstance()->Timelimit = $mail->Timeout;
    $mail->SMTPAuth = (string)($smtp['username'] ?? '') !== '';
    $mail->Username = $smtp['username'] ?? '';
    $mail->Password = $smtp['password'] ?? '';
    $mail->SMTPSecure = match ($smtp['encryption'] ?? 'tls') {
        'ssl' => PHPMailer::ENCRYPTION_SMTPS,
        'tls' => PHPMailer::ENCRYPTION_STARTTLS,
        default => '',
    };
    $mail->SMTPAutoTLS = ($smtp['encryption'] ?? 'tls') !== 'none';
    $mail->SMTPDebug = 0;
    return $mail->send();
}

function native_mail_send(array $config, string $subject, string $body, string $replyTo = ''): bool
{
    $mail = configured_mailer($config, $subject, $body, $replyTo);
    $mail->isMail();
    return $mail->send();
}

function send_notification(array $config, string $subject, string $body, string $replyTo = ''): bool
{
    return !empty($config['smtp']['enabled']) ? smtp_send($config, $subject, $body, $replyTo) : native_mail_send($config, $subject, $body, $replyTo);
}

function deliver_request(int $id): bool
{
    // Serialize initial delivery and admin retries, including across PHP workers.
    $lock = 'fiksitt-mail-' . substr(hash('sha256', config()['database']['name']), 0, 16) . '-' . $id;
    if ((int)query('SELECT GET_LOCK(?, 0)', [$lock])->fetchColumn() !== 1) throw new RuntimeException('Delivery is already in progress');
    try {
        $request = query('SELECT * FROM contact_requests WHERE id = ?', [$id])->fetch();
        if (!$request) throw new InvalidArgumentException('Forespørselen finnes ikke.');
        if ($request['email_sent']) return true;
        if ($request['status'] === 'spam') throw new InvalidArgumentException('Spam sendes ikke som e-post.');
        $request['photo_count'] = (int)query('SELECT COUNT(*) FROM request_photos WHERE request_id = ?', [$id])->fetchColumn();
        $sent = send_notification(config(), 'Ny forespørsel #' . $id . ' fra fiksitt', build_message($request, $id), $request['email']);
        if ($sent) {
            try { query('UPDATE contact_requests SET email_sent = 1 WHERE id = ?', [$id]); }
            catch (Throwable $error) { safe_log('delivery flag failed after SMTP acceptance; verify before retry', $error); }
        }
        return $sent;
    } finally {
        try { query('SELECT RELEASE_LOCK(?)', [$lock]); }
        catch (Throwable $error) { safe_log('delivery lock release failed', $error); }
    }
}

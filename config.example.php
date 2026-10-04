<?php
declare(strict_types=1);
// Copy to config.php on cPanel. Secrets must never be committed.
return [
    'app_env' => getenv('APP_ENV') ?: 'production',
    'app_url' => getenv('APP_URL') ?: '',
    'pretty_urls' => true,
    'admin_path' => getenv('ADMIN_PATH') ?: 'adfiksittmin',
    'setup_token' => getenv('SETUP_TOKEN') ?: '',
    'admin_allowed_ips' => array_values(array_filter(array_map('trim', explode(',', getenv('ADMIN_ALLOWED_IPS') ?: '')), static fn($ip) => $ip !== '')),
    'trusted_proxies' => array_values(array_filter(array_map('trim', explode(',', getenv('TRUSTED_PROXIES') ?: '')), static fn($ip) => $ip !== '')),
    'request_photo_dir' => getenv('REQUEST_PHOTO_DIR') ?: '/var/lib/fiksitt/request-photos',
    'database' => [
        'host' => getenv('DB_HOST') ?: '',
        'port' => (int)(getenv('DB_PORT') ?: 3306),
        'name' => getenv('DB_NAME') ?: '',
        'user' => getenv('DB_USER') ?: '',
        'password' => getenv('DB_PASSWORD') ?: '',
    ],
    'business_name' => 'fiksitt',
    'recipient_email' => getenv('MAIL_RECIPIENT') ?: '',
    'from_email' => getenv('MAIL_FROM') ?: '',
    'from_name' => 'fiksitt nettside',
    'smtp' => [
        'enabled' => (bool)getenv('SMTP_HOST'),
        'host' => getenv('SMTP_HOST') ?: '',
        'port' => (int)(getenv('SMTP_PORT') ?: 587),
        'username' => getenv('SMTP_USER') ?: '',
        'password' => getenv('SMTP_PASSWORD') ?: '',
        'encryption' => getenv('SMTP_ENCRYPTION') ?: 'tls',
        'timeout' => 10,
    ],
];

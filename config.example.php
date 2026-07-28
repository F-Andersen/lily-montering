<?php
declare(strict_types=1);

/*
 * Copy this file to config.php on the production server.
 * Do not commit config.php. Keep SMTP passwords only on the server.
 */
return [
    'business_name' => 'LILY Montering',
    'recipient_email' => 'kontakt@example.com',
    'from_email' => 'no-reply@example.com',
    'from_name' => 'LILY Montering nettside',

    'smtp' => [
        'enabled' => false,
        'host' => 'smtp.example.com',
        'port' => 587,
        'username' => '',
        'password' => '',
        'encryption' => 'tls', // tls, ssl, or none
        'timeout' => 15,
    ],
];

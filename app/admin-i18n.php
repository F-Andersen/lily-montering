<?php
declare(strict_types=1);
require_once __DIR__ . '/bootstrap.php';

function admin_locale(): string
{
    start_session();
    return in_array($_SESSION['admin_locale'] ?? '', ['uk', 'nb'], true) ? $_SESSION['admin_locale'] : 'nb';
}

function admin_translations(): array
{
    static $messages;
    return $messages ??= require __DIR__ . '/locales/admin-uk.php';
}

function admin_t(string $text): string
{
    if (admin_locale() !== 'uk') return $text;
    $messages = admin_translations();
    if (isset($messages[$text])) return $messages[$text];
    if (preg_match('/^Kontroller feltet: ([a-z_]+)\.$/D', $text, $match)) return 'Перевірте поле: ' . $match[1] . '.';
    return $text;
}

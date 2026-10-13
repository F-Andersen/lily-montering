<?php
declare(strict_types=1);
require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/admin-i18n.php';

function require_admin_access(): void
{
    if (PHP_SAPI !== 'cli') {
        $path = parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH) ?: '';
        $prefix = app_path() . '/' . admin_path();
        if ($path !== $prefix && !str_starts_with($path, $prefix . '/')) {
            http_response_code(404);
            header('X-Robots-Tag: noindex, nofollow');
            exit('Not found');
        }
    }
    $allowed = config()['admin_allowed_ips'] ?? [];
    if (!$allowed || in_array($_SERVER['REMOTE_ADDR'] ?? '', $allowed, true)) return;
    http_response_code(403);
    security_headers();
    header('X-Robots-Tag: noindex, nofollow');
    header('Content-Type: text/plain; charset=UTF-8');
    exit(admin_t('Administrasjon er ikke tilgjengelig fra denne tilkoblingen.'));
}

require_admin_access();

function refresh_admin_hash(array $admin, string $password): array|false
{
    if (!password_needs_rehash($admin['password_hash'], PASSWORD_DEFAULT)) return $admin;
    $hash = password_hash($password, PASSWORD_DEFAULT);
    // Upgrade only the exact credential version that this login verified.
    $updated = query('UPDATE admins SET password_hash = ? WHERE id = ? AND active = 1 AND CAST(password_hash AS BINARY) = CAST(? AS BINARY)', [$hash, $admin['id'], $admin['password_hash']]);
    if ($updated->rowCount() !== 1) return false;
    $admin['password_hash'] = $hash;
    return $admin;
}

function require_admin(): array
{
    start_session();
    if (empty($_SESSION['admin_id']) || time() - (int)($_SESSION['last_activity'] ?? 0) > 1800) {
        unset($_SESSION['admin_id'], $_SESSION['last_activity']);
        redirect('admin/login.php');
    }
    try {
        $admin = query('SELECT id, email, password_hash FROM admins WHERE id = ? AND active = 1', [$_SESSION['admin_id']])->fetch();
        if (!$admin || !hash_equals(hash('sha256', $admin['password_hash']), (string)($_SESSION['auth_version'] ?? ''))) {
            unset($_SESSION['admin_id'], $_SESSION['last_activity'], $_SESSION['auth_version']);
            redirect('admin/login.php');
        }
    } catch (Throwable $error) {
        safe_log('admin database unavailable', $error);
        http_response_code(503);
        security_headers();
        exit(admin_t('Administrasjonen er midlertidig utilgjengelig. Prøv igjen senere.'));
    }
    $_SESSION['last_activity'] = time();
    unset($admin['password_hash']);
    return $admin;
}

function require_post_csrf(): void
{
    if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
        http_response_code(405);
        exit(admin_t('Ugyldig metode.'));
    }
    if (!csrf_valid()) {
        http_response_code(403);
        exit(admin_t('Skjemaet er utløpt. Last inn siden på nytt.'));
    }
}

function flash(string $message): void
{
    start_session();
    $_SESSION['flash'] = $message;
}

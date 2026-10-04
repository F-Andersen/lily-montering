<?php
declare(strict_types=1);

function config(): array
{
    static $config;
    if ($config !== null) return $config;
    $config = require dirname(__DIR__) . '/config.example.php';
    $path = dirname(__DIR__) . '/config.php';
    if (is_file($path)) $config = array_replace_recursive($config, require $path);
    return $config;
}

function db(): PDO
{
    static $pdo, $failure;
    if ($pdo instanceof PDO) return $pdo;
    if ($failure instanceof Throwable) throw $failure;
    $c = config()['database'];
    if ($c['host'] === '' || $c['name'] === '' || $c['user'] === '') throw new RuntimeException('Database is not configured');
    try {
        $pdo = new PDO('mysql:host=' . $c['host'] . ';port=' . (int)$c['port'] . ';dbname=' . $c['name'] . ';charset=utf8mb4', $c['user'], $c['password'], [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
        PDO::ATTR_TIMEOUT => 3,
        ]);
    } catch (Throwable $error) { $failure = $error; throw $error; }
    return $pdo;
}

function query(string $sql, array $params = []): PDOStatement
{
    $stmt = db()->prepare($sql);
    $stmt->execute($params);
    return $stmt;
}

function safe_log(string $event, Throwable $error): void
{
    error_log('LILY ' . $event . ': ' . get_class($error) . ' code=' . $error->getCode());
}

function e(mixed $value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function https(): bool
{
    if (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') return true;
    // Only the explicitly trusted ingress may supply the original scheme.
    return in_array($_SERVER['REMOTE_ADDR'] ?? '', config()['trusted_proxies'] ?? [], true)
        && ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https';
}

function client_ip(): string
{
    $remote = (string)($_SERVER['REMOTE_ADDR'] ?? '');
    $forwarded = $_SERVER['HTTP_X_REAL_IP'] ?? '';
    return in_array($remote, config()['trusted_proxies'] ?? [], true)
        && is_string($forwarded) && filter_var($forwarded, FILTER_VALIDATE_IP)
        ? $forwarded : $remote;
}

function start_session(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) return;
    ini_set('session.use_strict_mode', '1');
    ini_set('session.use_only_cookies', '1');
    session_name('lily_session');
    session_set_cookie_params(['lifetime' => 0, 'path' => app_path() . '/', 'secure' => https(), 'httponly' => true, 'samesite' => 'Lax']);
    session_start();
}

function csrf(): string
{
    start_session();
    return $_SESSION['csrf'] ??= bin2hex(random_bytes(32));
}

function csrf_input(): string
{
    return '<input type="hidden" name="csrf" value="' . e(csrf()) . '">';
}

function csrf_valid(): bool
{
    start_session();
    return is_string($_POST['csrf'] ?? null) && hash_equals(csrf(), $_POST['csrf']);
}

function app_path(): string
{
    return rtrim((string)(parse_url(config()['app_url'], PHP_URL_PATH) ?? ''), '/');
}

function url(string $path = ''): string
{
    $path = ltrim($path, '/');
    if ($path === 'admin' || str_starts_with($path, 'admin/')) $path = admin_path() . substr($path, 5);
    return app_path() . '/' . ltrim($path, '/');
}

function admin_path(): string
{
    $path = (string)(config()['admin_path'] ?? 'adfiksittmin');
    if (!preg_match('/^[a-z][a-z0-9-]{4,63}$/D', $path) || in_array($path, ['assets','app','tools','database','tests','tjenester'], true)) throw new RuntimeException('Invalid administration path');
    if ($path === 'admin' && config()['app_env'] !== 'local') throw new RuntimeException('Production administration requires a custom path');
    return $path;
}

function admin_absolute_url(string $path = ''): string
{
    return rtrim(config()['app_url'], '/') . '/' . admin_path() . '/' . ltrim($path, '/');
}

function redirect(string $path): never
{
    header('Location: ' . url($path), true, 303);
    exit;
}

function settings(): array
{
    static $values;
    if ($values !== null) return $values;
    $values = ['company_name' => 'fiksitt', 'slogan' => 'Presis montering. Ryddig levert.', 'service_region' => 'Østlandet', 'primary_cta' => 'Be om tilbud', 'phone' => '', 'email' => '', 'public_domain' => '', 'organization_number' => '', 'social_url' => ''];
    $values += ['seo_indexable' => '0', 'home_seo_title' => '', 'home_seo_description' => ''];
    try {
        foreach (query('SELECT setting_key, setting_value FROM site_settings')->fetchAll() as $row) $values[$row['setting_key']] = $row['setting_value'];
    } catch (Throwable $error) { safe_log('settings unavailable', $error); }
    return $values;
}

function absolute_url(string $path = ''): string
{
    $base = settings()['public_domain'] ?: config()['app_url'];
    return $base === '' ? '' : rtrim($base, '/') . '/' . ltrim($path, '/');
}

function service_url(string $slug = ''): string
{
    if (!config()['pretty_urls']) return url('tjenester.php') . ($slug === '' ? '' : '?slug=' . rawurlencode($slug));
    return url('tjenester' . ($slug === '' ? '' : '/' . rawurlencode($slug)));
}

function slugify(string $title): string
{
    $title = strtr(mb_strtolower($title, 'UTF-8'), ['æ' => 'ae', 'ø' => 'o', 'å' => 'a']);
    $ascii = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $title);
    return trim((string)preg_replace('/[^a-z0-9]+/', '-', strtolower($ascii ?: $title)), '-');
}

function image_path(string $path): string
{
    return preg_match('~^assets/(?:images|uploads)/[a-zA-Z0-9_-]+\\.(?:jpe?g|png|webp)$~D', $path) ? $path : '';
}

function image_dimensions(string $path): array|false
{
    static $cache = [];
    if (!array_key_exists($path, $cache)) {
        $file = dirname(__DIR__) . '/' . image_path($path);
        $cache[$path] = image_path($path) !== '' && is_file($file) ? @getimagesize($file) : false;
    }
    return $cache[$path];
}

function preferred_image_path(string $path): string
{
    $path = image_path($path);
    if ($path === '') return '';
    $webp = preg_replace('/\.(?:jpe?g|png)$/i', '.webp', $path);
    return image_dimensions($webp) ? $webp : $path;
}

function image_html(string $path, string $alt, string $class = '', bool $lazy = true, string $sizes = '(max-width: 760px) 100vw, 480px'): string
{
    $path = preferred_image_path($path);
    if ($path === '') return '';
    $size = image_dimensions($path);
    if (!$size) return '';
    $variants = [];
    foreach ([320, 900] as $width) {
        $webp = preg_replace('/\\.(?:jpe?g|png|webp)$/i', '-' . $width . '.webp', $path);
        if ($webp !== $path && ($dimensions = image_dimensions($webp))) $variants[$dimensions[0]] = e(url($webp)) . ' ' . $dimensions[0] . 'w';
    }
    if (str_ends_with($path, '.webp')) $variants[$size[0]] = e(url($path)) . ' ' . $size[0] . 'w';
    ksort($variants);
    $source = $variants ? '<source type="image/webp" srcset="' . implode(', ', $variants) . '" sizes="' . e($sizes) . '">' : '';
    return '<picture>' . $source . '<img class="' . e($class) . '" src="' . e(url($path)) . '" width="' . $size[0] . '" height="' . $size[1] . '" alt="' . e($alt) . '" decoding="async"' . ($lazy ? ' loading="lazy"' : '') . '></picture>';
}

function rate_limit(string $scope, string $identity, int $max, int $window): bool
{
    $dir = sys_get_temp_dir() . '/lily-limits-' . substr(hash('sha256', dirname(__DIR__)), 0, 12);
    if (!is_dir($dir) && !@mkdir($dir, 0700, true) && !is_dir($dir)) return false;
    $handle = @fopen($dir . '/' . hash('sha256', $scope . ':' . $identity) . '.json', 'c+');
    if (!$handle) return false;
    try {
        if (!flock($handle, LOCK_EX)) return false;
        $state = json_decode(stream_get_contents($handle), true);
        if (!is_array($state) || ($state['expires'] ?? 0) <= time()) $state = ['expires' => time() + $window, 'count' => 0];
        $allowed = $state['count'] < $max;
        if ($allowed) $state['count']++;
        rewind($handle);
        ftruncate($handle, 0);
        fwrite($handle, json_encode($state));
        return $allowed;
    } finally { fclose($handle); }
}

function security_headers(?array $schema = null, bool $photoPreviews = false): string
{
    $json = $schema ? json_encode($schema, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) : '';
    $hash = $json !== '' ? " 'sha256-" . base64_encode(hash('sha256', $json, true)) . "'" : '';
    $images = $photoPreviews ? "'self' data: blob:" : "'self' data:";
    header("Content-Security-Policy: default-src 'self'; base-uri 'self'; object-src 'none'; frame-ancestors 'self'; form-action 'self'; connect-src 'self'; img-src " . $images . "; style-src 'self'; script-src 'self'" . $hash . "; manifest-src 'self'");
    header('X-Content-Type-Options: nosniff');
    header('Referrer-Policy: strict-origin-when-cross-origin');
    header('Permissions-Policy: camera=(), microphone=(), geolocation=()');
    header('Cache-Control: no-store');
    return $json;
}

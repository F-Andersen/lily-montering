<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
putenv('TRUSTED_PROXIES=192.0.2.10');
require dirname(__DIR__) . '/app/bootstrap.php';
$cases = [
    ['native TLS', ['HTTPS' => 'on'], true],
    ['plain HTTP', ['HTTPS' => 'off'], false],
    ['untrusted spoof', ['REMOTE_ADDR' => '192.0.2.11', 'HTTP_X_FORWARDED_PROTO' => 'https'], false],
    ['trusted TLS', ['REMOTE_ADDR' => '192.0.2.10', 'HTTP_X_FORWARDED_PROTO' => 'https'], true],
    ['trusted HTTP', ['REMOTE_ADDR' => '192.0.2.10', 'HTTP_X_FORWARDED_PROTO' => 'http'], false],
    ['ambiguous scheme', ['REMOTE_ADDR' => '192.0.2.10', 'HTTP_X_FORWARDED_PROTO' => 'https,http'], false],
    ['no remote address', ['HTTP_X_FORWARDED_PROTO' => 'https'], false],
];
foreach ($cases as [$name, $server, $expected]) {
    $_SERVER = $server;
    if (https() !== $expected) { fwrite(STDERR, "FAIL: $name\n"); exit(1); }
    echo "PASS: $name\n";
}
$ipCases = [
    [['REMOTE_ADDR' => '192.0.2.10', 'HTTP_X_REAL_IP' => '198.51.100.8'], '198.51.100.8'],
    [['REMOTE_ADDR' => '192.0.2.11', 'HTTP_X_REAL_IP' => '198.51.100.8'], '192.0.2.11'],
    [['REMOTE_ADDR' => '192.0.2.10', 'HTTP_X_REAL_IP' => '198.51.100.8, 127.0.0.1'], '192.0.2.10'],
    [['REMOTE_ADDR' => '192.0.2.10', 'HTTP_X_REAL_IP' => ['198.51.100.8']], '192.0.2.10'],
];
foreach ($ipCases as [$server, $expected]) {
    $_SERVER = $server;
    if (client_ip() !== $expected) { fwrite(STDERR, "FAIL: proxy client IP\n"); exit(1); }
}
echo "PASS: trusted proxy client IP only\n";

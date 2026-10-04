<?php
declare(strict_types=1);
// Development only; run from CLI, never publish tests on shared hosting.
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
$root = dirname(__DIR__);
$iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS));
$failed = false;
foreach ($iterator as $file) {
    if ($file->getExtension() !== 'php' || str_contains($file->getPathname(), DIRECTORY_SEPARATOR . 'foto' . DIRECTORY_SEPARATOR)) continue;
    passthru(escapeshellarg(PHP_BINARY) . ' -l ' . escapeshellarg($file->getPathname()), $code);
    $failed = $failed || $code !== 0;
}
exit($failed ? 1 : 0);

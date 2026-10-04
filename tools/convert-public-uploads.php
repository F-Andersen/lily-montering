<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') exit(1);
require dirname(__DIR__) . '/app/uploads.php';
$apply = ($argv[1] ?? '') === '--apply';
$directory = dirname(__DIR__) . '/assets/uploads';
$count = 0;
foreach (glob($directory . '/*') ?: [] as $source) {
    if (!preg_match('/^[a-f0-9]{40}\.(?:jpg|png)$/D', basename($source))) continue;
    $target = preg_replace('/\.(?:jpg|png)$/', '.webp', $source);
    if (is_file($target) && @getimagesize($target)) continue;
    $mime = (new finfo(FILEINFO_MIME_TYPE))->file($source);
    if (!in_array($mime, ['image/jpeg', 'image/png'], true)) throw new RuntimeException('Invalid legacy image');
    if ($apply) encode_public_image($source, $mime, $target);
    $count++;
}
echo ($apply ? 'Converted ' : 'Dry run: ') . $count . " legacy public uploads. DB paths and originals preserved; private enquiry photos untouched.\n";

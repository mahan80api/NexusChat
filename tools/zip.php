<?php
/**
 * NexusChat - Create ZIP package
 *
 * اجرا: php tools/zip.php
 * خروجی: NexusChat-v1.0.0.zip در ریشه پروژه
 */

$root = dirname(__DIR__);
$name = 'NexusChat-v' . trim(file_get_contents($root . '/.version') ?: '1.0.0') . '.zip';
$out = $root . '/' . $name;

if (is_file($out)) unlink($out);

$zip = new ZipArchive();
if ($zip->open($out, ZipArchive::CREATE) !== true) {
    die("Cannot create ZIP\n");
}

$exclude = [
    '.git', '.github', 'node_modules', 'vendor', '.vscode', '.idea',
    'logs/*.log', 'uploads/avatars/*', 'uploads/images/*', 'uploads/videos/*',
    'uploads/voice/*', 'uploads/files/*', 'uploads/stickers/*',
    '*.zip', '*.tar.gz', '*.bak', '*.orig', 'Thumbs.db', '.DS_Store',
    'config/config.php', 'config/database.php', '.env',
];

$iterator = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator($root, RecursiveDirectoryIterator::SKIP_DOTS),
    RecursiveIteratorIterator::SELF_FIRST
);

$count = 0;
foreach ($iterator as $file) {
    $path = $file->getPathname();
    $rel = substr($path, strlen($root) + 1);
    $rel = str_replace('\\', '/', $rel);

    $skip = false;
    foreach ($exclude as $ex) {
        if (fnmatch($ex, $rel) || fnmatch($ex, basename($rel))) {
            $skip = true; break;
        }
    }
    if ($skip) continue;

    if ($file->isDir()) {
        $zip->addEmptyDir($rel);
    } else {
        $zip->addFile($path, $rel);
        $count++;
    }
}

$zip->close();

$size = round(filesize($out) / 1024 / 1024, 2);
echo "✓ Created $name ($size MB, $count files)\n";
echo "Location: $out\n";

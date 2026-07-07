<?php
/**
 * NexusChat - Project Statistics
 *
 * اجرا: php tools/stats.php
 */

$root = dirname(__DIR__);

function scan($dir, $ext) {
    $r = ['files' => 0, 'lines' => 0, 'size' => 0];
    if (!is_dir($dir)) return $r;
    $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir, RecursiveDirectoryIterator::SKIP_DOTS));
    foreach ($it as $f) {
        if (!$f->isFile()) continue;
        if (substr($f, -strlen($ext)) !== $ext) continue;
        if (strpos($f, '.git') !== false) continue;
        $r['files']++;
        $r['size'] += $f->getSize();
        $r['lines'] += count(file($f->getPathname()));
    }
    return $r;
}

$php = scan($root, '.php');
$js = scan($root . '/assets/js', '.js');
$css = scan($root . '/assets/css', '.css');
$sql = scan($root . '/db', '.sql');
$md = scan($root, '.md');

echo "✨ NexusChat - Project Statistics\n";
echo "══════════════════════════════════════\n\n";

$total = ['files' => 0, 'lines' => 0, 'size' => 0];
foreach (['PHP' => $php, 'JS' => $js, 'CSS' => $css, 'SQL' => $sql, 'MD' => $md] as $name => $s) {
    $total['files'] += $s['files'];
    $total['lines'] += $s['lines'];
    $total['size'] += $s['size'];
    printf("%-6s %4d files  %6d lines  %s KB\n", $name, $s['files'], $s['lines'], number_format($s['size']/1024, 1));
}

echo "──────────────────────────────────────\n";
printf("TOTAL  %4d files  %6d lines  %s MB\n", $total['files'], $total['lines'], number_format($total['size']/1024/1024, 2));

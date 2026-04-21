<?php

declare(strict_types=1);

/**
 * Fail if tracked paths collide when compared case-insensitively (breaks Linux checkouts).
 *
 * Run from repo root: php vendor/bin/check-git-path-case-collisions.php
 */

$root = getcwd();
if (!is_string($root) || $root === '') {
    fwrite(STDERR, "Could not determine current working directory.\n");
    exit(2);
}

$proc = proc_open(
    ['git', 'ls-files', '-z'],
    [1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
    $pipes,
    $root,
);
if (!is_resource($proc)) {
    fwrite(STDERR, "Could not run git ls-files.\n");
    exit(2);
}
$stdout = stream_get_contents($pipes[1]);
$stderr = stream_get_contents($pipes[2]);
foreach ($pipes as $pipe) {
    fclose($pipe);
}
$code = proc_close($proc);
if ($code !== 0) {
    fwrite(STDERR, "git ls-files failed (exit {$code}).\n");
    if (is_string($stderr) && $stderr !== '') {
        fwrite(STDERR, $stderr);
    }
    exit(2);
}
if (!is_string($stdout)) {
    fwrite(STDERR, "Could not read git ls-files output.\n");
    exit(2);
}

$paths = explode("\0", $stdout);
$byLower = [];
foreach ($paths as $path) {
    if ($path === '') {
        continue;
    }
    $key = strtolower(str_replace('\\', '/', $path));
    $byLower[$key] ??= [];
    $byLower[$key][] = $path;
}

$collisions = [];
foreach ($byLower as $key => $group) {
    $unique = array_values(array_unique($group));
    if (count($unique) > 1) {
        $collisions[$key] = $unique;
    }
}

if ($collisions === []) {
    echo "No case-only path collisions among tracked files.\n";
    exit(0);
}

fwrite(STDERR, "Case-insensitive path collisions (would break on case-sensitive filesystems):\n");
foreach ($collisions as $pathsList) {
    foreach ($pathsList as $p) {
        fwrite(STDERR, "  - {$p}\n");
    }
    fwrite(STDERR, "\n");
}
exit(1);

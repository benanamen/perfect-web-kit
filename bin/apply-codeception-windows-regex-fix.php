<?php

declare(strict_types=1);

/**
 * Codeception 5.3 builds preg_match patterns from DIRECTORY_SEPARATOR. On Windows,
 * "tests\Functional" yields a regex where "\F" is invalid under PCRE2 (PHP 8.5+).
 *
 * This script normalizes the in-tree Codeception files to use forward slashes via
 * preg_quote so `codecept run functional` works on Windows. It is idempotent.
 *
 * Run from a repo that has codeception installed in vendor/:
 *   php vendor/bin/apply-codeception-windows-regex-fix.php
 */

$root = getcwd();
if (!is_string($root) || $root === '') {
    fwrite(STDERR, "Could not determine current working directory.\n");
    exit(2);
}

$runFile = $root . '/vendor/codeception/codeception/src/Codeception/Command/Run.php';
if (is_file($runFile)) {
    $content = file_get_contents($runFile);
    if (!is_string($content)) {
        fwrite(STDERR, 'Failed to read ' . $runFile . "\n");
        exit(2);
    }
    $needle = '                if (preg_match("#^{$testsPath}/(.*?)$#", $suite, $matches)) {';
    $replace = <<<'PHP'
                $testsPathNorm = str_replace(['\\', '\/'], '/', $testsPath);
                $suiteNorm = str_replace(['\\', '\/'], '/', $suite);
                if (preg_match('#^' . preg_quote($testsPathNorm, '#') . '/(.*)$#', $suiteNorm, $matches)) {
PHP;
    if (str_contains($content, $needle) && !str_contains($content, '$testsPathNorm')) {
        $written = file_put_contents($runFile, str_replace($needle, $replace, $content));
        if ($written === false) {
            fwrite(STDERR, 'Failed to patch ' . $runFile . "\n");
            exit(2);
        }
    }
}

$dryRunFile = $root . '/vendor/codeception/codeception/src/Codeception/Command/DryRun.php';
if (is_file($dryRunFile)) {
    $content = file_get_contents($dryRunFile);
    if (!is_string($content)) {
        fwrite(STDERR, 'Failed to read ' . $dryRunFile . "\n");
        exit(2);
    }
    $needle = "        \$filename = str_replace(['//', '\\/', '\\\\'], '/', \$filename);\n        \$res = preg_match(\"#^{\$testsPath}/(.*?)/(.*)\$#\", \$filename, \$matches);";
    $replace = "        \$filename = str_replace(['//', '\\/', '\\\\'], '/', \$filename);\n        \$testsPath = str_replace(['//', '\\/', '\\\\'], '/', \$testsPath);\n        \$res = preg_match(\"#^{\$testsPath}/(.*?)/(.*)\$#\", \$filename, \$matches);";
    if (str_contains($content, $needle) && !str_contains($content, "\$testsPath = str_replace(['//', '\\/', '\\\\'], '/', \$testsPath);")) {
        $written = file_put_contents($dryRunFile, str_replace($needle, $replace, $content));
        if ($written === false) {
            fwrite(STDERR, 'Failed to patch ' . $dryRunFile . "\n");
            exit(2);
        }
    }
}

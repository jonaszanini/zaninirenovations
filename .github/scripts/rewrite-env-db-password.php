#!/usr/bin/env php
<?php

/**
 * Replace or append DB_PASSWORD in a Laravel .env file.
 *
 * Reads the raw password from stdin (never argv). Writes a single line:
 *   DB_PASSWORD="..."
 * with backslash, double-quote, and dollar signs escaped for dotenv.
 */
if ($argc < 2) {
    fwrite(STDERR, "usage: rewrite-env-db-password.php <env-file>\n");
    exit(2);
}

$path = $argv[1];
$pw = stream_get_contents(STDIN);
if ($pw === false) {
    fwrite(STDERR, "ERROR: failed to read password from stdin.\n");
    exit(1);
}
if ($pw === '') {
    fwrite(STDERR, "ERROR: NEW_DB_PASSWORD is empty.\n");
    exit(1);
}
if (strpbrk($pw, "\r\n") !== false) {
    fwrite(STDERR, "ERROR: NEW_DB_PASSWORD must not contain newlines.\n");
    exit(1);
}
if (! is_file($path) || ! is_readable($path)) {
    fwrite(STDERR, "ERROR: env file is missing or unreadable.\n");
    exit(1);
}

$escaped = str_replace(
    ['\\', '"', '$'],
    ['\\\\', '\\"', '\\$'],
    $pw
);
$newLine = 'DB_PASSWORD="'.$escaped.'"';

$contents = file_get_contents($path);
if ($contents === false) {
    fwrite(STDERR, "ERROR: could not read env file.\n");
    exit(1);
}

$count = 0;
$updated = preg_replace_callback(
    '/^DB_PASSWORD=.*$/m',
    static function () use ($newLine, &$count) {
        $count++;

        return $newLine;
    },
    $contents,
    1
);
if (! is_string($updated)) {
    fwrite(STDERR, "ERROR: failed to rewrite DB_PASSWORD line.\n");
    exit(1);
}
if ($count === 0) {
    if ($contents !== '' && substr($contents, -1) !== "\n") {
        $contents .= "\n";
    }
    $updated = $contents.$newLine."\n";
}

$dir = dirname($path);
$tmp = tempnam($dir, '.env.rotate.');
if ($tmp === false) {
    fwrite(STDERR, "ERROR: could not create env temp file.\n");
    exit(1);
}

if (file_put_contents($tmp, $updated) === false) {
    @unlink($tmp);
    fwrite(STDERR, "ERROR: could not write env temp file.\n");
    exit(1);
}

if (! rename($tmp, $path)) {
    @unlink($tmp);
    fwrite(STDERR, "ERROR: could not replace env file.\n");
    exit(1);
}

<?php

$root = dirname(__DIR__);
$dir = $root.'/resources/views/admin';
$fixed = 0;

$iterator = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS)
);

foreach ($iterator as $file) {
    if (! str_ends_with($file->getFilename(), '.blade.php')) {
        continue;
    }

    $path = $file->getPathname();
    $content = file_get_contents($path);
    $original = $content;

    $content = preg_replace_callback(
        '/<input\b([\s\S]*?)\bclass="admin-input"([\s\S]*?)\bclass="@error\(\'([^\']+)\'\)\s+is-invalid\s+@enderror"([\s\S]*?)>/',
        static function (array $m): string {
            $field = $m[3];

            return '<input'.$m[1].'@class([\'admin-input\', \'is-invalid\' => $errors->has(\''.$field.'\')])'.$m[2].$m[4].'>';
        },
        $content
    );

    if ($content !== $original) {
        file_put_contents($path, $content);
        $fixed++;
        echo str_replace($root.DIRECTORY_SEPARATOR, '', $path).PHP_EOL;
    }
}

echo PHP_EOL."Fixed {$fixed} files.".PHP_EOL;

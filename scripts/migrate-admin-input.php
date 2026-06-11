<?php

/**
 * One-time migrator: add class="admin-input" to admin form controls that lack it.
 * Skips checkboxes, radios, hidden fields, and submit/button inputs.
 */

$root = dirname(__DIR__);
$dir = $root.'/resources/views/admin';

$skipInputTypes = ['checkbox', 'radio', 'hidden', 'submit', 'button', 'image'];
$changedFiles = 0;
$changedTags = 0;

$iterator = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS)
);

foreach ($iterator as $file) {
    if ($file->getExtension() !== 'php' || ! str_ends_with($file->getFilename(), '.blade.php')) {
        continue;
    }

    $path = $file->getPathname();
    $content = file_get_contents($path);
    $original = $content;

    $content = preg_replace_callback(
        '/@class\(\[((?:[^\[\]]|\'[^\']*\'|"[^"]*")*)\]\)/',
        static function (array $m): string {
            $inner = $m[1];
            if (str_contains($inner, 'admin-input') || str_contains($inner, 'admin-checkbox')) {
                return $m[0];
            }

            return "@class(['admin-input', {$inner}])";
        },
        $content
    );

    $content = preg_replace_callback(
        '/<(input|select|textarea)\b([^>]*)>/is',
        static function (array $m) use ($skipInputTypes, &$changedTags): string {
            $tag = $m[1];
            $attrs = $m[2];

            if (str_contains($attrs, 'admin-input') || str_contains($attrs, 'admin-checkbox__input')) {
                return $m[0];
            }

            if ($tag === 'input' && preg_match('/type\s*=\s*["\']([^"\']+)["\']/i', $attrs, $typeMatch)) {
                if (in_array(strtolower($typeMatch[1]), $skipInputTypes, true)) {
                    return $m[0];
                }
            }

            if (preg_match('/\bclass\s*=\s*["\']([^"\']*)["\']/i', $attrs, $classMatch)) {
                $classes = trim($classMatch[1]);
                if ($classes === '') {
                    $newClass = 'admin-input';
                } elseif (! preg_match('/\badmin-input\b/', $classes)) {
                    $newClass = 'admin-input '.$classes;
                } else {
                    return $m[0];
                }
                $attrs = preg_replace(
                    '/\bclass\s*=\s*["\'][^"\']*["\']/i',
                    'class="'.htmlspecialchars($newClass, ENT_QUOTES).'"',
                    $attrs,
                    1
                );
            } else {
                $attrs = ' class="admin-input"'.$attrs;
            }

            $changedTags++;

            return '<'.$tag.$attrs.'>';
        },
        $content
    );

    if ($content !== $original) {
        file_put_contents($path, $content);
        $changedFiles++;
        echo 'Updated: '.str_replace($root.DIRECTORY_SEPARATOR, '', $path).PHP_EOL;
    }
}

echo PHP_EOL."Done. {$changedFiles} files, ~{$changedTags} tags updated.".PHP_EOL;

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
        '/<(input|select|textarea)\b([^>]+)>/is',
        static function (array $m): string {
            $tag = $m[1];
            $attrs = $m[2];

            if (substr_count(strtolower($attrs), 'class=') < 2) {
                return $m[0];
            }

            preg_match_all('/\bclass\s*=\s*("([^"]*)"|\'([^\']*)\'|@class\(\[([^\]]+)\]\))/i', $attrs, $matches, PREG_SET_ORDER);

            if (count($matches) < 2) {
                return $m[0];
            }

            $classes = [];
            $bladeClass = null;

            foreach ($matches as $match) {
                if (str_starts_with($match[0], '@class')) {
                    $bladeClass = $match[0];
                    continue;
                }
                foreach (preg_split('/\s+/', trim($match[2] ?? $match[3] ?? '')) as $class) {
                    if ($class !== '') {
                        $classes[] = $class;
                    }
                }
            }

            $classes = array_values(array_unique($classes));

            foreach ($matches as $match) {
                $attrs = str_replace($match[0], '', $attrs);
            }

            $attrs = preg_replace('/\s+/', ' ', trim($attrs));

            $classAttr = $classes !== [] ? ' class="'.implode(' ', $classes).'"' : '';
            $bladeAttr = $bladeClass ? ' '.$bladeClass : '';

            return '<'.$tag.$classAttr.$bladeAttr.' '.$attrs.'>';
        },
        $content
    );

    $content = preg_replace('/\s+>/', '>', $content);
    $content = preg_replace('/class="admin-input @class/', '@class', $content);

    $content = preg_replace(
        "/@class\(\['admin-input', 'admin-input'/",
        "@class(['admin-input'",
        $content
    );

    if ($content !== $original) {
        file_put_contents($path, $content);
        $fixed++;
        echo 'Fixed: '.str_replace($root.DIRECTORY_SEPARATOR, '', $path).PHP_EOL;
    }
}

echo PHP_EOL."Fixed {$fixed} files.".PHP_EOL;

<?php

namespace Tests\Unit\Views;

use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;
use Tests\TestCase;

class PublicCssLightOnlyTest extends TestCase
{
    public function test_public_css_files_do_not_contain_prefers_color_scheme_dark(): void
    {
        $cssDir = public_path('css');
        $this->assertDirectoryExists($cssDir);

        $offenders = [];
        $checked = 0;

        foreach ($this->cssFiles($cssDir) as $file) {
            $relative = str_replace($cssDir.DIRECTORY_SEPARATOR, '', $file);
            if ($this->isAdminCss($relative)) {
                continue;
            }

            $contents = file_get_contents($file);
            $this->assertNotFalse($contents, "Could not read {$relative}");
            $checked++;

            if (preg_match('/prefers-color-scheme\s*:\s*dark/i', $contents) === 1) {
                $offenders[] = $relative;
            }
        }

        $this->assertGreaterThan(0, $checked, 'Expected to inspect at least one public CSS file.');
        $this->assertSame(
            [],
            $offenders,
            "Public CSS must stay light-only. Found prefers-color-scheme: dark in:\n".implode("\n", $offenders)
        );
    }

    /**
     * @return iterable<string>
     */
    private function cssFiles(string $root): iterable
    {
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($root, RecursiveDirectoryIterator::SKIP_DOTS)
        );

        /** @var SplFileInfo $file */
        foreach ($iterator as $file) {
            if ($file->isFile() && strtolower($file->getExtension()) === 'css') {
                yield $file->getPathname();
            }
        }
    }

    private function isAdminCss(string $relative): bool
    {
        $base = basename($relative);

        return str_starts_with($base, 'admin');
    }
}

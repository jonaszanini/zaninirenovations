<?php

namespace Tests\Unit\Views;

use Illuminate\View\Compilers\BladeCompiler;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;
use Tests\TestCase;

class BladeSyntaxTest extends TestCase
{
    public function test_admin_blade_templates_compile_without_syntax_errors(): void
    {
        $compiler = app(BladeCompiler::class);
        $root = resource_path('views/admin');

        foreach ($this->bladeFiles($root) as $file) {
            $contents = file_get_contents($file);
            $this->assertNotFalse($contents, "Could not read {$file}");

            try {
                $compiler->compileString($contents);
            } catch (\Throwable $e) {
                $this->fail($this->relativeViewPath($file).': '.$e->getMessage());
            }
        }

        $this->assertTrue(true);
    }

    public function test_site_blade_templates_compile_without_syntax_errors(): void
    {
        $compiler = app(BladeCompiler::class);
        $root = resource_path('views/site');

        foreach ($this->bladeFiles($root) as $file) {
            $contents = file_get_contents($file);
            $this->assertNotFalse($contents, "Could not read {$file}");

            try {
                $compiler->compileString($contents);
            } catch (\Throwable $e) {
                $this->fail($this->relativeViewPath($file).': '.$e->getMessage());
            }
        }

        $this->assertTrue(true);
    }

    public function test_blade_views_do_not_use_mismatched_error_directive_quotes(): void
    {
        $viewsRoot = resource_path('views');
        $offenders = [];

        foreach ($this->bladeFiles($viewsRoot) as $file) {
            $contents = file_get_contents($file);
            if ($contents === false) {
                continue;
            }

            if (preg_match('/@error\("[^"]*\'\)/', $contents) === 1) {
                $offenders[] = $this->relativeViewPath($file);
            }
        }

        $this->assertSame(
            [],
            $offenders,
            "Mismatched @error directive quotes found in:\n".implode("\n", $offenders)
        );
    }

    /**
     * @return list<string>
     */
    private function bladeFiles(string $directory): array
    {
        if (! is_dir($directory)) {
            return [];
        }

        $files = [];
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($directory, \FilesystemIterator::SKIP_DOTS)
        );

        /** @var SplFileInfo $file */
        foreach ($iterator as $file) {
            if ($file->isFile() && str_ends_with($file->getFilename(), '.blade.php')) {
                $files[] = $file->getPathname();
            }
        }

        sort($files);

        return $files;
    }

    private function relativeViewPath(string $absolutePath): string
    {
        $viewsRoot = realpath(resource_path('views'));

        return $viewsRoot
            ? ltrim(str_replace($viewsRoot, 'resources/views', realpath($absolutePath) ?: $absolutePath), '\\/')
            : $absolutePath;
    }
}

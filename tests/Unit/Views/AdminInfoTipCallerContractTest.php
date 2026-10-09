<?php

namespace Tests\Unit\Views;

use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;
use Tests\TestCase;

class AdminInfoTipCallerContractTest extends TestCase
{
    public function test_no_view_passes_legacy_text_or_html_keys_to_tip_partials(): void
    {
        $roots = [
            resource_path('views') => '',
            app_path() => 'app/',
        ];
        $offenders = [];

        foreach ($roots as $root => $prefix) {
            /** @var SplFileInfo $file */
            foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root)) as $file) {
                if (! $file->isFile()) {
                    continue;
                }
                $name = $file->getFilename();
                if (! str_ends_with($name, '.blade.php') && ! str_ends_with($name, '.php')) {
                    continue;
                }
                $src = file_get_contents($file->getPathname());
                if ($src === false) {
                    continue;
                }
                $relative = $prefix.substr($file->getPathname(), strlen($root) + 1);
                foreach ($this->legacyTipKeyHits($src) as $hit) {
                    $offenders[] = $relative.': '.$hit;
                }
            }
        }

        $this->assertSame(
            [],
            $offenders,
            "Pass tipText/tipHtml (not text/html) to info_tip, _cp_field_hint, and labelHint:\n".implode("\n", $offenders)
        );
    }

    public function test_docs_document_tip_text_keys_not_legacy_text(): void
    {
        $docs = file_get_contents(base_path('docs/admin-ui.md'));

        $this->assertStringContainsString("['tipText' => 'Plain explanation.', 'tipLabel' => 'Labour rate']", $docs);
        $this->assertStringNotContainsString("@include('admin.partials.info_tip', ['text' =>", $docs);
        $this->assertStringContainsString('Do not pass', $docs);
    }

    public function test_search_select_maps_label_hint_html_to_tip_html(): void
    {
        $html = view('admin.partials.search_select', [
            'id' => 'ss-html-hint',
            'name' => 'client_id',
            'label' => 'Client from directory',
            'labelHint' => [
                'html' => 'Type a name, then tap a match. See <strong>Clients</strong>.',
            ],
            'searchUrl' => '/admin/clients/search',
        ])->render();

        $this->assertStringContainsString('<strong>Clients</strong>', $html);
        $this->assertStringContainsString('aria-label="About Client from directory"', $html);
    }

    public function test_dead_label_with_tip_partial_is_gone(): void
    {
        $this->assertFileDoesNotExist(resource_path('views/admin/partials/label_with_tip.blade.php'));
    }

    public function test_scanner_covers_include_if_when_each_and_variable_arrays(): void
    {
        $includeIf = $this->legacyTipKeyHits("@includeIf('admin.partials.info_tip', ['text' => 'legacy'])");
        $this->assertNotEmpty($includeIf);

        $includeWhen = $this->legacyTipKeyHits("@includeWhen(\$ok, 'admin.client_projects._cp_field_hint', ['html' => '<b>x</b>'])");
        $this->assertNotEmpty($includeWhen);

        $viaVar = $this->legacyTipKeyHits(
            "\$hint = ['text' => 'via variable'];\n@include('admin.partials.info_tip', \$hint)"
        );
        $this->assertNotEmpty($viaVar);

        $labelHintVar = $this->legacyTipKeyHits(
            "\$hint = ['html' => '<em>x</em>'];\n'labelHint' => \$hint"
        );
        $this->assertNotEmpty($labelHintVar);

        $cleanVar = $this->legacyTipKeyHits(
            "\$ssHint = ['tipText' => 'ok']; unset(\$ssHint['text'], \$ssHint['html']);\n@include('admin.client_projects._cp_field_hint', \$ssHint)"
        );
        $this->assertSame([], $cleanVar);

        $includeUnless = $this->legacyTipKeyHits("@includeUnless(\$skip, 'admin.partials.info_tip', ['text' => 'legacy'])");
        $this->assertNotEmpty($includeUnless);

        $includeFirst = $this->legacyTipKeyHits("@includeFirst(['admin.partials.info_tip'], ['html' => '<b>x</b>'])");
        $this->assertNotEmpty($includeFirst);

        $compactText = $this->legacyTipKeyHits("@include('admin.partials.info_tip', compact('text', 'label'))");
        $this->assertNotEmpty($compactText);

        $each = $this->legacyTipKeyHits("@each('admin.partials.info_tip', \$tips, 'tip')");
        $this->assertNotEmpty($each);

        $phpView = $this->legacyTipKeyHits("view('admin.partials.info_tip', ['text' => 'from app'])");
        $this->assertNotEmpty($phpView);
    }

    /**
     * @return list<string>
     */
    private function legacyTipKeyHits(string $src): array
    {
        $hits = [];
        $needles = [
            "/@include(?:If)?\(\s*['\"]admin\.partials\.info_tip['\"]/",
            "/@include(?:If)?\(\s*['\"]admin\.client_projects\._cp_field_hint['\"]/",
            "/@includeWhen\(\s*[^,]+,\s*['\"]admin\.partials\.info_tip['\"]/",
            "/@includeWhen\(\s*[^,]+,\s*['\"]admin\.client_projects\._cp_field_hint['\"]/",
            "/@includeUnless\(\s*[^,]+,\s*['\"]admin\.partials\.info_tip['\"]/",
            "/@includeUnless\(\s*[^,]+,\s*['\"]admin\.client_projects\._cp_field_hint['\"]/",
            "/@includeFirst\(\s*\[[^\]]*['\"]admin\.partials\.info_tip/",
            "/@each\(\s*['\"]admin\.(?:partials\.info_tip|client_projects\._cp_field_hint)['\"]/",
            "/view\(\s*['\"]admin\.partials\.info_tip['\"]/",
            "/['\"]labelHint['\"]\s*=>/",
        ];

        foreach ($needles as $re) {
            if (preg_match_all($re, $src, $matches, PREG_OFFSET_CAPTURE) < 1) {
                continue;
            }
            $isInclude = str_contains($re, '@include') || str_contains($re, '@each') || str_contains($re, 'view(');
            foreach ($matches[0] as $match) {
                $window = substr($src, $match[1], 220);
                if (str_contains($re, '@each')) {
                    $hits[] = preg_replace('/\s+/', ' ', substr($window, 0, 120));
                    continue;
                }
                if (preg_match("/compact\(\s*['\"](?:text|html)['\"]/", $window) === 1) {
                    $hits[] = 'compact(text/html)';
                    continue;
                }
                $array = str_contains($re, '@includeFirst')
                    ? $this->extractLastBalancedArrayInCall($src, $match[1])
                    : $this->extractBalancedArray($src, $match[1], $isInclude);
                if ($array !== null && preg_match("/['\"](?:text|html)['\"]\s*=>/", $array) === 1) {
                    $hits[] = preg_replace('/\s+/', ' ', substr($array, 0, 160));
                    continue;
                }
                if (preg_match('/,\s*(\$[A-Za-z_][A-Za-z0-9_]*)/', $window, $varMatch)
                    || preg_match('/[\'\"]labelHint[\'\"]\s*=>\s*(\$[A-Za-z_][A-Za-z0-9_]*)/', $window, $varMatch)) {
                    $varHits = $this->legacyKeysOnVariable($src, $match[1], $varMatch[1]);
                    foreach ($varHits as $varHit) {
                        $hits[] = $varHit;
                    }
                }
            }
        }

        return $hits;
    }

    private function extractLastBalancedArrayInCall(string $src, int $from): ?string
    {
        $close = strpos($src, ')', $from);
        if ($close === false) {
            return null;
        }
        $last = null;
        $offset = $from;
        while ($offset < $close) {
            $chunk = $this->extractBalancedArray($src, $offset, true);
            if ($chunk === null) {
                break;
            }
            $start = strpos($src, '[', $offset);
            if ($start === false || $start > $close) {
                break;
            }
            $last = $chunk;
            $offset = $start + strlen($chunk);
        }

        return $last;
    }

    private function extractBalancedArray(string $src, int $from, bool $mustBeCallArg): ?string
    {
        $start = strpos($src, '[', $from);
        if ($start === false) {
            return null;
        }
        if ($mustBeCallArg) {
            $close = strpos($src, ')', $from);
            if ($close !== false && $start > $close) {
                return null;
            }
        } elseif (($start - $from) > 48) {
            return null;
        }

        $depth = 0;
        $len = strlen($src);
        for ($i = $start; $i < $len; $i++) {
            $ch = $src[$i];
            if ($ch === '[') {
                $depth++;
            } elseif ($ch === ']') {
                $depth--;
                if ($depth === 0) {
                    return substr($src, $start, $i - $start + 1);
                }
            }
        }

        return null;
    }

    /**
     * @return list<string>
     */
    private function legacyKeysOnVariable(string $src, int $at, string $var): array
    {
        $before = substr($src, max(0, $at - 900), min(900, $at));
        $quoted = preg_quote($var, '/');
        if (preg_match('/'.$quoted.'\s*=\s*(\[[\s\S]{0,400}\])/', $before, $assign) !== 1) {
            return [];
        }
        if (preg_match("/['\"](?:text|html)['\"]\s*=>/", $assign[1]) !== 1) {
            return [];
        }
        if (str_contains($before, 'tipText') || str_contains($before, 'tipHtml')
            || str_contains($before, "unset({$var}['text']") || str_contains($before, "unset(\$ssHint['text']")) {
            return [];
        }

        return [$var.' still passes text/html'];
    }
}

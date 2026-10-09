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
        $root = resource_path('views');
        $offenders = [];

        /** @var SplFileInfo $file */
        foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root)) as $file) {
            if (! $file->isFile() || ! str_ends_with($file->getFilename(), '.blade.php')) {
                continue;
            }
            $src = file_get_contents($file->getPathname());
            if ($src === false) {
                continue;
            }
            $relative = substr($file->getPathname(), strlen($root) + 1);
            foreach ($this->legacyTipKeyHits($src) as $hit) {
                $offenders[] = $relative.': '.$hit;
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

    public function test_label_with_tip_does_not_inherit_parent_scope_keys(): void
    {
        $leaked = view('admin.partials.label_with_tip', [
            'for' => 'leaked-for',
            'labelHtml' => '<em>Leaked label</em>',
            'labelId' => 'leaked-label-id',
            'tipText' => 'Leaked tip text',
            'tipHtml' => '<strong>Leaked tip html</strong>',
            'tipLabel' => 'Leaked tip label',
            'lwLabelText' => 'Isolated label',
        ])->render();

        $this->assertStringContainsString('Isolated label', $leaked);
        $this->assertStringNotContainsString('leaked-for', $leaked);
        $this->assertStringNotContainsString('Leaked label', $leaked);
        $this->assertStringNotContainsString('leaked-label-id', $leaked);
        $this->assertStringNotContainsString('Leaked tip text', $leaked);
        $this->assertStringNotContainsString('Leaked tip html', $leaked);
        $this->assertStringNotContainsString('About Leaked tip label', $leaked);

        $explicit = view('admin.partials.label_with_tip', [
            'lwFor' => 'br-owner-rate',
            'lwLabelText' => 'Owner rate (CAD$)',
            'lwTipText' => 'Your hourly rate for this quote.',
            'lwTipLabel' => 'Owner rate (CAD$)',
        ])->render();

        $this->assertStringContainsString('for="br-owner-rate"', $explicit);
        $this->assertStringContainsString('Owner rate (CAD$)', $explicit);
        $this->assertStringContainsString('Your hourly rate for this quote.', $explicit);
        $this->assertStringContainsString('aria-label="About Owner rate (CAD$)"', $explicit);
    }

    /**
     * @return list<string>
     */
    private function legacyTipKeyHits(string $src): array
    {
        $hits = [];
        $needles = [
            "/@include\(\s*['\"]admin\.partials\.info_tip['\"]/",
            "/@include\(\s*['\"]admin\.client_projects\._cp_field_hint['\"]/",
            "/['\"]labelHint['\"]\s*=>/",
        ];

        foreach ($needles as $re) {
            if (preg_match_all($re, $src, $matches, PREG_OFFSET_CAPTURE) < 1) {
                continue;
            }
            $isInclude = str_contains($re, '@include');
            foreach ($matches[0] as $match) {
                $array = $this->extractBalancedArray($src, $match[1], $isInclude);
                if ($array === null) {
                    continue;
                }
                if (preg_match("/['\"](?:text|html)['\"]\s*=>/", $array) === 1) {
                    $hits[] = preg_replace('/\s+/', ' ', substr($array, 0, 160));
                }
            }
        }

        return $hits;
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
}

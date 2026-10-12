<?php

namespace Tests\Unit\Views;

use App\Models\AiInstruction;
use Illuminate\Support\HtmlString;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;
use Tests\TestCase;

class AiActionGroupTest extends TestCase
{
    public function test_shared_group_renders_ai_button_then_config_then_history(): void
    {
        $html = view('admin.partials.ai_action_group', [
            'instructionKey' => AiInstruction::KEY_QUOTATION_WORK_DESCRIPTION,
            'instructionLabel' => 'Edit quotation work description prompt',
            'showHistory' => true,
            'historyKey' => 'test-history',
            'slot' => new HtmlString(
                '<button type="button" class="admin-btn admin-btn--primary js-ai-btn" id="test-ai-btn">Improve description with AI</button>'
            ),
        ])->render();

        $this->assertStringContainsString('class="admin-ai-action-group"', $html);
        $this->assertStringContainsString('class="admin-ai-action-pair"', $html);
        $this->assertStringNotContainsString('admin-ai-action-tail', $html);
        $this->assertStringContainsString('id="test-ai-btn"', $html);
        $this->assertStringContainsString('admin-ai-instruction-edit-link', $html);
        $this->assertStringContainsString('data-history-mount', $html);
        $this->assertStringContainsString('data-history-key="test-history"', $html);
        $this->assertStringContainsString(
            route('admin.ai-instructions.edit', AiInstruction::KEY_QUOTATION_WORK_DESCRIPTION),
            $html
        );

        $buttonPos = strpos($html, 'id="test-ai-btn"');
        $configPos = strpos($html, 'admin-ai-instruction-edit-link');
        $historyPos = strpos($html, 'data-history-mount');

        $this->assertNotFalse($buttonPos);
        $this->assertNotFalse($configPos);
        $this->assertNotFalse($historyPos);
        $this->assertLessThan($configPos, $buttonPos, 'AI button must come before the config icon.');
        $this->assertLessThan($historyPos, $configPos, 'Config icon must come before the history/undo icon.');
    }

    public function test_shared_group_places_extra_actions_after_config_and_before_history(): void
    {
        $html = view('admin.partials.ai_action_group', [
            'instructionKey' => AiInstruction::KEY_QUOTATION_WORK_DESCRIPTION,
            'instructionLabel' => 'Edit quotation work description prompt',
            'showHistory' => true,
            'slot' => new HtmlString(
                '<button type="button" class="js-ai-btn">Improve description with AI</button>'
            ),
            'extra' => new HtmlString(
                '<button type="button" class="js-from-quote">Create automatically from quotation</button>'
                .'<a class="admin-ai-instruction-edit-link js-extra-config" href="#from-quote">From quote prompt</a>'
            ),
        ])->render();

        $improvePos = strpos($html, 'js-ai-btn');
        $primaryConfigPos = strpos($html, 'admin-ai-instruction-edit-link');
        $fromQuotePos = strpos($html, 'js-from-quote');
        $extraConfigPos = strpos($html, 'js-extra-config');
        $historyPos = strpos($html, 'data-history-mount');

        $this->assertNotFalse($improvePos);
        $this->assertNotFalse($primaryConfigPos);
        $this->assertNotFalse($fromQuotePos);
        $this->assertNotFalse($extraConfigPos);
        $this->assertNotFalse($historyPos);
        $this->assertLessThan($primaryConfigPos, $improvePos);
        $this->assertLessThan($fromQuotePos, $primaryConfigPos);
        $this->assertLessThan($extraConfigPos, $fromQuotePos);
        $this->assertLessThan($historyPos, $extraConfigPos);
    }

    public function test_fence_work_description_partial_renders_button_then_config_then_undo(): void
    {
        $html = view('admin.partials.quotation_work_description_ai', [
            'workDescriptionTextareaId' => 'fq-work-description',
            'aiContextFormId' => 'fq-form',
            'aiQuotationType' => 'fence',
            'trailingActionPartial' => 'admin.quotations.fence._work_description_trailing_actions',
        ])->render();

        $improvePos = strpos($html, 'quotation-work-description-ai__btn');
        $fromQuotePos = strpos($html, 'quotation-work-description-ai__from-quote');
        $historyPos = strpos($html, 'data-history-mount');
        $trailingPos = strpos($html, 'admin-ai-action-group--trailing');

        $this->assertNotFalse($improvePos);
        $this->assertNotFalse($fromQuotePos);
        $this->assertNotFalse($historyPos);
        $this->assertNotFalse($trailingPos);

        preg_match_all('/admin-ai-instruction-edit-link/', $html, $configMatches, PREG_OFFSET_CAPTURE);
        $this->assertGreaterThanOrEqual(2, count($configMatches[0]));
        $firstConfigPos = $configMatches[0][0][1];
        $secondConfigPos = $configMatches[0][1][1];

        $this->assertLessThan($firstConfigPos, $improvePos, 'Improve button must come before its config icon.');
        $this->assertLessThan($fromQuotePos, $firstConfigPos);
        $this->assertLessThan($secondConfigPos, $fromQuotePos, 'From-quote button must come before its config icon.');
        $this->assertLessThan($historyPos, $secondConfigPos, 'Undo must come after both AI pairs.');
        $this->assertLessThan($trailingPos, $historyPos, 'Template icons stay after the AI cluster.');
        $this->assertStringContainsString('fq-generate-work-description', $html);
    }

    public function test_blog_form_call_site_renders_suggest_then_config_then_extra_then_undo(): void
    {
        $form = (string) file_get_contents(resource_path('views/admin/blog/posts/form.blade.php'));
        $this->assertSame(1, preg_match(
            '/@component\(\s*[\'"]admin\.partials\.ai_action_group[\'"][\s\S]*?@endcomponent/',
            $form,
            $match
        ), 'Blog form must call admin.partials.ai_action_group.');

        $html = \Illuminate\Support\Facades\Blade::render($match[0], [
            'post' => (object) ['id' => 12],
        ]);

        $suggestPos = strpos($html, 'bp-ai-suggest-btn');
        $configPos = strpos($html, 'admin-ai-instruction-edit-link');
        $imagePos = strpos($html, 'bp-ai-image-btn');
        $checkPos = strpos($html, 'bp-check-btn');
        $historyPos = strpos($html, 'data-history-mount');

        $this->assertNotFalse($suggestPos);
        $this->assertNotFalse($configPos);
        $this->assertNotFalse($imagePos);
        $this->assertNotFalse($checkPos);
        $this->assertNotFalse($historyPos);
        $this->assertLessThan($configPos, $suggestPos, 'Suggest metadata must come before its config icon.');
        $this->assertLessThan($imagePos, $configPos);
        $this->assertLessThan($checkPos, $imagePos);
        $this->assertLessThan($historyPos, $checkPos, 'Undo must come after Check content, not beside it in markup.');
        $this->assertStringContainsString('data-history-key="blog-post-12"', $html);
    }

    public function test_portfolio_description_call_site_renders_button_then_config_then_undo(): void
    {
        $html = view('admin.partials.portfolio_description_ai')->render();

        $buttonPos = strpos($html, 'portfolio-desc-ai-btn');
        $configPos = strpos($html, 'admin-ai-instruction-edit-link');
        $historyPos = strpos($html, 'data-history-mount');

        $this->assertNotFalse($buttonPos);
        $this->assertNotFalse($configPos);
        $this->assertNotFalse($historyPos);
        $this->assertLessThan($configPos, $buttonPos, 'Portfolio AI button must come before its config icon.');
        $this->assertLessThan($historyPos, $configPos, 'Undo must come after the config icon.');
        $this->assertStringContainsString(
            route('admin.ai-instructions.edit', AiInstruction::KEY_PORTFOLIO_DESCRIPTION),
            $html
        );
    }

    public function test_pair_css_keeps_button_and_config_on_one_shrinking_row(): void
    {
        $css = file_get_contents(public_path('css/admin.css'));
        $this->assertNotFalse($css);

        $this->assertMatchesRegularExpression(
            '/\.admin-ai-action-group\s*\{[^}]*display:\s*inline-flex/s',
            $css
        );
        preg_match('/^\.admin-ai-action-group\s*\{([^}]+)\}/m', $css, $groupBlock);
        $this->assertNotEmpty($groupBlock[1] ?? null);
        $this->assertDoesNotMatchRegularExpression(
            '/(?<!max-)width:\s*100%/',
            $groupBlock[1],
            'Do not set width:100% on .admin-ai-action-group; it stretches trailing groups and sibling rows.'
        );
        $this->assertMatchesRegularExpression(
            '/\.admin-ai-action-pair\s*\{[^}]*flex-wrap:\s*nowrap/s',
            $css
        );
        $this->assertMatchesRegularExpression(
            '/\.admin-ai-action-pair\s*>\s*\.admin-btn\s*\{[^}]*min-width:\s*0/s',
            $css
        );
        $this->assertMatchesRegularExpression(
            '/\.admin-ai-action-pair\s*>\s*\.admin-btn\s*\{[^}]*white-space:\s*normal/s',
            $css
        );
        $this->assertMatchesRegularExpression(
            '/\.admin-ai-action-pair\s*>\s*\.admin-btn\s*\{[^}]*flex-shrink:\s*1/s',
            $css
        );
        $this->assertMatchesRegularExpression(
            '/#admin-photo-edit-modal \.admin-ai-action-group\s*\{[^}]*width:\s*auto/s',
            $css
        );
        $this->assertMatchesRegularExpression(
            '/#admin-photo-edit-modal \.admin-ai-action-group\s*>\s*\.admin-ai-action-pair\s*\{[^}]*flex:\s*0 0 auto/s',
            $css
        );
        $this->assertMatchesRegularExpression(
            '/@media\s*\(\s*max-width:\s*380px\s*\)\s*\{[^}]*#admin-photo-edit-modal \.admin-modal-actions\s*\{[^}]*flex-wrap:\s*wrap/s',
            $css,
            'Photo-edit modal actions must wrap below ~380px so Save stays inside the padded panel.'
        );
        $this->assertDoesNotMatchRegularExpression(
            '/@media\s*\(\s*max-width:\s*380px\s*\)\s*\{[^}]*margin-left:\s*auto/s',
            $css,
            'The ≤380 modal wrap must not restate margin-left:auto on actions-end.'
        );
        $this->assertMatchesRegularExpression(
            '/\.blog-admin-ai-metadata-wrap\s*>\s*\.admin-ai-action-group\s*\{[^}]*width:\s*100%/s',
            $css,
            'Blog AI group must set width:100% so extra-pair and undo wrap resolve.'
        );
        $this->assertDoesNotMatchRegularExpression(
            '/\.nb2-generate-row\s*>\s*\.admin-ai-action-group[^{]*\{[^}]*width:\s*100%/s',
            $css,
            'Do not force width:100% on quotation/image-modify generate-row groups.'
        );
        $this->assertMatchesRegularExpression(
            '/\.blog-admin-ai-metadata-wrap\s*>\s*\.admin-ai-action-group\s*>\s*\.admin-ai-text-history-mount\s*\{[^}]*flex:\s*1 1 100%/s',
            $css,
            'Undo-on-its-own-row must be scoped to the blog metadata wrap.'
        );
        $this->assertDoesNotMatchRegularExpression(
            '/\.admin-ai-action-group:not\(\.admin-ai-action-group--trailing\)\s*>\s*\.admin-ai-text-history-mount\s*\{[^}]*flex:\s*1 1 100%/s',
            $css,
            'Do not put every group\'s undo on its own row at ≤700px.'
        );
        $this->assertMatchesRegularExpression(
            '/\.blog-admin-ai-metadata-wrap\s*>\s*\.admin-ai-action-group\s*>\s*\.admin-ai-action-pair\s*~\s*\.admin-ai-action-pair\s*\{[^}]*flex-wrap:\s*wrap/s',
            $css,
            'Blog extra pair must wrap as a unit so Generate image can stay on one line.'
        );
        $this->assertMatchesRegularExpression(
            '/\.blog-admin-ai-metadata-wrap\s*>\s*\.admin-ai-action-group\s*>\s*\.admin-ai-action-pair\s*~\s*\.admin-ai-action-pair\s*>\s*\.admin-btn\s*\{[^}]*white-space:\s*nowrap/s',
            $css,
            'Blog extra buttons must be nowrap (not only the second .admin-btn ~ .admin-btn).'
        );
    }

    public function test_each_inline_ai_group_uses_the_shared_action_group(): void
    {
        $offenders = [];
        $sites = 0;

        foreach ($this->adminBladeFiles() as $file) {
            $relative = $this->relativeViewPath($file);
            if (str_ends_with($relative, 'partials/ai_action_group.blade.php')) {
                continue;
            }

            $contents = (string) file_get_contents($file);
            $sites += count($this->componentAiGroupBlocks($contents));

            foreach ($this->rawAiActionGroupOffenders($contents, $relative) as $offender) {
                $offenders[] = $offender;
            }
        }

        $this->assertGreaterThanOrEqual(
            11,
            $sites,
            'Per-group scan must visit every @component call site, not only leftover raw markup.'
        );
        $this->assertSame(
            [],
            $offenders,
            "Every AI generate/improve group must use admin.partials.ai_action_group:\n".implode("\n", $offenders)
        );
    }

    public function test_config_icon_is_not_emitted_before_the_ai_button(): void
    {
        $offenders = [];
        $checkedGroups = 0;

        foreach ($this->adminBladeFiles() as $file) {
            $relative = $this->relativeViewPath($file);
            $contents = (string) file_get_contents($file);
            $isSharedGroup = str_ends_with($relative, 'partials/ai_action_group.blade.php');

            if (! $isSharedGroup) {
                foreach ($this->rawAiActionGroupOffenders($contents, $relative) as $offender) {
                    $offenders[] = $offender;
                }
            }

            foreach ($this->componentAiGroupBlocks($contents) as $block) {
                $checkedGroups++;
                if ($this->componentGroupHasConfigBeforeButton($block)) {
                    $offenders[] = $relative.' (config include before an AI button in the component call site)';
                }
            }

            foreach ($this->configBeforeAiButtonChunks($contents) as $unused) {
                $offenders[] = $relative.' (config include before an AI <button>…</button>)';
                break;
            }
        }

        $this->assertGreaterThanOrEqual(
            11,
            $checkedGroups,
            'Config-before-button must inspect each @component group, not skip converted files.'
        );
        $this->assertSame(
            [],
            array_values(array_unique($offenders)),
            "Config icon must not appear before the AI button:\n".implode("\n", $offenders)
        );
    }

    public function test_old_blog_style_config_before_inner_icon_is_detected(): void
    {
        $old = <<<'BLADE'
            @include('admin.partials.ai_instruction_edit_link', ['instructionKey' => 'blog_post'])
            <button type="button" class="admin-btn" id="bp-ai-suggest-btn">
              <i class="fa fa-magic" aria-hidden="true"></i> Suggest metadata
            </button>
        BLADE;

        $this->assertNotEmpty($this->configBeforeAiButtonChunks($old));
    }

    public function test_raw_group_is_detected_regardless_of_attribute_order(): void
    {
        $mixed = <<<'BLADE'
            <div data-src="{{ $photo->url }}" title='Quote "{{ $photo->alt }}"' @if($open) hidden @endif class="admin-card admin-ai-action-group">
              @include('admin.partials.ai_instruction_edit_link', ['instructionKey' => 'blog_post'])
              <button type="button">Suggest metadata</button>
            </div>
        BLADE;

        $this->assertNotEmpty($this->rawAiActionGroupOffenders($mixed, 'fixture.blade.php'));
    }

    /**
     * @return list<string>
     */
    private function rawAiActionGroupOffenders(string $contents, string $relative): array
    {
        $offenders = [];

        foreach ($this->divOpenTags($contents) as $div) {
            $class = $this->htmlClassAttribute($div['tag']);
            if ($class === null || ! str_contains($class, 'admin-ai-action-group')) {
                continue;
            }
            if (str_contains($class, 'admin-ai-action-group--trailing')) {
                continue;
            }

            $following = substr($contents, $div['offset'], 900);
            if (str_contains($following, 'ai_instruction_edit_link')) {
                $offenders[] = $relative.' (inline group still includes ai_instruction_edit_link)';
            }
        }

        return $offenders;
    }

    /**
     * @return list<array{tag: string, offset: int}>
     */
    private function divOpenTags(string $contents): array
    {
        $tags = [];
        $offset = 0;
        $length = strlen($contents);

        while (($start = stripos($contents, '<div', $offset)) !== false) {
            $end = $this->endOfHtmlTag($contents, $start, $length);
            if ($end === null) {
                break;
            }
            $tags[] = [
                'tag' => substr($contents, $start, $end - $start + 1),
                'offset' => $start,
            ];
            $offset = $end + 1;
        }

        return $tags;
    }

    private function endOfHtmlTag(string $contents, int $start, int $length): ?int
    {
        $i = $start + 4;
        $quote = null;
        $blade = 0;

        while ($i < $length) {
            $ch = $contents[$i];
            $next = $contents[$i + 1] ?? '';

            if ($blade > 0) {
                if ($ch === '}' && $next === '}') {
                    $blade--;
                    $i += 2;
                    continue;
                }
                $i++;
                continue;
            }

            if ($quote !== null) {
                if ($ch === '{' && $next === '{') {
                    $blade++;
                    $i += 2;
                    continue;
                }
                if ($ch === $quote) {
                    $quote = null;
                }
                $i++;
                continue;
            }

            if ($ch === '"' || $ch === "'") {
                $quote = $ch;
                $i++;
                continue;
            }

            if ($ch === '{' && $next === '{') {
                $blade++;
                $i += 2;
                continue;
            }

            if ($ch === '>') {
                return $i;
            }

            $i++;
        }

        return null;
    }

    private function htmlClassAttribute(string $tag): ?string
    {
        if (preg_match('/\bclass\s*=\s*(["\'])(.*?)\1/s', $tag, $match) !== 1) {
            return null;
        }

        return $match[2];
    }

    /**
     * @return list<string>
     */
    private function componentAiGroupBlocks(string $contents): array
    {
        if (preg_match_all(
            '/@component\(\s*[\'"]admin\.partials\.ai_action_group[\'"][\s\S]*?@endcomponent/',
            $contents,
            $matches
        ) < 1) {
            return [];
        }

        return $matches[0];
    }

    private function componentGroupHasConfigBeforeButton(string $block): bool
    {
        $parts = preg_split('/@slot\(\s*[\'"]extra[\'"]\s*\)/', $block, 2);
        $regions = $parts === false ? [$block] : $parts;

        foreach ($regions as $region) {
            $includePos = strpos($region, 'ai_instruction_edit_link');
            if ($includePos === false) {
                continue;
            }
            if (! preg_match('/<button\b[\s\S]*?<\/button>/i', $region, $buttonMatch, PREG_OFFSET_CAPTURE)) {
                return true;
            }
            if ($includePos < $buttonMatch[0][1]) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return list<string>
     */
    private function configBeforeAiButtonChunks(string $contents): array
    {
        $aiButtonMarker = 'fa-magic|Improve |Generate |Auto categorize|Suggest metadata|Apply AI|Improve prompt|Create automatically';
        if (preg_match_all(
            '/ai_instruction_edit_link[\s\S]{0,800}<button[\s\S]*?<\/button>/',
            $contents,
            $matches
        ) < 1) {
            return [];
        }

        $chunks = [];
        foreach ($matches[0] as $chunk) {
            if (preg_match('/'.$aiButtonMarker.'/', $chunk) === 1) {
                $chunks[] = $chunk;
            }
        }

        return $chunks;
    }

    /**
     * @return list<string>
     */
    private function adminBladeFiles(): array
    {
        $directory = resource_path('views/admin');
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

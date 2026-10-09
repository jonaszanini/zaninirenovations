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
    }

    public function test_each_inline_ai_group_uses_the_shared_action_group(): void
    {
        $offenders = [];

        foreach ($this->adminBladeFiles() as $file) {
            $relative = $this->relativeViewPath($file);
            if (str_ends_with($relative, 'partials/ai_action_group.blade.php')) {
                continue;
            }

            $contents = (string) file_get_contents($file);
            if (preg_match_all('/<div\s+class="([^"]*)"/', $contents, $matches, PREG_OFFSET_CAPTURE) < 1) {
                continue;
            }

            foreach ($matches[1] as $match) {
                $class = $match[0];
                if (! str_contains($class, 'admin-ai-action-group')) {
                    continue;
                }
                if (str_contains($class, 'admin-ai-action-group--trailing')) {
                    continue;
                }

                $offset = $match[1];
                $following = substr($contents, $offset, 900);
                if (str_contains($following, 'ai_instruction_edit_link')) {
                    $offenders[] = $relative.' (inline group still includes ai_instruction_edit_link)';
                }
            }
        }

        $this->assertSame(
            [],
            $offenders,
            "Every AI generate/improve group must use admin.partials.ai_action_group:\n".implode("\n", $offenders)
        );
    }

    public function test_config_icon_is_not_emitted_before_the_ai_button(): void
    {
        $offenders = [];
        $aiButtonMarker = 'fa-magic|Improve |Generate |Auto categorize|Suggest metadata|Apply AI|Improve prompt|Create automatically';

        foreach ($this->adminBladeFiles() as $file) {
            $relative = $this->relativeViewPath($file);
            $contents = (string) file_get_contents($file);
            if (! str_contains($contents, 'ai_instruction_edit_link')) {
                continue;
            }

            if (preg_match_all(
                '/ai_instruction_edit_link[\s\S]{0,800}<button[\s\S]*?<\/button>/',
                $contents,
                $matches
            ) === 0) {
                continue;
            }

            foreach ($matches[0] as $chunk) {
                if (preg_match('/'.$aiButtonMarker.'/', $chunk) === 1) {
                    $offenders[] = $relative;
                    break;
                }
            }
        }

        $this->assertSame(
            [],
            array_values(array_unique($offenders)),
            "Config icon must not appear before the AI button (checked through </button>):\n".implode("\n", $offenders)
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

        $this->assertSame(1, preg_match(
            '/ai_instruction_edit_link[\s\S]{0,800}<button[\s\S]*?<\/button>/',
            $old
        ));
        preg_match('/ai_instruction_edit_link[\s\S]{0,800}<button[\s\S]*?<\/button>/', $old, $match);
        $this->assertMatchesRegularExpression('/fa-magic|Suggest metadata/', $match[0]);
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

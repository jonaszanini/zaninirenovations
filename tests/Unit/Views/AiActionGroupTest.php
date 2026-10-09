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

    public function test_admin_ai_generate_groups_use_the_shared_action_group(): void
    {
        $offenders = [];

        foreach ($this->adminBladeFiles() as $file) {
            $relative = $this->relativeViewPath($file);
            if (str_ends_with($relative, 'partials/ai_action_group.blade.php')) {
                continue;
            }

            $contents = (string) file_get_contents($file);
            $hasInlineGroup = (bool) preg_match(
                '/class="admin-ai-action-group(?!--)[^"]*"[^>]*>[\s\S]{0,800}ai_instruction_edit_link/',
                $contents
            );
            $usesShared = str_contains($contents, "admin.partials.ai_action_group");

            if ($hasInlineGroup && ! $usesShared) {
                $offenders[] = $relative;
            }
        }

        $this->assertSame(
            [],
            $offenders,
            "AI generate/improve groups must use admin.partials.ai_action_group so the button comes first:\n".implode("\n", $offenders)
        );
    }

    public function test_config_icon_is_not_emitted_before_the_ai_button(): void
    {
        $offenders = [];

        foreach ($this->adminBladeFiles() as $file) {
            $relative = $this->relativeViewPath($file);
            $contents = (string) file_get_contents($file);
            if (preg_match(
                '/ai_instruction_edit_link[\s\S]{0,400}<button[^>]*(fa-magic|Improve |Generate |Auto categorize|Suggest metadata|Apply AI|Improve prompt)/',
                $contents
            ) === 1) {
                $offenders[] = $relative;
            }
        }

        $this->assertSame(
            [],
            $offenders,
            "Config icon must not appear before the AI button:\n".implode("\n", $offenders)
        );
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

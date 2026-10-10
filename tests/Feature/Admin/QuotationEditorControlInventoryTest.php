<?php

namespace Tests\Feature\Admin;

use App\Models\BasementQuotation;
use App\Models\FenceQuotation;
use App\Models\LowDeckQuotation;
use App\Models\MillworkQuotation;
use App\Models\ShedQuotation;
use App\Models\StandardQuotation;
use Tests\IntegrationTestCase;
use Tests\Support\ActsAsAdmin;

class QuotationEditorControlInventoryTest extends IntegrationTestCase
{
    use ActsAsAdmin;

    /**
     * @return array<string, array{0: string, 1: string, 2: list<string>}>
     */
    public function createPages(): array
    {
        return [
            'standard' => ['admin.quotations.standard.create', 'standard', ['Quotation types', 'Grand total']],
            'basement' => ['admin.quotations.basement.create', 'basement', ['id="br-step-nav"', 'id="br-next"']],
            'fence' => ['admin.quotations.fence.create', 'fence', ['Save &amp; create project', 'after_save', 'create_project']],
            'low_deck' => ['admin.quotations.low-deck.create', 'low-deck', []],
            'millwork' => ['admin.quotations.millwork.create', 'millwork', ['qh-wizard']],
            'shed' => ['admin.quotations.shed.create', 'shed', ['qh-wizard']],
        ];
    }

    /**
     * @dataProvider createPages
     *
     * @param  list<string>  $needles
     */
    public function test_create_page_keeps_main_toolbar_controls(string $route, string $type, array $needles): void
    {
        $this->actingAsAdmin();

        $html = $this->get(route($route))
            ->assertOk()
            ->assertSee('data-qh-header', false)
            ->assertSee('Save quotation', false)
            ->assertSee('data-qh-cancel', false)
            ->getContent();

        $this->assertStringNotContainsString('Back to project', $html);
        $this->assertStringNotContainsString('Back to list', $html);

        foreach ($needles as $needle) {
            $this->assertStringContainsString($needle, $html, "{$type} create is missing {$needle}");
        }

        if ($type === 'standard') {
            $this->assertStringContainsString(route('admin.quotations.index'), $html);
            $this->assertStringContainsString('id="sq-toolbar-grand"', $html);
            $this->assertStringContainsString('br-toolbar-grand', $html);
        }
        if ($type === 'low-deck') {
            $this->assertStringNotContainsString('Print Layout Diagrams', $html);
        }
        if ($type === 'millwork') {
            $this->assertStringNotContainsString('data-qh-cut-list', $html);
        }
    }

    public function test_edit_pages_restore_email_sms_diagrams_and_cut_list(): void
    {
        $this->actingAsAdmin();

        $basement = BasementQuotation::query()->create([
            'quote_number' => 'BR-INV-1',
            'client_name' => 'Inventory Basement',
            'is_draft' => true,
            'inputs' => [],
            'calculation' => [],
            'final_quote_total' => 0,
        ]);
        $this->get(route('admin.quotations.basement.edit', $basement))
            ->assertOk()
            ->assertSee('Email quotation to client', false)
            ->assertSee('data-qh-email', false)
            ->assertSee('View PDF', false)
            ->assertSee('data-qh-view-pdf', false)
            ->assertSee('id="br-step-nav"', false)
            ->assertSee('id="br-sticky-total"', false);

        $standard = StandardQuotation::query()->create([
            'quote_number' => 'SQ-INV-1',
            'client_name' => 'Inventory Standard',
            'display_title' => 'Kitchen',
            'materials' => [],
        ]);
        $this->get(route('admin.quotations.standard.edit', $standard))
            ->assertOk()
            ->assertSee('Email quotation to client', false)
            ->assertSee('SMS quotation link to client', false)
            ->assertSee('Print material list', false)
            ->assertSee('View PDF', false)
            ->assertSee('data-qh-view-pdf', false)
            ->assertSee('id="sq-toolbar-grand"', false)
            ->assertSee('br-toolbar-grand', false);

        $fence = FenceQuotation::query()->create([
            'quote_number' => 'FQ-INV-1',
            'client_name' => 'Inventory Fence',
            'sides' => [['length_ft' => 20]],
            'calculation' => ['materials' => [], 'totals' => []],
        ]);
        $fenceHtml = $this->get(route('admin.quotations.fence.edit', $fence))
            ->assertOk()
            ->assertSee('Print material list', false)
            ->assertSee('View PDF', false)
            ->assertSee('data-qh-view-pdf', false)
            ->getContent();
        $this->assertStringNotContainsString('Save &amp; create project', $fenceHtml);

        $deck = LowDeckQuotation::query()->create([
            'quote_number' => 'LD-INV-1',
            'client_name' => 'Inventory Deck',
            'width_ft' => 12,
            'length_ft' => 16,
            'calculation' => [],
        ]);
        $this->get(route('admin.quotations.low-deck.edit', $deck))
            ->assertOk()
            ->assertSee('Print Layout Diagrams', false)
            ->assertSee('data-qh-layout-diagrams', false)
            ->assertSee('View PDF', false)
            ->assertSee(route('admin.quotations.low-deck.layout-diagrams', $deck), false);

        $millwork = MillworkQuotation::query()->create([
            'quote_number' => 'MW-INV-1',
            'client_name' => 'Inventory Millwork',
            'is_draft' => true,
            'inputs' => ['pieces' => []],
            'calculation' => [],
        ]);
        $mwHtml = $this->get(route('admin.quotations.millwork.edit', $millwork))
            ->assertOk()
            ->assertSee('Print cut list', false)
            ->assertSee('data-qh-cut-list', false)
            ->assertSee(route('admin.quotations.millwork.cut-list', $millwork), false)
            ->getContent();
        $this->assertMatchesRegularExpression('/id="mw-step-nav"[^>]*hidden|hidden[^>]*id="mw-step-nav"/', $mwHtml);

        $shed = ShedQuotation::query()->create([
            'quote_number' => 'SH-INV-1',
            'client_name' => 'Inventory Shed',
            'is_draft' => true,
            'inputs' => [],
            'calculation' => [],
            'final_quote_total' => 0,
        ]);
        $shHtml = $this->get(route('admin.quotations.shed.edit', $shed))
            ->assertOk()
            ->getContent();
        $this->assertMatchesRegularExpression('/id="sh-step-nav"[^>]*hidden|hidden[^>]*id="sh-step-nav"/', $shHtml);
    }

    public function test_programmatic_diff_against_main_toolbars(): void
    {
        $this->actingAsAdmin();

        $map = [
            'basement' => [
                'file' => 'basement/create.blade.php',
                'main' => ['Email quotation to client'],
                'rendered' => fn () => $this->renderedBasementEdit(),
                'needles' => ['Email quotation to client'],
            ],
            'standard' => [
                'file' => 'standard/create.blade.php',
                'main' => ['Email quotation to client', 'SMS quotation link to client', 'Quotation types'],
                'rendered' => fn () => $this->get(route('admin.quotations.standard.create'))->assertOk()->getContent()
                    ."\n".$this->renderedStandardEdit(),
                'needles' => ['Email quotation to client', 'SMS quotation link to client', 'Quotation types'],
            ],
            'fence' => [
                'file' => 'fence/create.blade.php',
                'main' => ['Save &amp; create project', 'after_save', 'create_project'],
                'rendered' => fn () => $this->get(route('admin.quotations.fence.create'))->assertOk()->getContent(),
                'needles' => ['Save &amp; create project', 'after_save', 'create_project'],
            ],
            'low_deck' => [
                'file' => 'low_deck/create.blade.php',
                'main' => ['Print Layout Diagrams', 'low-deck.layout-diagrams'],
                'rendered' => fn () => $this->renderedLowDeckEdit(),
                'needles' => ['Print Layout Diagrams', 'data-qh-layout-diagrams'],
            ],
            'millwork' => [
                'file' => 'millwork/create.blade.php',
                'main' => ['Print cut list'],
                'rendered' => fn () => $this->renderedMillworkEdit(),
                'needles' => ['Print cut list'],
            ],
        ];

        foreach ($map as $type => $spec) {
            $mainPaths = [
                'resources/views/admin/quotations/'.$spec['file'],
                'resources/views/admin/partials/quotation_editor_header.blade.php',
                'resources/views/admin/partials/quotation_editor_more_actions.blade.php',
            ];
            $main = '';
            foreach ($mainPaths as $path) {
                $chunk = (string) shell_exec('git show origin/main:'.$path.' 2>/dev/null');
                if ($chunk === '') {
                    $chunk = (string) shell_exec('git show main:'.$path.' 2>/dev/null');
                }
                $main .= $chunk;
            }
            if (trim($main) === '') {
                $this->fail('origin/main (or main) is required for the toolbar inventory diff; CI must fetch it');
            }
            foreach ($spec['main'] as $needle) {
                $this->assertStringContainsString($needle, $main, "main {$spec['file']} no longer has {$needle}");
            }

            $html = $spec['rendered']();
            $this->assertNotSame('', trim($html), "{$type} rendered HTML was empty");
            foreach ($spec['needles'] as $needle) {
                $this->assertStringContainsString(
                    $needle,
                    $html,
                    "Rendered {$type} editor is missing {$needle} that main still has — do not hide this behind an unused partial"
                );
            }
        }
    }

    private function renderedBasementEdit(): string
    {
        $quote = BasementQuotation::query()->create([
            'quote_number' => 'BR-INV-MAIN-1',
            'client_name' => 'Inventory Main Basement',
            'is_draft' => true,
            'inputs' => [],
            'calculation' => [],
            'final_quote_total' => 0,
        ]);

        return $this->get(route('admin.quotations.basement.edit', $quote))->assertOk()->getContent();
    }

    private function renderedStandardEdit(): string
    {
        $quote = StandardQuotation::query()->create([
            'quote_number' => 'SQ-INV-MAIN-1',
            'client_name' => 'Inventory Main Standard',
            'display_title' => 'Kitchen',
            'materials' => [],
        ]);

        return $this->get(route('admin.quotations.standard.edit', $quote))->assertOk()->getContent();
    }

    private function renderedLowDeckEdit(): string
    {
        $quote = LowDeckQuotation::query()->create([
            'quote_number' => 'LD-INV-MAIN-1',
            'client_name' => 'Inventory Main Deck',
            'width_ft' => 12,
            'length_ft' => 16,
            'calculation' => [],
        ]);

        $html = $this->get(route('admin.quotations.low-deck.edit', $quote))->assertOk()->getContent();
        $this->assertStringContainsString(
            route('admin.quotations.low-deck.layout-diagrams', $quote),
            $html,
            'Rendered low deck edit must keep the layout-diagrams URL'
        );

        return $html;
    }

    private function renderedMillworkEdit(): string
    {
        $quote = MillworkQuotation::query()->create([
            'quote_number' => 'MW-INV-MAIN-1',
            'client_name' => 'Inventory Main Millwork',
            'is_draft' => true,
            'inputs' => ['pieces' => []],
            'calculation' => [],
        ]);

        $html = $this->get(route('admin.quotations.millwork.edit', $quote))->assertOk()->getContent();
        $this->assertStringContainsString(
            route('admin.quotations.millwork.cut-list', $quote),
            $html,
            'Rendered millwork edit must keep the cut-list URL'
        );

        return $html;
    }

    public function test_main_toolbar_controls_are_represented_in_shared_partials(): void
    {
        $more = file_get_contents(resource_path('views/admin/partials/quotation_editor_more_actions.blade.php'));
        $this->assertNotFalse($more);
        $pdf = file_get_contents(resource_path('views/admin/partials/quotation_view_pdf.blade.php'));
        $this->assertNotFalse($pdf);
        $this->assertStringContainsString('View PDF', $pdf);
        $this->assertStringContainsString('data-qh-view-pdf', $pdf);
        $this->assertStringContainsString('quotation_view_pdf', $more);

        foreach ([
            'Email quotation to client',
            'SMS quotation link to client',
            'Save &amp; create project',
            'Print Layout Diagrams',
            'Print cut list',
            'Quotation types',
            'after_save',
            'create_project',
        ] as $needle) {
            $this->assertStringContainsString($needle, $more);
        }

        $creates = [
            'basement/create.blade.php' => ["'email'", 'br-email-quote-form'],
            'standard/create.blade.php' => ['showHubLink', "'sms'", "'email'", 'st-btn-draft'],
            'fence/create.blade.php' => ['showCreateProject', 'createProjectFormId', 'fq-btn-draft'],
            'low_deck/create.blade.php' => ['layoutDiagramsUrl', 'ld-btn-draft'],
            'millwork/create.blade.php' => ['cutListUrl', 'mw-btn-draft'],
        ];
        foreach ($creates as $file => $needles) {
            $blade = file_get_contents(resource_path('views/admin/quotations/'.$file));
            foreach ($needles as $needle) {
                $this->assertStringContainsString($needle, $blade, "{$file} lost {$needle}");
            }
        }
    }
}

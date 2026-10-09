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
            ->assertSee('Grand total', false);

        $fence = FenceQuotation::query()->create([
            'quote_number' => 'FQ-INV-1',
            'client_name' => 'Inventory Fence',
            'sides' => [['length_ft' => 20]],
            'calculation' => ['materials' => [], 'totals' => []],
        ]);
        $fenceHtml = $this->get(route('admin.quotations.fence.edit', $fence))
            ->assertOk()
            ->assertSee('Print material list', false)
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
        $map = [
            'basement/create.blade.php' => ['Email quotation to client'],
            'standard/create.blade.php' => ['Email quotation to client', 'SMS quotation link to client', 'Quotation types'],
            'fence/create.blade.php' => ['Save &amp; create project', 'after_save', 'create_project'],
            'low_deck/create.blade.php' => ['Print Layout Diagrams', 'low-deck.layout-diagrams'],
            'millwork/create.blade.php' => ['Print cut list'],
        ];
        $more = file_get_contents(resource_path('views/admin/partials/quotation_editor_more_actions.blade.php'));
        foreach ($map as $file => $needles) {
            $head = file_get_contents(resource_path('views/admin/quotations/'.$file))."\n".$more;
            $main = (string) shell_exec('git show origin/main:resources/views/admin/quotations/'.$file.' 2>/dev/null');
            if ($main === '') {
                $this->markTestSkipped('origin/main is not available for the toolbar inventory diff');
            }
            foreach ($needles as $needle) {
                $this->assertStringContainsString($needle, $main, "main {$file} no longer has {$needle}");
                $this->assertStringContainsString($needle, $head, "head {$file} dropped {$needle} from main");
            }
        }
    }

    public function test_main_toolbar_controls_are_represented_in_shared_partials(): void
    {
        $more = file_get_contents(resource_path('views/admin/partials/quotation_editor_more_actions.blade.php'));
        $this->assertNotFalse($more);
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
            'basement/create.blade.php' => ['Email quotation to client', 'br-email-quote-form'],
            'standard/create.blade.php' => ['showHubLink', 'sms', 'Email quotation to client'],
            'fence/create.blade.php' => ['showCreateProject', 'create_project'],
            'low_deck/create.blade.php' => ['layoutDiagramsUrl'],
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

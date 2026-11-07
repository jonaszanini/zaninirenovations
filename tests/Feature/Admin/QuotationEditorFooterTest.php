<?php

namespace Tests\Feature\Admin;

use App\Models\ClientProject;
use Tests\IntegrationTestCase;
use Tests\Support\ActsAsAdmin;

class QuotationEditorFooterTest extends IntegrationTestCase
{
    use ActsAsAdmin;

    /**
     * @return array<string, array{0: string, 1: string, 2: bool}>
     */
    public function quotationTypes(): array
    {
        return [
            'standard' => ['standard', 'admin.quotations.standard.create', false],
            'basement' => ['basement', 'admin.quotations.basement.create', false],
            'millwork' => ['millwork', 'admin.quotations.millwork.create', false],
            'flooring' => ['flooring', 'admin.quotations.flooring.create', false],
            'trimming' => ['trimming', 'admin.quotations.trimming.create', false],
            'painting' => ['painting', 'admin.quotations.painting.create', true],
            'shed' => ['shed', 'admin.quotations.shed.create', false],
            'tile' => ['tile', 'admin.quotations.tile.create', false],
            'fence' => ['fence', 'admin.quotations.fence.create', false],
            'paving' => ['paving', 'admin.quotations.paving.create', false],
            'stairs' => ['stairs', 'admin.quotations.stairs.create', false],
            'low_deck' => ['low-deck', 'admin.quotations.low-deck.create', false],
        ];
    }

    /**
     * @dataProvider quotationTypes
     */
    public function test_create_page_footer_and_cancel_without_project(
        string $type,
        string $createRoute,
        bool $isPainting
    ): void {
        $this->actingAsAdmin();

        $html = $this->get(route($createRoute))
            ->assertOk()
            ->assertSee('data-qh-header', false)
            ->getContent();

        $this->assertSame(1, substr_count($html, 'qh-actions__save'), $type.' header Save');
        $this->assertStringContainsString('data-qh-cancel="', $html);

        preg_match('/data-qh-cancel="([^"]+)"/', $html, $cancel);
        $cancelUrl = html_entity_decode($cancel[1] ?? '');
        $this->assertStringContainsString('/quotations/'.$type, $cancelUrl, $type.' unlinked Cancel stays on the type list');

        if ($isPainting) {
            $this->assertStringNotContainsString('data-qh-footer', $html);
            $this->assertStringNotContainsString('qh-sticky-footer', $html);
            $this->assertStringNotContainsString('admin-quotation-editor-footer.js', $html);
            $this->assertSame(0, substr_count($html, 'data-qh-footer-save'));

            return;
        }

        $this->assertStringContainsString('data-qh-footer', $html, $type.' sticky footer');
        preg_match_all('/data-qh-footer(?!-)/', $html, $footerMarks);
        $this->assertCount(1, $footerMarks[0], $type.' must not stack footers');
        $this->assertSame(1, substr_count($html, 'data-qh-footer-save'), $type.' one footer Save');
        $this->assertStringContainsString('data-qh-footer-amount', $html);
        $this->assertStringContainsString('admin-quotation-editor-footer.js', $html);
        $this->assertStringContainsString('qh-sticky-footer', $html);
        $this->assertMatchesRegularExpression(
            '/<(button)[^>]*(data-qh-footer-save|type="submit")[^>]*(type="submit"|data-qh-footer-save)/',
            $html
        );

        $footerPos = strpos($html, 'data-qh-footer');
        $headerPos = strpos($html, 'data-qh-header');
        $this->assertNotFalse($footerPos);
        $this->assertNotFalse($headerPos);
        $this->assertGreaterThan($headerPos, $footerPos, $type.' footer sits below the header');

        if ($type === 'standard') {
            $this->assertStringContainsString('id="sq-toolbar-grand"', $html);
            $this->assertStringNotContainsString('sq-form-actions', $html);
        }
        if ($type === 'basement') {
            $this->assertStringContainsString('id="br-sticky-total"', $html);
            $this->assertStringContainsString('id="br-next"', $html);
        }
        if ($type === 'millwork') {
            $this->assertStringContainsString('id="mw-totals-bar"', $html);
            $this->assertStringContainsString('id="mw-bar-total"', $html);
        }
    }

    /**
     * @dataProvider quotationTypes
     */
    public function test_linked_project_cancel_and_footer_amount(
        string $type,
        string $createRoute,
        bool $isPainting
    ): void {
        $this->actingAsAdmin();

        $project = ClientProject::factory()->create([
            'name' => 'Footer '.$type.' Job',
            'status' => ClientProject::STATUS_VISIT_SCHEDULED,
        ]);
        $projectUrl = route('admin.client-projects.show', $project).'#quotation';

        $html = $this->get(route($createRoute, ['project' => $project->id]))
            ->assertOk()
            ->assertSee('qh-crumb__project', false)
            ->assertSee($projectUrl, false)
            ->getContent();

        preg_match('/data-qh-cancel="([^"]+)"/', $html, $cancel);
        $cancelUrl = html_entity_decode($cancel[1] ?? '');

        if ($isPainting) {
            $this->assertStringContainsString('/quotations/painting', $cancelUrl);
            $this->assertStringNotContainsString('#quotation', $cancelUrl);
            $this->assertStringNotContainsString('data-qh-footer', $html);

            return;
        }

        $this->assertSame($projectUrl, $cancelUrl, $type.' Cancel returns to the project quotation tab');
        $this->assertStringContainsString('data-qh-footer', $html);
        $this->assertStringContainsString('data-qh-footer-amount', $html);
        $this->assertSame(1, substr_count($html, 'data-qh-footer-save'));
        $this->assertStringContainsString('CA$', $html);
    }
}

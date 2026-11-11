<?php

namespace Tests\Feature\Admin;

use App\Http\Controllers\Admin\PaintingQuotationController;
use App\Models\ClientProject;
use App\Models\ClientProjectQuotation;
use App\Models\PaintingPaintLine;
use App\Models\PaintingQuotation;
use App\Models\RoomAreaProject;
use App\Models\RoomAreaRoom;
use App\Services\PaintingQuotationCalculator;
use Tests\IntegrationTestCase;
use Tests\Support\ActsAsAdmin;
use Tests\Support\ExtractsPdfText;

class PaintingQuotationCreateHiddenTest extends IntegrationTestCase
{
    use ActsAsAdmin;
    use ExtractsPdfText;

    public function test_hub_and_room_area_omit_painting_create_entry_points(): void
    {
        $this->actingAsAdmin();
        $room = $this->makeRoom();

        $hub = $this->get(route('admin.quotations.index'))
            ->assertOk()
            ->getContent();
        $this->assertStringNotContainsString(route('admin.quotations.painting.index'), $hub);
        $this->assertStringNotContainsString(route('admin.quotations.painting.create'), $hub);
        $this->assertDoesNotMatchRegularExpression('/<h4>\s*Painting\s*<\/h4>/', $hub);

        $index = $this->get(route('admin.quotations.painting.index'))
            ->assertOk()
            ->assertSee('Painting quotation archive', false)
            ->assertSee('New painting quotations are not offered', false)
            ->assertSee('admin-info-tip', false)
            ->assertDontSee('fa-plus', false)
            ->getContent();
        $this->assertStringNotContainsString(route('admin.quotations.painting.create'), $index);
        $this->assertDoesNotMatchRegularExpression(
            '/<p class="admin-form-help"[^>]*>\s*New painting quotations are not offered/',
            $index
        );

        $roomArea = $this->get(route('admin.utilities.room-area.quote', [
            'layout' => 'room:'.$room->id,
        ]))
            ->assertOk()
            ->assertSee('Start a calculator', false)
            ->assertSee('Flooring', false)
            ->getContent();
        $this->assertStringNotContainsString(route('admin.quotations.painting.create'), $roomArea);
        $this->assertDoesNotMatchRegularExpression('/>\s*Painting\s*<\/a>/', $roomArea);
    }

    public function test_create_route_redirects_to_hub_with_notice_and_store_is_blocked(): void
    {
        $this->actingAsAdmin();

        $this->get(route('admin.quotations.painting.create'))
            ->assertRedirect(route('admin.quotations.index'));

        $this->followingRedirects()
            ->get(route('admin.quotations.painting.create'))
            ->assertOk()
            ->assertSee(PaintingQuotationController::CREATE_DISABLED_NOTICE, false)
            ->assertDontSee('New painting services quotation');

        $before = PaintingQuotation::query()->count();
        $this->post(route('admin.quotations.painting.store'), [
            'save_intent' => 'draft',
            'client_name' => 'Blocked painting create',
            'total_property_sqf' => 1200,
        ])->assertNotFound();
        $this->assertSame($before, PaintingQuotation::query()->count());
    }

    public function test_existing_painting_quotation_still_opens_edit_and_pdf(): void
    {
        $this->actingAsAdmin();

        $quote = $this->makePublishedPaintingQuote('Existing Painting Client');
        $project = ClientProject::factory()->create([
            'status' => ClientProject::STATUS_VISIT_SCHEDULED,
            'billing_type' => ClientProject::BILLING_FIXED,
            'client_name' => 'Existing Painting Client',
        ]);

        $this->get(route('admin.quotations.painting.edit', $quote))
            ->assertOk()
            ->assertSee($quote->quote_number, false)
            ->assertSee('Existing Painting Client', false)
            ->assertSee('data-qh-header', false);

        $this->post(route('admin.quotations.painting.link', $quote), [
            'client_project_id' => $project->id,
        ])->assertSessionHasNoErrors();

        $cpq = ClientProjectQuotation::query()
            ->where('source_type', ClientProjectQuotation::SOURCE_PAINTING)
            ->where('source_id', $quote->id)
            ->first();
        $this->assertNotNull($cpq);

        $this->get(route('admin.client-projects.quotation.edit', [$project, $cpq]))
            ->assertRedirect(route('admin.quotations.painting.edit', [$quote, 'return' => 'project']));

        $this->get(route('admin.search.index', ['q' => $quote->quote_number]))
            ->assertOk()
            ->assertSee($quote->quote_number, false);

        $pdf = $this->get(route('admin.client-projects.quotation.pdf', [$project, $cpq]));
        $pdf->assertOk();
        $pdfPath = storage_path('app/painting-archive-'.$quote->id.'.pdf');
        file_put_contents($pdfPath, $pdf->getContent());
        $pdfText = $this->extractPdfText($pdfPath);
        $this->assertStringContainsString('Paint', $pdfText);
        $this->assertStringContainsString(
            \App\Models\ClientProject::formatMoney((float) $quote->final_quote_total),
            $pdfText
        );
    }

    private function makePublishedPaintingQuote(string $clientName): PaintingQuotation
    {
        $paintLineId = PaintingPaintLine::query()->orderBy('id')->value('id');
        $inputs = array_merge(PaintingQuotationCalculator::defaultInputs(), [
            'total_property_sqf' => 1200,
            'paint_line_id' => $paintLineId,
        ]);
        $calc = (new PaintingQuotationCalculator)->calculate($inputs);

        return PaintingQuotation::query()->create([
            'quote_number' => PaintingQuotation::generateNumber(),
            'client_name' => $clientName,
            'is_draft' => false,
            'inputs' => $inputs,
            'calculation' => $calc,
            'final_quote_total' => round((float) ($calc['summary']['final_quote_total'] ?? 0), 2),
            'payment_installments' => 3,
            'include_debris_removal' => 0,
            'ask_reply_to_accept' => 0,
        ]);
    }

    private function makeRoom(): RoomAreaRoom
    {
        $project = RoomAreaProject::create([
            'name' => 'Hide painting project',
            'scale_pixels_per_foot' => 12,
        ]);

        return RoomAreaRoom::create([
            'room_area_project_id' => $project->id,
            'name' => 'Kitchen',
            'scale_pixels_per_foot' => 12,
            'area_sqft' => 80,
            'wall_height_ft' => 8,
            'vertices' => [
                'v' => 3,
                'groups' => [[
                    'id' => 'g1',
                    'color' => '#024768',
                    'polygons' => [[
                        'id' => 'p1',
                        'vertices' => [
                            ['x' => 0, 'y' => 0],
                            ['x' => 120, 'y' => 0],
                            ['x' => 120, 'y' => 96],
                            ['x' => 0, 'y' => 96],
                        ],
                    ]],
                ]],
                'objects' => [],
            ],
        ]);
    }
}

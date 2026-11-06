<?php

namespace Tests\Feature\QuoteAcceptance;

use App\Models\BasementQuotation;
use App\Models\ClientProject;
use App\Models\ClientProjectQuotation;
use App\Services\BasementQuotationCalculator;
use Illuminate\Support\Facades\URL;
use Tests\IntegrationTestCase;

class BasementValueTierAcceptTest extends IntegrationTestCase
{
    public function test_accept_page_requires_value_tier_and_persists_upgrade(): void
    {
        [$project, $quotation, $basement] = $this->makeBasementLinkedQuotation();
        $url = URL::signedRoute('public.quotations.accept', ['quotation' => $quotation]);

        $this->get($url)
            ->assertOk()
            ->assertSee('Invest', false)
            ->assertSee('Base', false)
            ->assertSee('Standard', false)
            ->assertSee('Upgrade', false)
            ->assertSee('name="value_tier"', false)
            ->assertSee('CAD$', false);

        $this->post($url)
            ->assertOk()
            ->assertSee('Please choose Base, Standard, or Upgrade', false);
        $this->assertFalse($quotation->fresh()->isAccepted());

        $upgradeTotal = (float) $basement->valuePackages()['upgrade']['grand_total'];
        $this->post($url, ['value_tier' => 'upgrade'])
            ->assertRedirect();

        $quotation->refresh();
        $basement->refresh();
        $this->assertTrue($quotation->isAccepted());
        $this->assertSame('upgrade', $quotation->accepted_value_tier);
        $this->assertSame('hardwood', $basement->inputs['flooring_type']);
        $this->assertEqualsWithDelta($upgradeTotal, (float) $basement->final_quote_total, 0.05);
        $this->assertEqualsWithDelta($upgradeTotal, $quotation->grandTotal(), 1.0);

        $acceptedAt = $quotation->accepted_at;
        $this->post($url, ['value_tier' => 'base'])
            ->assertOk()
            ->assertSee('Already accepted', false);
        $quotation->refresh();
        $this->assertEquals($acceptedAt?->timestamp, $quotation->fresh()->accepted_at?->timestamp);
        $this->assertSame('upgrade', $quotation->accepted_value_tier);
        $this->assertSame('hardwood', $basement->fresh()->inputs['flooring_type']);
    }

    public function test_email_template_includes_invest_columns_for_basement(): void
    {
        [$project, $quotation] = $this->makeBasementLinkedQuotation();
        $html = view('emails.client_projects.client_quotation', [
            'project' => $project,
            'quotation' => $quotation,
            'acceptUrl' => 'https://example.test/accept',
        ])->render();

        $this->assertStringContainsString('Invest', $html);
        $this->assertStringContainsString('Base', $html);
        $this->assertStringContainsString('Standard', $html);
        $this->assertStringContainsString('Upgrade', $html);
        $this->assertStringContainsString('CAD$', $html);
        $this->assertStringContainsString('Value', $html);
        $this->assertStringContainsString('Special terms', $html);
        $this->assertDoesNotMatchRegularExpression('/\blicensed\b|\bWSIB\b/i', $html);
    }

    /**
     * @return array{0: ClientProject, 1: ClientProjectQuotation, 2: BasementQuotation}
     */
    private function makeBasementLinkedQuotation(): array
    {
        $project = ClientProject::factory()->create([
            'name' => 'Basement finish',
            'client_name' => 'Ada Client',
            'client_email' => 'ada@example.test',
            'status' => ClientProject::STATUS_QUOTE_SENT,
            'billing_type' => ClientProject::BILLING_FIXED,
        ]);

        $inputs = [
            'area_sqft' => 200,
            'ceiling_height_ft' => 8,
            'flooring_type' => 'engineered',
            'flooring_sf' => 200,
            'flooring_waste_percent' => 8,
            'underlayment' => false,
            'include_demolition' => false,
            'insulate_walls' => false,
            'include_cleaning' => false,
            'include_touchups' => false,
            'detector_qty' => 0,
            'drywall_wall_sf' => 0,
            'drywall_ceiling_sf' => 0,
            'paint_wall_sf' => 0,
            'paint_ceiling_sf' => 0,
            'paint_trim_lf' => 0,
            'baseboard_lf' => 0,
            'material_waste_percent' => 0,
            'contractor_fee_percent' => 10,
            'owner_hourly_rate' => 80,
            'client_scope_included' => 'Flooring as specified.',
            'client_scope_not_included' => 'Carpet flooring.',
            'client_scope_assumptions' => 'A contractor fee of 10% applies to materials and subcontracted services, not Zanini hourly labour. The same fee also applies to written change orders.',
            'client_scope_allowances' => 'No separate cash allowances.',
        ];
        $calc = (new BasementQuotationCalculator)->calculate($inputs);
        $basement = BasementQuotation::create([
            'quote_number' => BasementQuotation::generateNumber(),
            'client_name' => $project->client_name,
            'is_draft' => false,
            'inputs' => $inputs,
            'calculation' => $calc,
            'final_quote_total' => $calc['summary']['final_quote_total'],
            'version' => 1,
            'client_project_id' => $project->id,
        ]);

        $quotation = ClientProjectQuotation::query()->create([
            'client_project_id' => $project->id,
            'title' => 'Basement quotation',
            'status' => ClientProjectQuotation::STATUS_SENT,
            'type' => ClientProjectQuotation::TYPE_QUOTATION,
            'labour' => true,
            'material' => true,
            'quotation_number' => 'QUO-2026-'.str_pad((string) random_int(1000, 9999), 4, '0', STR_PAD_LEFT),
            'source_type' => ClientProjectQuotation::SOURCE_BASEMENT,
            'source_id' => $basement->id,
            'source_snapshot' => ['quote_number' => $basement->quote_number],
            'items' => [
                [
                    'description' => 'Basement renovation (labour)',
                    'amount' => (float) $calc['summary']['labor_pre_hst'],
                    'apply_hst' => true,
                ],
                [
                    'description' => 'Basement renovation materials',
                    'amount' => (float) $calc['summary']['materials_pre_hst'],
                    'apply_hst' => true,
                ],
                [
                    'description' => 'Contractor fee 10%',
                    'amount' => (float) $calc['summary']['contractor_fee_pre_hst'],
                    'apply_hst' => true,
                ],
            ],
        ]);

        return [$project, $quotation, $basement];
    }
}

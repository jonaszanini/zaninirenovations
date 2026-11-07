<?php

namespace Tests\Unit\Services;

use App\Models\TileGroutType;
use App\Models\TileMortarType;
use App\Services\QuotationWorkDescriptionContextBuilder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class QuotationWorkDescriptionContextBuilderTest extends TestCase
{
    use RefreshDatabase;
    public function test_shed_context_uses_sq_ft_labels_and_skips_empty_size(): void
    {
        $text = (new QuotationWorkDescriptionContextBuilder)->build('shed', [
            'client_name' => 'Jason Costantini',
            'shed_type' => 'gable',
            'length_ft' => 13,
            'width_ft' => 3,
            'wall_height_ft' => 6.75,
            'roof_type' => 'weather_coat',
            'siding' => 'none',
            'foundation' => 'blocks',
            'area_label' => '39 sq ft',
        ]);

        $this->assertStringContainsString('Custom shed', $text);
        $this->assertStringContainsString('13 ft × 3 ft', $text);
        $this->assertStringContainsString('39 sq ft', $text);
        $this->assertStringContainsString('Weather coat', $text);
        $this->assertStringContainsString('Deck blocks', $text);
        $this->assertStringNotContainsString('m²', $text);
    }

    public function test_basement_demolition_lists_checked_tear_out_items(): void
    {
        $text = (new QuotationWorkDescriptionContextBuilder)->build('basement', [
            'include_demolition' => true,
            'demo_include_flooring' => false,
            'demo_include_studs' => true,
            'demo_include_drywall' => true,
            'demo_include_baseboard' => true,
        ]);

        $this->assertStringContainsString('Demolition: studs, drywall, baseboard', $text);
        $this->assertStringNotContainsString('flooring', strtolower($text));
    }

    public function test_basement_omits_unselected_trade_sections_and_inputs(): void
    {
        $builder = new QuotationWorkDescriptionContextBuilder();
        $payload = [
            'client_name' => 'Josafa',
            'area_sqft' => 454.05,
            'ceiling_height_ft' => 8,
            'scope_preset' => 'custom',
            'enabled_sections' => ['overview', 'demolition', 'drywall', 'doors_trim'],
            'include_demolition' => true,
            'demo_include_studs' => true,
            'demo_studs_lf' => 56,
            'door_qty' => 1,
            // Hidden default form inputs that should NEVER appear in context because trades are disabled:
            'insulate_walls' => true,
            'insulate_ceiling' => true,
            'soundproof_interior' => true,
            'detector_qty' => 2,
            'outlet_qty' => 10,
            'switch_qty' => 3,
            'include_cleaning' => true,
            'include_touchups' => true,
            'paint_walls' => true,
            'paint_coats' => 2,
            'flooring_type' => 'Luxury vinyl plank (LVP)',
            'ceiling_type' => 'Drywall (taped & sanded)',
            'include_bathroom' => true,
            'electrical_lines' => [
                ['description' => 'STALE PANEL UPGRADE', 'amount' => 1800, 'hst_included' => true],
            ],
            'plumbing_lines' => [
                ['description' => 'STALE ROUGH-IN', 'amount' => 900, 'hst_included' => true],
            ],
        ];

        $text = $builder->build('basement', $payload);

        // Included trades are listed
        $this->assertStringContainsString('Included trades in this quotation:', $text);
        $this->assertStringContainsString('Demolition & Preparation', $text);
        $this->assertStringContainsString('Drywall / Mud / Tape / Sand', $text);
        $this->assertStringContainsString('Doors, Trim & Baseboards', $text);

        // Excluded trades are explicitly warned
        $this->assertStringContainsString('Trades NOT included in this quotation:', $text);
        $this->assertStringContainsString('Insulation & Vapour Barrier', $text);
        $this->assertStringContainsString('Electrical', $text);
        $this->assertStringContainsString('Final Details & Cleanup', $text);
        $this->assertStringContainsString('Flooring', $text);
        $this->assertStringContainsString('Painting', $text);

        // Omitted trades are NOT in the scope description:
        $this->assertStringNotContainsString('Insulation:', $text);
        $this->assertStringNotContainsString('wall insulation', $text);
        $this->assertStringNotContainsString('Electrical scope:', $text);
        $this->assertStringNotContainsString('smoke/CO detectors', $text);
        $this->assertStringNotContainsString('detector_qty', $text);
        $this->assertStringNotContainsString('Final cleanup', $text);
        $this->assertStringNotContainsString('Painting scope:', $text);
        $this->assertStringNotContainsString('Paint coats', $text);
        $this->assertStringNotContainsString('Flooring: Luxury vinyl plank', $text);
        $this->assertStringNotContainsString('Ceiling: Drywall', $text);
        $this->assertStringNotContainsString('Bathroom: yes', $text);
        $this->assertStringNotContainsString('STALE PANEL UPGRADE', $text);
        $this->assertStringNotContainsString('STALE ROUGH-IN', $text);

        // Included items ARE present:
        $this->assertStringContainsString('Demolition:', $text);
        $this->assertStringContainsString('studs (56 lf)', $text);
        $this->assertStringContainsString('Interior doors: 1', $text);
        $this->assertStringContainsString('Drywall finish level:', $text);
    }

    public function test_trimming_omits_disabled_trim_families_even_when_quantities_present(): void
    {
        $builder = new QuotationWorkDescriptionContextBuilder();
        $payload = [
            'client_name' => 'John',
            'enabled_families' => ['floor_wall'],
            'floor_lf' => 120,
            'include_baseboard' => true,
            // Disabled families that might have values in the form:
            'door_qty' => 5,
            'window_qty' => 3,
            'crown_lf' => 80,
            'chair_rail_lf' => 40,
        ];

        $text = $builder->build('trimming', $payload);

        $this->assertStringContainsString('Included trim families: Floor to wall', $text);
        $this->assertStringContainsString('Trim families NOT included in this quote:', $text);
        $this->assertStringContainsString('Baseboards: included (120 lf)', $text);

        $this->assertStringNotContainsString('Door openings:', $text);
        $this->assertStringNotContainsString('Window openings:', $text);
        $this->assertStringNotContainsString('Crown moulding:', $text);
        $this->assertStringNotContainsString('Chair rail:', $text);
    }

    public function test_shed_omits_unselected_features(): void
    {
        $builder = new QuotationWorkDescriptionContextBuilder();
        $payload = [
            'client_name' => 'Alice',
            'shed_type' => 'gable',
            'length_ft' => 12,
            'width_ft' => 10,
            'include_electrical' => false,
            'outlet_qty' => 4,
            'include_paint' => false,
            'include_loft' => false,
            'include_ramp' => false,
            'include_plumbing' => false,
        ];

        $text = $builder->build('shed', $payload);

        $this->assertStringNotContainsString('Electrical:', $text);
        $this->assertStringNotContainsString('Exterior paint', $text);
        $this->assertStringNotContainsString('Loft:', $text);
        $this->assertStringNotContainsString('Ramp:', $text);
        $this->assertStringNotContainsString('Plumbing:', $text);
    }

    public function test_paving_omits_pricing_margins_and_zero_equipment(): void
    {
        $builder = new QuotationWorkDescriptionContextBuilder();
        $payload = [
            'client_name' => 'Bob',
            'total_area_sq_ft' => 350,
            'stone_types' => 'Holland Stone',
            'profit_margin' => 35,
            'owner_hourly_rate' => 85,
            'helper_hourly_rate' => 45,
            'mini_skid_days' => 0,
            'plate_compactor_days' => 1,
        ];

        $text = $builder->build('paving', $payload);

        $this->assertStringContainsString('Project area: 350 sq ft', $text);
        $this->assertStringContainsString('Plate compactor rental days: 1', $text);

        $this->assertStringNotContainsString('Profit margin', $text);
        $this->assertStringNotContainsString('Owner hourly rate', $text);
        $this->assertStringNotContainsString('Helper hourly rate', $text);
        $this->assertStringNotContainsString('Mini skid steer rental days', $text);
    }

    public function test_stairs_context_includes_only_checked_options(): void
    {
        $builder = new QuotationWorkDescriptionContextBuilder();
        $payload = [
            'client_name' => 'David',
            'total_steps' => 13,
            'material_type' => 'Red Oak',
            'carpet_removal' => false,
            'requires_stain' => true,
            'requires_clear_coat' => false,
        ];

        $text = $builder->build('stairs', $payload);

        $this->assertStringContainsString('Total steps: 13', $text);
        $this->assertStringContainsString('Material / wood type: Red Oak', $text);
        $this->assertStringContainsString('Staining: yes', $text);
        $this->assertStringNotContainsString('Carpet removal', $text);
        $this->assertStringNotContainsString('Clear coat', $text);
    }

    public function test_flooring_context_includes_only_checked_options(): void
    {
        $builder = new QuotationWorkDescriptionContextBuilder();
        $payload = [
            'client_name' => 'Eva',
            'square_footage' => 450,
            'flooring_type' => 'Engineered Hardwood',
            'remove_carpet' => false,
            'rescrew_subfloor' => true,
            'install_shoe_moulding' => true,
            'shoe_moulding_linear_ft' => 80,
            'install_baseboard' => true,
            'baseboard_linear_ft' => 80,
            'closet_doors' => false,
        ];

        $text = $builder->build('flooring', $payload);

        $this->assertStringContainsString('Square footage: 450 sq ft', $text);
        $this->assertStringContainsString('Subfloor re-screwing / preparation: yes', $text);
        $this->assertStringContainsString('Install shoe moulding: yes (80 lf)', $text);
        $this->assertStringContainsString('Install baseboard: yes (80 lf)', $text);
        $this->assertStringNotContainsString('Carpet removal', $text);
        $this->assertStringNotContainsString('Closet doors', $text);
    }

    public function test_millwork_context_formats_pieces_and_specifications(): void
    {
        $builder = new QuotationWorkDescriptionContextBuilder();
        $payload = [
            'client_name' => 'Charlie',
            'pieces' => [
                [
                    'piece_type' => 'lower_cabinet',
                    'quantity' => 2,
                    'width_in' => 36,
                    'height_in' => 34.5,
                    'depth_in' => 24,
                ],
            ],
            'sheet_material_type' => 'Maple veneer plywood',
            'edge_banding_type' => '0.5mm PVC',
            'finish_type' => 'Stain & clear coat',
            'include_installation' => true,
        ];

        $text = $builder->build('millwork', $payload);

        $this->assertStringContainsString('Quotation type: Custom millwork', $text);
        $this->assertStringContainsString('Lower Cabinet', $text);
        $this->assertStringContainsString('W: 36in', $text);
        $this->assertStringContainsString('H: 34.5in', $text);
        $this->assertStringContainsString('D: 24in', $text);
        $this->assertStringContainsString('Installation included: yes', $text);
    }

    public function test_tile_context_uses_mortar_and_grout_names_not_ids(): void
    {
        $mortar = TileMortarType::query()->create([
            'name' => 'MAPEI ULTRAFLEX',
            'bag_weight_lbs' => 50,
            'cost_price' => 40,
            'sort_order' => 1,
            'is_active' => true,
        ]);
        $grout = TileGroutType::query()->create([
            'name' => 'Polyblend Plus',
            'bag_weight_lbs' => 10,
            'density_factor' => 1.6,
            'cost_price' => 20,
            'sort_order' => 1,
            'is_active' => true,
        ]);

        $text = (new QuotationWorkDescriptionContextBuilder)->build('tile', [
            'mortar_type_id' => $mortar->id,
            'grout_type_id' => $grout->id,
            'trowel_size' => '1/4" x 1/4" x 1/4"',
        ]);

        $this->assertStringContainsString('Mortar: MAPEI ULTRAFLEX', $text);
        $this->assertStringContainsString('Grout: Polyblend Plus', $text);
        $this->assertStringNotContainsString('Mortar type: '.$mortar->id, $text);
        $this->assertStringNotContainsString('Grout type: '.$grout->id, $text);
        $this->assertStringNotContainsString('Type 1', $text);
    }

    public function test_from_quote_instruction_file_has_context_placeholder(): void
    {
        $instruction = require database_path('data/ai_instruction_quotation_work_description_from_quote.php');

        $this->assertStringContainsString('{{ quotation_context }}', $instruction);
        $this->assertStringContainsString('Do not mention prices', $instruction);
        $this->assertStringNotContainsString('{{ contractor_notes }}', $instruction);
    }
}

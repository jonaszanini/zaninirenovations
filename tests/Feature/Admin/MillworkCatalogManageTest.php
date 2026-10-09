<?php

namespace Tests\Feature\Admin;

use App\Models\MillworkQuotation;
use App\Models\MillworkSettings;
use App\Services\Millwork\MillworkCatalog;
use App\Services\MillworkQuotationCalculator;
use Tests\IntegrationTestCase;
use Tests\Support\ActsAsAdmin;
use Tests\Support\AssertsAdminInfoTipAria;

class MillworkCatalogManageTest extends IntegrationTestCase
{
    use ActsAsAdmin;
    use AssertsAdminInfoTipAria;

    public function test_create_page_has_manage_icons_and_converted_info_tips(): void
    {
        $this->actingAsAdmin();

        $html = $this->get(route('admin.quotations.millwork.create'))
            ->assertOk()
            ->assertSee('data-mw-manage-slot="wood_sheets"', false)
            ->assertSee('data-mw-manage-slot="edge_banding"', false)
            ->assertSee('data-mw-manage-slot="trim_stock"', false)
            ->assertSee('data-mw-manage-slot="hinges"', false)
            ->assertSee('id="mw-catalog-modal"', false)
            ->assertSee('>Cancel</button>', false)
            ->assertDontSee('cp-reply-to-accept__help', false)
            ->assertSee('cp-required', false)
            ->getContent();

        $this->assertAdminInfoTipAriaLabel($html, 'Wood sheet');
        $this->assertAdminInfoTipAriaLabel($html, 'Ask client to reply I accept');
        $this->assertStringContainsString('Sheet size defaults to 4×8 from Price configuration.', $html);
        $this->assertStringContainsString('Required. Pick Yes or No before saving or emailing.', $html);
        $this->assertStringContainsString('aria-label="Manage wood sheets"', $html);
        $this->assertStringContainsString('aria-label="Manage trim stock"', $html);
    }

    public function test_catalog_endpoints_are_admin_only(): void
    {
        $this->get(route('admin.quotations.millwork.catalog.show', ['slot' => 'wood_sheets']))
            ->assertRedirect(route('login'));
        $this->put(route('admin.quotations.millwork.catalog.update', ['slot' => 'wood_sheets']), [
            'items' => [],
        ])->assertRedirect(route('login'));
    }

    public function test_ajax_add_keeps_current_selection_and_does_not_reload_quote_fields(): void
    {
        $this->actingAsAdmin();
        $settings = MillworkSettings::current();
        $before = $settings->itemsFor(MillworkSettings::SLOT_WOOD_SHEETS);
        $keepId = (string) ($before[0]['id'] ?? '');

        $items = $before;
        $items[] = [
            'name' => 'PRC custom maple 5×5',
            'unit_price' => 88.5,
            'thickness_in' => 0.75,
            'width_in' => 60,
            'length_in' => 60,
            'material_kind' => 'plywood',
            'grade' => 'cabinet',
        ];

        $res = $this->putJson(route('admin.quotations.millwork.catalog.update', ['slot' => 'wood_sheets']), [
            'items' => $items,
        ])->assertOk()
            ->assertJsonPath('slot', 'wood_sheets');

        $ids = collect($res->json('options'))->pluck('id');
        $this->assertTrue($ids->contains($keepId));
        $this->assertTrue(
            collect($res->json('items'))->contains(fn ($row) => ($row['name'] ?? '') === 'PRC custom maple 5×5')
        );

        $create = $this->get(route('admin.quotations.millwork.create'))->assertOk()->getContent();
        $this->assertStringContainsString('PRC custom maple 5×5', $create);
        $this->assertStringContainsString($keepId, $create);
    }

    public function test_used_item_is_soft_deleted_and_saved_quote_keeps_snapshot(): void
    {
        $this->actingAsAdmin();
        $settings = MillworkSettings::current();
        $sheet = $settings->itemsFor(MillworkSettings::SLOT_WOOD_SHEETS)[0];
        $sheetId = (string) $sheet['id'];

        $this->post(route('admin.quotations.millwork.store'), [
            'save_intent' => 'draft',
            'client_name' => 'Snapshot Client',
            'sheet_id' => $sheetId,
            'pieces' => [[
                'type' => 'shelf',
                'sets' => [['qty' => 1, 'length' => 24, 'depth' => 10, 'unit' => 'in']],
            ]],
            'payment_installments' => 3,
            'include_debris_removal' => 0,
            'include_commute' => 0,
            'owner_hourly_rate' => 80,
            'helper_hourly_rate' => 40,
            'ask_reply_to_accept' => '0',
        ])->assertRedirect();

        $quote = MillworkQuotation::query()->where('client_name', 'Snapshot Client')->first();
        $this->assertNotNull($quote);
        $this->assertNotEmpty($quote->catalog_snapshot[MillworkSettings::SLOT_WOOD_SHEETS] ?? []);
        $savedTotal = (float) $quote->final_quote_total;
        $savedPrice = (float) $quote->catalog_snapshot[MillworkSettings::SLOT_WOOD_SHEETS][0]['unit_price'];

        $raised = $settings->itemsFor(MillworkSettings::SLOT_WOOD_SHEETS);
        foreach ($raised as &$row) {
            if ((string) ($row['id'] ?? '') === $sheetId) {
                $row['unit_price'] = $savedPrice + 50;
            }
        }
        unset($row);
        $this->putJson(route('admin.quotations.millwork.catalog.update', ['slot' => 'wood_sheets']), [
            'items' => $raised,
        ])->assertOk();

        $fresh = $quote->fresh();
        $this->assertEquals($savedTotal, (float) $fresh->final_quote_total);
        $this->assertEquals($savedPrice, (float) $fresh->catalog_snapshot[MillworkSettings::SLOT_WOOD_SHEETS][0]['unit_price']);

        $withSnap = MillworkQuotationCalculator::defaultInputs();
        $withSnap = array_merge($withSnap, $fresh->inputs ?? []);
        $withSnap['_catalog_snapshot'] = $fresh->catalog_snapshot;
        $withSnap['_cut_plan_version'] = $fresh->cutPlanVersion();
        $calc = (new MillworkQuotationCalculator)->calculate($withSnap, null, $fresh->cutPlanVersion());
        $this->assertEqualsWithDelta($savedTotal, (float) ($calc['summary']['final_quote_total'] ?? 0), 0.05);

        $remaining = array_values(array_filter(
            MillworkSettings::current()->itemsFor(MillworkSettings::SLOT_WOOD_SHEETS),
            fn ($row) => (string) ($row['id'] ?? '') !== $sheetId
        ));
        $this->putJson(route('admin.quotations.millwork.catalog.update', ['slot' => 'wood_sheets']), [
            'items' => $remaining,
        ])->assertOk()
            ->assertJsonPath('soft_deleted.0', $sheetId);

        $this->assertTrue(MillworkCatalog::itemIsUsedOnQuotes(MillworkSettings::SLOT_WOOD_SHEETS, $sheetId));

        $create = $this->get(route('admin.quotations.millwork.create'))->assertOk()->getContent();
        $this->assertStringNotContainsString('value="'.$sheetId.'"', $create);

        $edit = $this->get(route('admin.quotations.millwork.edit', $fresh))->assertOk()->getContent();
        $this->assertStringContainsString('value="'.$sheetId.'"', $edit);
        $this->assertEquals($savedTotal, (float) $fresh->fresh()->final_quote_total);
    }

    public function test_unused_item_is_hard_deleted(): void
    {
        $this->actingAsAdmin();
        $settings = MillworkSettings::current();
        $items = $settings->itemsFor(MillworkSettings::SLOT_TRIM);
        $items[] = ['name' => 'Temp crown', 'unit_price' => 4.25, 'profile' => 'crown', 'stock_length_ft' => 12];

        $added = $this->putJson(route('admin.quotations.millwork.catalog.update', ['slot' => 'trim_stock']), [
            'items' => $items,
        ])->assertOk()->json('items');
        $temp = collect($added)->first(fn ($row) => ($row['name'] ?? '') === 'Temp crown');
        $this->assertNotEmpty($temp['id'] ?? null);

        $without = array_values(array_filter($added, fn ($row) => ($row['name'] ?? '') !== 'Temp crown'));
        $this->putJson(route('admin.quotations.millwork.catalog.update', ['slot' => 'trim_stock']), [
            'items' => $without,
        ])->assertOk()
            ->assertJsonPath('removed.0', $temp['id']);

        $create = $this->get(route('admin.quotations.millwork.create'))->assertOk()->getContent();
        $this->assertStringNotContainsString('Temp crown', $create);
    }
}

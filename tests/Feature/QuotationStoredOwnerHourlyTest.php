<?php

namespace Tests\Feature;

use App\Models\BasementQuotation;
use App\Models\MillworkQuotation;
use App\Models\SharedCrewRate;
use App\Models\ShedQuotation;
use App\Models\TrimmingQuotation;
use App\Models\User;
use App\Services\SharedWorkCatalog;
use Illuminate\Support\Facades\Hash;
use Tests\IntegrationTestCase;

class QuotationStoredOwnerHourlyTest extends IntegrationTestCase
{
    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->create([
            'email' => 'stored-owner-rate@zanini.ca',
            'password' => Hash::make('password'),
            'role' => 'admin',
        ]);
    }

    private function setSharedOwnerRate(float $rate): void
    {
        $crew = SharedCrewRate::current();
        $crew->owner_hourly_rate = $rate;
        $crew->save();
        SharedWorkCatalog::forgetCache();
    }

    /**
     * @return array<string, mixed>
     */
    private function shedStorePayload(string $clientName, float $ownerRate = 80): array
    {
        return [
            'save_intent' => 'publish',
            'client_name' => $clientName,
            'length_ft' => 10,
            'width_ft' => 12,
            'wall_height_ft' => 8,
            'shed_type' => 'gable',
            'foundation' => 'skids',
            'payment_installments' => 3,
            'include_debris_removal' => 0,
            'include_commute' => 0,
            'owner_hourly_rate' => $ownerRate,
            'helper_hourly_rate' => 40,
            'ask_reply_to_accept' => '0',
        ];
    }

    public function test_shed_resave_keeps_total_when_shared_rate_changes_and_owner_not_reposted(): void
    {
        $this->setSharedOwnerRate(80);
        $this->actingAs($this->admin);

        $this->post(route('admin.quotations.shed.store'), $this->shedStorePayload('Stored rate shed', 80))
            ->assertRedirect();

        $quote = ShedQuotation::query()->where('client_name', 'Stored rate shed')->first();
        $this->assertNotNull($quote);
        $originalTotal = (float) $quote->final_quote_total;
        $this->assertEqualsWithDelta(80, (float) ($quote->inputs['owner_hourly_rate'] ?? 0), 0.01);

        $this->setSharedOwnerRate(65);

        $this->from(route('admin.quotations.shed.edit', $quote))
            ->put(route('admin.quotations.shed.update', $quote), [
                'save_intent' => 'publish',
                'client_name' => 'Stored rate shed',
                'length_ft' => 10,
                'width_ft' => 12,
                'wall_height_ft' => 8,
                'shed_type' => 'gable',
                'foundation' => 'skids',
                'payment_installments' => 3,
                'include_debris_removal' => 0,
                'include_commute' => 0,
                'helper_hourly_rate' => 40,
                'ask_reply_to_accept' => '0',
            ])
            ->assertRedirect(route('admin.quotations.shed.edit', $quote));

        $quote->refresh();
        $this->assertEqualsWithDelta(80, (float) ($quote->inputs['owner_hourly_rate'] ?? 0), 0.01);
        $this->assertEqualsWithDelta($originalTotal, (float) $quote->final_quote_total, 0.02);
    }

    public function test_legacy_shed_without_stored_owner_rate_resaves_with_eighty_five_not_shared(): void
    {
        $this->setSharedOwnerRate(73.25);
        $this->actingAs($this->admin);

        $quote = ShedQuotation::query()->create([
            'quote_number' => 'SH-LEG-'.random_int(1000, 9999),
            'client_name' => 'Legacy shed',
            'is_draft' => true,
            'inputs' => [
                'length_ft' => 10,
                'width_ft' => 12,
                'wall_height_ft' => 8,
                'shed_type' => 'gable',
                'foundation' => 'skids',
                'helper_hourly_rate' => 40,
            ],
            'calculation' => [],
            'final_quote_total' => 0,
        ]);

        $this->from(route('admin.quotations.shed.edit', $quote))
            ->put(route('admin.quotations.shed.update', $quote), [
                'save_intent' => 'draft',
                'client_name' => 'Legacy shed',
                'length_ft' => 10,
                'width_ft' => 12,
                'wall_height_ft' => 8,
                'shed_type' => 'gable',
                'foundation' => 'skids',
                'payment_installments' => 1,
                'include_debris_removal' => 0,
                'include_commute' => 0,
                'helper_hourly_rate' => 40,
                'ask_reply_to_accept' => '0',
            ]);

        $quote->refresh();
        $this->assertEqualsWithDelta(85, (float) ($quote->inputs['owner_hourly_rate'] ?? 0), 0.01);
    }

    public function test_legacy_basement_without_stored_owner_rate_uses_eighty_five(): void
    {
        $this->setSharedOwnerRate(73.25);
        $this->actingAs($this->admin);

        $quote = BasementQuotation::query()->create([
            'quote_number' => 'BR-LEG-'.random_int(1000, 9999),
            'client_name' => 'Legacy basement',
            'is_draft' => true,
            'inputs' => [
                'area_sqft' => 850,
                'ceiling_height_ft' => 8,
                'room_count' => 3,
                'helper_hourly_rate' => 40,
            ],
            'calculation' => [],
            'final_quote_total' => 0,
        ]);

        $this->from(route('admin.quotations.basement.edit', $quote))
            ->put(route('admin.quotations.basement.update', $quote), [
                'save_intent' => 'draft',
                'client_name' => 'Legacy basement',
                'area_sqft' => 850,
                'ceiling_height_ft' => 8,
                'room_count' => 3,
                'helper_hourly_rate' => 40,
                'payment_installments' => 1,
                'include_commute' => 0,
                'include_debris_removal' => 0,
                'ask_reply_to_accept' => '0',
            ]);

        $quote->refresh();
        $this->assertEqualsWithDelta(85, (float) ($quote->inputs['owner_hourly_rate'] ?? 0), 0.01);
    }

    public function test_legacy_trimming_without_stored_owner_rate_uses_eighty_five(): void
    {
        $this->setSharedOwnerRate(73.25);
        $this->actingAs($this->admin);

        $quote = TrimmingQuotation::query()->create([
            'quote_number' => 'TM-LEG-'.random_int(1000, 9999),
            'client_name' => 'Legacy trim',
            'is_draft' => true,
            'inputs' => [
                'enabled_families' => ['floor_wall'],
                'floor_lf' => 80,
                'helper_hourly_rate' => 40,
            ],
            'calculation' => [],
            'final_quote_total' => 0,
        ]);

        $this->from(route('admin.quotations.trimming.edit', $quote))
            ->put(route('admin.quotations.trimming.update', $quote), [
                'save_intent' => 'draft',
                'client_name' => 'Legacy trim',
                'enabled_families' => ['floor_wall'],
                'floor_lf' => 80,
                'helper_hourly_rate' => 40,
                'payment_installments' => 1,
                'include_commute' => 0,
                'include_debris_removal' => 0,
                'ask_reply_to_accept' => '0',
            ]);

        $quote->refresh();
        $this->assertEqualsWithDelta(85, (float) ($quote->inputs['owner_hourly_rate'] ?? 0), 0.01);
    }

    public function test_legacy_millwork_without_stored_owner_rate_uses_eighty_five(): void
    {
        $this->setSharedOwnerRate(73.25);
        $this->actingAs($this->admin);

        $quote = MillworkQuotation::query()->create([
            'quote_number' => 'MW-LEG-'.random_int(1000, 9999),
            'client_name' => 'Legacy millwork',
            'is_draft' => true,
            'inputs' => [
                'pieces' => [[
                    'type' => 'shelf',
                    'sets' => [['qty' => 1, 'length' => 24, 'depth' => 10, 'unit' => 'in']],
                ]],
                'helper_hourly_rate' => 40,
            ],
            'calculation' => [],
            'final_quote_total' => 0,
        ]);

        $this->from(route('admin.quotations.millwork.edit', $quote))
            ->put(route('admin.quotations.millwork.update', $quote), [
                'save_intent' => 'draft',
                'client_name' => 'Legacy millwork',
                'helper_hourly_rate' => 40,
                'payment_installments' => 1,
                'include_commute' => 0,
                'include_debris_removal' => 0,
                'ask_reply_to_accept' => '0',
                'pieces' => [[
                    'type' => 'shelf',
                    'sets' => [['qty' => 1, 'length' => 24, 'depth' => 10, 'unit' => 'in']],
                ]],
            ]);

        $quote->refresh();
        $this->assertEqualsWithDelta(85, (float) ($quote->inputs['owner_hourly_rate'] ?? 0), 0.01);
    }
}

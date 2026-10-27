<?php

namespace Tests\Feature;

use App\Models\SharedCrewRate;
use App\Models\User;
use App\Services\SharedWorkCatalog;
use Illuminate\Support\Facades\Hash;
use Tests\IntegrationTestCase;

class QuotationDefaultOwnerHourlyTest extends IntegrationTestCase
{
    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->create([
            'email' => 'owner-rate-default@zanini.ca',
            'password' => Hash::make('password'),
            'role' => 'admin',
        ]);

        $crew = SharedCrewRate::current();
        $crew->owner_hourly_rate = 73.25;
        $crew->save();
        SharedWorkCatalog::forgetCache();
        $this->assertEqualsWithDelta(73.25, SharedWorkCatalog::ownerHourlyRate(), 0.001);
    }

    public function test_standard_create_defaults_labour_hourly_to_shared_owner_rate(): void
    {
        $html = $this->actingAs($this->admin)
            ->get(route('admin.quotations.standard.create'))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('name="works[0][labour_hourly_rate]"', $html);
        $this->assertMatchesRegularExpression(
            '/name="works\[0\]\[labour_hourly_rate\]"[^>]*value="73\.25"/',
            $html
        );
    }

    public function test_basement_create_defaults_owner_rate_to_shared_owner_rate(): void
    {
        $html = $this->actingAs($this->admin)
            ->get(route('admin.quotations.basement.create'))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('id="br-owner-rate"', $html);
        $this->assertMatchesRegularExpression(
            '/id="br-owner-rate"[^>]*value="73\.25"/',
            $html
        );
    }

    public function test_millwork_create_defaults_owner_rate_to_shared_owner_rate(): void
    {
        $html = $this->actingAs($this->admin)
            ->get(route('admin.quotations.millwork.create'))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('id="mw-owner-rate"', $html);
        $this->assertMatchesRegularExpression(
            '/id="mw-owner-rate"[^>]*value="73\.25"/',
            $html
        );
    }

    public function test_trimming_create_defaults_hidden_owner_rate_to_shared_owner_rate(): void
    {
        $html = $this->actingAs($this->admin)
            ->get(route('admin.quotations.trimming.create'))
            ->assertOk()
            ->getContent();

        $this->assertMatchesRegularExpression(
            '/name="owner_hourly_rate"[^>]*value="73\.25"/',
            $html
        );
    }

    public function test_paving_create_defaults_owner_rate_to_shared_owner_rate(): void
    {
        $html = $this->actingAs($this->admin)
            ->get(route('admin.quotations.paving.create'))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('id="pq-owner"', $html);
        $this->assertMatchesRegularExpression(
            '/id="pq-owner"[^>]*value="73\.25"/',
            $html
        );
    }

    public function test_shed_create_preview_uses_shared_owner_rate_for_new_quotes(): void
    {
        $html = $this->actingAs($this->admin)
            ->get(route('admin.quotations.shed.create'))
            ->assertOk()
            ->getContent();

        $this->assertMatchesRegularExpression(
            '/"owner_hourly_rate":\s*73\.25/',
            $html
        );
    }
}

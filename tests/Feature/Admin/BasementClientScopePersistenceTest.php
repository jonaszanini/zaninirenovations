<?php

namespace Tests\Feature\Admin;

use App\Models\BasementQuotation;
use Tests\IntegrationTestCase;
use Tests\Support\ActsAsAdmin;

class BasementClientScopePersistenceTest extends IntegrationTestCase
{
    use ActsAsAdmin;

    public function test_second_editing_session_keeps_custom_scope_and_marks_fields_dirty(): void
    {
        $this->actingAsAdmin();

        $payload = $this->publishPayload([
            'client_name' => 'Scope Persist Client',
            'client_scope_included' => 'SESSION CUSTOM INCLUDED',
            'client_scope_not_included' => 'SESSION CUSTOM NOT INCLUDED',
            'client_scope_assumptions' => 'SESSION CUSTOM ASSUMPTIONS',
            'client_scope_allowances' => 'SESSION CUSTOM ALLOWANCES',
        ]);

        $store = $this->post(route('admin.quotations.basement.store'), $payload);
        $quote = BasementQuotation::query()->where('client_name', 'Scope Persist Client')->first();
        $this->assertNotNull($quote);
        $store->assertRedirect(route('admin.quotations.basement.edit', $quote));

        $editHtml = $this->get(route('admin.quotations.basement.edit', $quote))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('SESSION CUSTOM INCLUDED', $editHtml);
        $this->assertStringContainsString('SESSION CUSTOM NOT INCLUDED', $editHtml);
        $this->assertStringContainsString('SESSION CUSTOM ASSUMPTIONS', $editHtml);
        $this->assertStringContainsString('SESSION CUSTOM ALLOWANCES', $editHtml);
        $this->assertMatchesRegularExpression(
            '/<textarea[^>]*name="client_scope_included"[^>]*data-br-scope-dirty="1"/s',
            $editHtml
        );
        $this->assertMatchesRegularExpression(
            '/<textarea[^>]*name="client_scope_not_included"[^>]*data-br-scope-dirty="1"/s',
            $editHtml
        );
        $this->assertMatchesRegularExpression(
            '/<textarea[^>]*name="client_scope_assumptions"[^>]*data-br-scope-dirty="1"/s',
            $editHtml
        );
        $this->assertMatchesRegularExpression(
            '/<textarea[^>]*name="client_scope_allowances"[^>]*data-br-scope-dirty="1"/s',
            $editHtml
        );
        $this->assertMatchesRegularExpression(
            '/<textarea[^>]*name="client_scope_included"[^>]*data-br-scope-persisted="1"/s',
            $editHtml
        );

        $this->put(route('admin.quotations.basement.update', $quote), array_merge($payload, [
            'area_sqft' => 900,
            'room_count' => 4,
        ]))->assertRedirect(route('admin.quotations.basement.edit', $quote));

        $quote->refresh();
        $inputs = is_array($quote->inputs) ? $quote->inputs : [];
        $this->assertEqualsWithDelta(900.0, (float) $inputs['area_sqft'], 0.01);
        $this->assertSame('SESSION CUSTOM INCLUDED', $inputs['client_scope_included'] ?? null);
        $this->assertSame('SESSION CUSTOM NOT INCLUDED', $inputs['client_scope_not_included'] ?? null);
        $this->assertSame('SESSION CUSTOM ASSUMPTIONS', $inputs['client_scope_assumptions'] ?? null);
        $this->assertSame('SESSION CUSTOM ALLOWANCES', $inputs['client_scope_allowances'] ?? null);

        $secondEdit = $this->get(route('admin.quotations.basement.edit', $quote))
            ->assertOk()
            ->getContent();
        $this->assertStringContainsString('SESSION CUSTOM INCLUDED', $secondEdit);
        $this->assertStringContainsString('SESSION CUSTOM NOT INCLUDED', $secondEdit);
        $this->assertStringContainsString('SESSION CUSTOM ASSUMPTIONS', $secondEdit);
        $this->assertStringContainsString('SESSION CUSTOM ALLOWANCES', $secondEdit);
        $this->assertMatchesRegularExpression(
            '/<textarea[^>]*name="client_scope_included"[^>]*data-br-scope-dirty="1"/s',
            $secondEdit
        );
    }

    public function test_create_form_does_not_lock_empty_generated_scope(): void
    {
        $this->actingAsAdmin();

        $html = $this->get(route('admin.quotations.basement.create'))
            ->assertOk()
            ->getContent();

        $this->assertDoesNotMatchRegularExpression(
            '/<textarea[^>]*name="client_scope_included"[^>]*data-br-scope-dirty="1"/s',
            $html
        );
        $this->assertDoesNotMatchRegularExpression(
            '/<textarea[^>]*name="client_scope_included"[^>]*data-br-scope-persisted="1"/s',
            $html
        );
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function publishPayload(array $overrides = []): array
    {
        return array_merge([
            'save_intent' => 'publish',
            'client_name' => 'Scope Persist Client',
            'area_sqft' => 850,
            'ceiling_height_ft' => 8,
            'room_count' => 3,
            'payment_installments' => 3,
            'include_commute' => 0,
            'include_debris_removal' => 0,
            'owner_hourly_rate' => 80,
            'helper_hourly_rate' => 40,
            'ask_reply_to_accept' => '0',
            'contractor_fee_percent' => 10,
        ], $overrides);
    }
}

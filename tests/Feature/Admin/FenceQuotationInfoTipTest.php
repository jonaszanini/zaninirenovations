<?php

namespace Tests\Feature\Admin;

use App\Models\FenceQuotation;
use Tests\IntegrationTestCase;
use Tests\Support\ActsAsAdmin;

class FenceQuotationInfoTipTest extends IntegrationTestCase
{
    use ActsAsAdmin;

    public function test_fence_edit_page_renders_info_tip_buttons_with_aria(): void
    {
        $this->actingAsAdmin();

        $quote = FenceQuotation::query()->create([
            'quote_number' => 'FENCE-INFO-TIP-'.uniqid(),
            'client_name' => 'Info Tip Client',
            'sides' => [['length_ft' => 24]],
            'calculation' => ['materials' => [], 'totals' => []],
        ]);

        $response = $this->get(route('admin.quotations.fence.edit', $quote));

        $response->assertOk();
        $response->assertSee('aria-label="About Board style"', false);
        $response->assertSee('aria-label="About Gates"', false);
        $response->assertSee('aria-label="About Labour rate"', false);
        $response->assertSee('aria-expanded="false"', false);
        $response->assertSee('aria-controls="', false);
        $response->assertSee('aria-describedby="', false);
        $response->assertSee('role="tooltip"', false);
        $response->assertSee('Clear span between posts is at most the max spacing', false);
        $response->assertSee('Tick “Gate goes here” on a run', false);
        $response->assertSee('First gate is included in the per-foot rate', false);
        $response->assertSee('Preview updates as you type', false);
        $response->assertDontSee('<p class="admin-form-help">Clear span between posts', false);
        $response->assertDontSee('<p class="admin-form-help">Tick “Gate goes here”', false);
        $response->assertDontSee('<p class="admin-form-help">Length in feet', false);
    }
}

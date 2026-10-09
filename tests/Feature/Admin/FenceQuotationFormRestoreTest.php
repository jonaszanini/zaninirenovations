<?php

namespace Tests\Feature\Admin;

use Tests\IntegrationTestCase;
use Tests\Support\ActsAsAdmin;

class FenceQuotationFormRestoreTest extends IntegrationTestCase
{
    use ActsAsAdmin;

    public function test_old_side_flags_restore_after_validation_error(): void
    {
        $this->actingAsAdmin();

        $response = $this
            ->from(route('admin.quotations.fence.create'))
            ->post(route('admin.quotations.fence.store'), [
                'client_name' => '',
                'fence_height_ft' => 6,
                'board_style' => 'standard',
                'gate_count' => 1,
                'labour_rate_per_ft' => 55,
                'labour_rate_8ft_per_ft' => 60,
                'payment_installments' => 1,
                'include_commute' => '0',
                'include_debris_removal' => '0',
                'sides' => [
                    [
                        'length_ft' => '20.25',
                        'already_have_post_left' => '0',
                        'already_have_post_right' => '0',
                        'gate_goes_here' => '0',
                        'connected_to_previous' => '0',
                        'gate_preset' => '4_single',
                    ],
                    [
                        'length_ft' => '16',
                        'already_have_post_left' => '0',
                        'already_have_post_right' => '0',
                        'gate_goes_here' => '1',
                        'connected_to_previous' => '0',
                        'gate_preset' => '4_single',
                    ],
                ],
            ]);

        $response->assertRedirect(route('admin.quotations.fence.create'));
        $response->assertSessionHasErrors('client_name');

        $page = $this->get(route('admin.quotations.fence.create'));
        $page->assertOk();
        $html = $page->getContent();

        $this->assertMatchesRegularExpression(
            '/var initialSides = (\[[\s\S]*?\]);/',
            $html
        );
        preg_match('/var initialSides = (\[[\s\S]*?\]);/', $html, $m);
        $sides = json_decode($m[1], true);
        $this->assertIsArray($sides);
        $this->assertCount(2, $sides);

        $this->assertFalse($sides[0]['already_have_post_left']);
        $this->assertFalse($sides[0]['already_have_post_right']);
        $this->assertFalse($sides[0]['gate_goes_here']);
        $this->assertFalse($sides[1]['already_have_post_left']);
        $this->assertFalse($sides[1]['already_have_post_right']);
        $this->assertTrue($sides[1]['gate_goes_here']);
        $this->assertFalse($sides[1]['connected_to_previous']);
        $this->assertSame(20.25, (float) $sides[0]['length_ft']);
    }
}

<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\ActsAsAdmin;
use Tests\TestCase;

/**
 * Template for new admin feature / HTTP flow tests.
 *
 * 1. Copy to tests/Feature/YourArea/YourFeatureTest.php
 * 2. Use ActsAsAdmin for authenticated routes
 * 3. Use Storage::fake / Mail::fake / Http::fake for side effects
 */
class NewFeatureFlowTest extends TestCase
{
    use ActsAsAdmin;
    use RefreshDatabase;

    public function test_guest_is_denied(): void
    {
        $this->get('/admin/your-route')
            ->assertRedirect(route('login'));
    }

    public function test_authenticated_user_can_access_flow(): void
    {
        $this->actingAsAdmin();

        $this->get('/admin/your-route')
            ->assertOk();
    }
}

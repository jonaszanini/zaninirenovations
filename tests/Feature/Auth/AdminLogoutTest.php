<?php

namespace Tests\Feature\Auth;

use Tests\Support\ActsAsAdmin;
use Tests\IntegrationTestCase;

class AdminLogoutTest extends IntegrationTestCase
{
    use ActsAsAdmin;

    public function test_authenticated_user_can_logout(): void
    {
        $this->actingAsAdmin();

        $this->post(route('admin.logout'))
            ->assertRedirect(route('login'));

        $this->assertGuest();
    }

    public function test_guest_cannot_logout(): void
    {
        $this->post(route('admin.logout'))
            ->assertRedirect(route('login'));
    }
}

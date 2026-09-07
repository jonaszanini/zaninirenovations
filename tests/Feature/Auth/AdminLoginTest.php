<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Tests\Support\ActsAsAdmin;
use Tests\IntegrationTestCase;

class AdminLoginTest extends IntegrationTestCase
{
    use ActsAsAdmin;

    public function test_login_screen_is_available_to_guests(): void
    {
        $this->get(route('login'))
            ->assertOk()
            ->assertViewIs('admin.auth.login');
    }

    public function test_user_can_login_with_valid_credentials(): void
    {
        $user = User::factory()->create([
            'email' => 'admin@example.com',
        ]);

        $response = $this->post(route('login'), [
            'email' => 'admin@example.com',
            'password' => 'password',
        ]);

        $response->assertRedirect(route('admin.dashboard'));
        $this->assertAuthenticatedAs($user);
        $response->assertCookie(\Illuminate\Support\Facades\Auth::guard()->getRecallerName());
    }

    public function test_login_fails_with_invalid_credentials(): void
    {
        User::factory()->create([
            'email' => 'admin@example.com',
        ]);

        $this->from(route('login'))
            ->post(route('login'), [
                'email' => 'admin@example.com',
                'password' => 'wrong-password',
            ])
            ->assertRedirect(route('login'))
            ->assertSessionHasErrors('email');

        $this->assertGuest();
    }

    public function test_login_requires_email_and_password(): void
    {
        $this->post(route('login'), [])
            ->assertSessionHasErrors(['email', 'password']);
    }

    public function test_authenticated_users_are_redirected_from_login_page(): void
    {
        $this->actingAsAdmin();

        $this->get(route('login'))
            ->assertRedirect(route('admin.dashboard'));
    }
}

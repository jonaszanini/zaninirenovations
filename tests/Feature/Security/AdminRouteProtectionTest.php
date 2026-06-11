<?php

namespace Tests\Feature\Security;

use Tests\IntegrationTestCase;

class AdminRouteProtectionTest extends IntegrationTestCase
{

    /**
     * @dataProvider protectedAdminRoutesProvider
     */
    public function test_guest_is_redirected_to_login(string $method, string $uri): void
    {
        $this->call($method, $uri)->assertRedirect(route('login'));
    }

    public static function protectedAdminRoutesProvider(): array
    {
        return [
            'dashboard' => ['GET', '/dashboard'],
            'materials index' => ['GET', '/admin/materials'],
            'client projects index' => ['GET', '/admin/client-projects'],
            'fence config' => ['GET', '/admin/quotations/fence/config'],
        ];
    }
}

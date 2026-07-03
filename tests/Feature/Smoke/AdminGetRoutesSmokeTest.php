<?php

namespace Tests\Feature\Smoke;

use Tests\IntegrationTestCase;
use Tests\Support\ActsAsAdmin;
use Tests\Support\RouteSmokeHelper;

class AdminGetRoutesSmokeTest extends IntegrationTestCase
{
    use ActsAsAdmin;

    public function test_authenticated_admin_get_routes_do_not_return_server_errors(): void
    {
        $this->actingAsAdmin();

        $helper = new RouteSmokeHelper;
        $failures = [];

        foreach ($helper->getGetRoutes(adminOnly: true, publicOnly: false) as $entry) {
            $response = $this->get($entry['url']);
            $status = $response->getStatusCode();

            if ($status >= 500) {
                $failures[] = RouteSmokeHelper::formatFailure($entry['route'], $entry['url'], $response);
            }
        }

        $this->assertSame([], $failures, "Server errors on admin routes:\n".implode("\n", $failures));
    }
}

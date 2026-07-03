<?php

namespace Tests\Feature\Smoke;

use Tests\IntegrationTestCase;
use Tests\Support\RouteSmokeHelper;

class PublicGetRoutesSmokeTest extends IntegrationTestCase
{
    public function test_public_get_routes_do_not_return_server_errors(): void
    {
        $helper = new RouteSmokeHelper;
        $failures = [];

        foreach ($helper->getGetRoutes(adminOnly: false, publicOnly: true) as $entry) {
            $response = $this->get($entry['url']);
            $status = $response->getStatusCode();

            if ($status >= 500) {
                $failures[] = RouteSmokeHelper::formatFailure($entry['route'], $entry['url'], $response);
            }
        }

        $this->assertSame([], $failures, "Server errors on public routes:\n".implode("\n", $failures));
    }
}

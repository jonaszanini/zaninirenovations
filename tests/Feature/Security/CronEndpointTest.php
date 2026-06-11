<?php

namespace Tests\Feature\Security;

use Tests\TestCase;

class CronEndpointTest extends TestCase
{
    public function test_cron_endpoint_is_hidden_when_token_not_configured(): void
    {
        config(['cron.http_token' => '']);

        $this->get(route('cron.run'))
            ->assertNotFound();
    }

    public function test_cron_endpoint_rejects_invalid_token(): void
    {
        config(['cron.http_token' => 'expected-secret']);

        $this->get(route('cron.run', ['token' => 'wrong']))
            ->assertNotFound();
    }
}

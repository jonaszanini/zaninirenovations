<?php

namespace Tests\Feature;

use Tests\IntegrationTestCase;

class ExampleTest extends IntegrationTestCase
{
    public function test_the_application_returns_a_successful_response(): void
    {
        $response = $this->get('/');

        $response->assertStatus(200);
    }
}


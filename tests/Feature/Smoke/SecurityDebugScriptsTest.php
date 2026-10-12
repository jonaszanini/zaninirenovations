<?php

namespace Tests\Feature\Smoke;

use Tests\IntegrationTestCase;

class SecurityDebugScriptsTest extends IntegrationTestCase
{
    /**
     * @dataProvider debugScriptProvider
     */
    public function test_debug_scripts_are_blocked(string $path): void
    {
        $response = $this->get($path);

        $this->assertNotEquals(
            200,
            $response->getStatusCode(),
            "Security: {$path} must not return HTTP 200"
        );
    }

    public static function debugScriptProvider(): array
    {
        return [
            'test.php' => ['/test.php'],
            'info.php' => ['/info.php'],
            'phpinfo.php' => ['/phpinfo.php'],
        ];
    }
}

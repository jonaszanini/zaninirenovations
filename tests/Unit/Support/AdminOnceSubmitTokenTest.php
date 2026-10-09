<?php

namespace Tests\Unit\Support;

use App\Support\AdminOnceSubmitToken;
use Illuminate\Http\Request;
use Tests\TestCase;

class AdminOnceSubmitTokenTest extends TestCase
{
    public function test_missing_token_is_allowed(): void
    {
        $this->assertTrue(AdminOnceSubmitToken::claim(Request::create('/', 'POST')));
    }

    public function test_same_token_is_accepted_once(): void
    {
        $request = Request::create('/', 'POST', [
            AdminOnceSubmitToken::FIELD => bin2hex(random_bytes(16)),
        ]);

        $this->assertTrue(AdminOnceSubmitToken::claim($request));
        $this->assertFalse(AdminOnceSubmitToken::claim($request));
    }

    public function test_issue_returns_a_32_char_hex_token(): void
    {
        $token = AdminOnceSubmitToken::issue();

        $this->assertMatchesRegularExpression('/^[0-9a-f]{32}$/', $token);
        $this->assertNotSame($token, AdminOnceSubmitToken::issue());
    }
}

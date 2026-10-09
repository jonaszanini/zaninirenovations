<?php

namespace Tests\Unit\Support;

use App\Support\AdminOnceSubmitToken;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class AdminOnceSubmitTokenTest extends TestCase
{
    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

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

    public function test_used_token_cache_entry_expires_after_ttl(): void
    {
        Carbon::setTestNow('2026-10-09 12:00:00');

        $token = bin2hex(random_bytes(16));
        $request = Request::create('/', 'POST', [
            AdminOnceSubmitToken::FIELD => $token,
        ]);

        $this->assertTrue(AdminOnceSubmitToken::claim($request));
        $this->assertTrue(Cache::has(AdminOnceSubmitToken::cacheKey($token)));
        $this->assertFalse(AdminOnceSubmitToken::claim($request));

        Carbon::setTestNow(now()->addSeconds(AdminOnceSubmitToken::TTL_SECONDS + 1));

        $this->assertFalse(Cache::has(AdminOnceSubmitToken::cacheKey($token)));
        $this->assertTrue(AdminOnceSubmitToken::claim($request));
    }

    public function test_replay_message_is_neutral_until_the_first_create_succeeds(): void
    {
        $token = bin2hex(random_bytes(16));
        $request = Request::create('/', 'POST', [
            AdminOnceSubmitToken::FIELD => $token,
        ], [], [], ['HTTP_ACCEPT' => 'application/json']);
        $request->headers->set('Accept', 'application/json');

        $this->assertTrue(AdminOnceSubmitToken::claim($request));
        $this->assertFalse(AdminOnceSubmitToken::firstCreateSucceeded($request));

        $pending = AdminOnceSubmitToken::replayResponse($request);
        $this->assertInstanceOf(JsonResponse::class, $pending);
        $this->assertSame(409, $pending->status());
        $this->assertSame('This form was already submitted.', $pending->getData(true)['message']);

        AdminOnceSubmitToken::rememberCreated($request);
        $this->assertTrue(AdminOnceSubmitToken::firstCreateSucceeded($request));

        $created = AdminOnceSubmitToken::replayResponse($request);
        $this->assertSame(
            'This form was already submitted. No second record was created.',
            $created->getData(true)['message']
        );
    }

    public function test_replay_input_keeps_user_fields_and_drops_tokens(): void
    {
        $request = Request::create('/', 'POST', [
            AdminOnceSubmitToken::FIELD => bin2hex(random_bytes(16)),
            '_hotkey_save' => '1',
            '_hotkey_email_prior' => '0',
            '_token' => 'csrf-token',
            'name' => 'Kept client name',
            'client_name' => 'Pat',
        ]);

        $input = AdminOnceSubmitToken::inputForReplay($request);

        $this->assertSame('Kept client name', $input['name']);
        $this->assertSame('Pat', $input['client_name']);
        $this->assertArrayNotHasKey(AdminOnceSubmitToken::FIELD, $input);
        $this->assertArrayNotHasKey('_hotkey_save', $input);
        $this->assertArrayNotHasKey('_hotkey_email_prior', $input);
        $this->assertArrayNotHasKey('_token', $input);
    }
}

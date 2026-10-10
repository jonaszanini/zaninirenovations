<?php

namespace App\Support;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

/**
 * One-time create-form token. The first store() that presents a given
 * value wins; a repeat POST (second Ctrl+S, or Ctrl+S after a slow
 * mouse submit) cannot create another record.
 *
 * Used entries expire via TTL so the cache does not keep them forever.
 */
class AdminOnceSubmitToken
{
    public const FIELD = '_submit_once';

    public const TTL_SECONDS = 3600;

    public static function issue(): string
    {
        return bin2hex(random_bytes(16));
    }

    /**
     * Claim the posted token. A missing token is allowed so existing
     * clients and tests keep working; a replayed token is rejected.
     */
    public static function claim(Request $request): bool
    {
        $token = self::tokenFrom($request);
        if ($token === '') {
            return true;
        }

        return Cache::add(self::cacheKey($token), ['created' => false], now()->addSeconds(self::TTL_SECONDS));
    }

    public static function rememberCreated(Request $request): void
    {
        $token = self::tokenFrom($request);
        if ($token === '') {
            return;
        }

        Cache::put(self::cacheKey($token), ['created' => true], now()->addSeconds(self::TTL_SECONDS));
    }

    public static function firstCreateSucceeded(Request $request): bool
    {
        $token = self::tokenFrom($request);
        if ($token === '') {
            return false;
        }

        $payload = Cache::get(self::cacheKey($token));

        return is_array($payload) && ! empty($payload['created']);
    }

    public static function replayResponse(Request $request): RedirectResponse|JsonResponse
    {
        $message = self::firstCreateSucceeded($request)
            ? 'This form was already submitted. No second record was created.'
            : 'This form was already submitted.';

        if ($request->expectsJson()) {
            return response()->json([
                'ok' => false,
                'message' => $message,
            ], 409);
        }

        $fallback = url()->previous();
        if (! is_string($fallback) || $fallback === '') {
            $fallback = route('admin.client-projects.index');
        }

        return redirect()
            ->to($fallback)
            ->withInput(self::inputForReplay($request))
            ->with('warning', $message);
    }

    public static function cacheKey(string $token): string
    {
        return 'admin_once_submit:'.hash('sha256', $token);
    }

    /**
     * @return array<string, mixed>
     */
    public static function inputForReplay(Request $request): array
    {
        $input = $request->except([
            self::FIELD,
            '_hotkey_save',
            '_hotkey_email_prior',
            '_token',
        ]);

        if ($request->boolean('_hotkey_save') && $request->exists('_hotkey_email_prior')) {
            $input['send_client_email'] = $request->boolean('_hotkey_email_prior') ? '1' : '0';
        }

        return $input;
    }

    private static function tokenFrom(Request $request): string
    {
        return trim((string) $request->input(self::FIELD, ''));
    }
}

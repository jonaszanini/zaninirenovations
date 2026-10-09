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
 */
class AdminOnceSubmitToken
{
    public const FIELD = '_submit_once';

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
        $token = trim((string) $request->input(self::FIELD, ''));
        if ($token === '') {
            return true;
        }

        return Cache::add(self::cacheKey($token), 1, now()->addDay());
    }

    public static function replayResponse(Request $request): RedirectResponse|JsonResponse
    {
        $message = 'This form was already submitted. No second record was created.';

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

        return redirect()->to($fallback)->with('warning', $message);
    }

    public static function cacheKey(string $token): string
    {
        return 'admin_once_submit:'.hash('sha256', $token);
    }
}

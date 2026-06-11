<?php

namespace App\Services;

use App\Support\Logging\AppLogger;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class BlogCommentCaptcha
{
    public function mode(): string
    {
        $mode = strtolower((string) config('blog.comment_captcha', 'math'));

        if (in_array($mode, ['recaptcha_v2', 'recaptcha_v3'], true)
            && filled(config('blog.recaptcha_site_key'))
            && filled(config('blog.recaptcha_secret_key'))) {
            return $mode;
        }

        return 'math';
    }

    /** @return array{a: int, b: int, token: string} */
    public function generateMathChallenge(): array
    {
        $a = random_int(2, 12);
        $b = random_int(2, 12);
        $token = Str::random(32);
        session([
            'blog_comment_captcha' => [
                'token' => $token,
                'answer' => $a + $b,
            ],
        ]);

        return ['a' => $a, 'b' => $b, 'token' => $token];
    }

    public function validate(Request $request): bool
    {
        $mode = $this->mode();

        if ($mode === 'recaptcha_v2') {
            return $this->verifyRecaptcha((string) $request->input('g-recaptcha-response', ''));
        }

        if ($mode === 'recaptcha_v3') {
            return $this->verifyRecaptcha((string) $request->input('g-recaptcha-response', ''), 0.5);
        }

        return $this->validateMath($request);
    }

    protected function validateMath(Request $request): bool
    {
        $stored = session('blog_comment_captcha');
        session()->forget('blog_comment_captcha');

        if (! is_array($stored) || ($stored['token'] ?? '') !== (string) $request->input('captcha_token')) {
            return false;
        }

        return (int) $request->input('captcha_answer') === (int) ($stored['answer'] ?? -1);
    }

    protected function verifyRecaptcha(string $response, ?float $minScore = null): bool
    {
        if ($response === '') {
            return false;
        }

        $secret = config('blog.recaptcha_secret_key');
        if (! filled($secret)) {
            return false;
        }

        try {
            $result = Http::asForm()->post('https://www.google.com/recaptcha/api/siteverify', [
                'secret' => $secret,
                'response' => $response,
                'remoteip' => request()->ip(),
            ])->json();
        } catch (\Throwable $e) {
            AppLogger::warning('blog.captcha.verify_failed', [], $e);

            return false;
        }

        if (! ($result['success'] ?? false)) {
            return false;
        }

        if ($minScore !== null) {
            return ((float) ($result['score'] ?? 0)) >= $minScore;
        }

        return true;
    }
}

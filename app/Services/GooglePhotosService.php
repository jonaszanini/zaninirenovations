<?php

namespace App\Services;

use App\Models\GooglePhotosToken;
use App\Models\User;
use GuzzleHttp\Client;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Str;
use RuntimeException;

class GooglePhotosService
{
    private function http(): Client
    {
        return new Client([
            'timeout' => 60,
            'connect_timeout' => 20,
            'http_errors' => false,
        ]);
    }

    private function httpPicker(): Client
    {
        return new Client([
            'timeout' => 90,
            'connect_timeout' => 20,
            'http_errors' => false,
        ]);
    }

    public function redirectUri(): string
    {
        $configured = (string) Config::get('google_photos.redirect_uri', '');
        if ($configured !== '') {
            return $configured;
        }

        return rtrim((string) config('app.url'), '/').'/admin/google-photos/callback';
    }

    public function buildAuthorizeUrl(User $user): string
    {
        $clientId = (string) Config::get('google_photos.client_id', '');
        if ($clientId === '') {
            throw new RuntimeException('Missing GOOGLE_PHOTOS_CLIENT_ID.');
        }

        $state = Str::random(40);
        session([
            'google_photos_oauth_state' => $state,
            'google_photos_oauth_user_id' => (int) $user->id,
        ]);

        $query = http_build_query([
            'client_id' => $clientId,
            'redirect_uri' => $this->redirectUri(),
            'response_type' => 'code',
            'access_type' => 'offline',
            'prompt' => 'consent',
            'include_granted_scopes' => 'false',
            'state' => $state,
            'scope' => implode(' ', (array) Config::get('google_photos.scopes', [])),
        ], '', '&', PHP_QUERY_RFC3986);

        return 'https://accounts.google.com/o/oauth2/v2/auth?'.$query;
    }

    public function exchangeCodeForTokens(string $code, string $state, int $userId): GooglePhotosToken
    {
        $expected = (string) session('google_photos_oauth_state', '');
        $expectedUserId = (int) session('google_photos_oauth_user_id', 0);
        session()->forget(['google_photos_oauth_state', 'google_photos_oauth_user_id']);

        if ($expected === '' || ! hash_equals($expected, $state) || $expectedUserId !== $userId) {
            throw new RuntimeException('Invalid OAuth state. Please try connecting again.');
        }

        $clientId = (string) Config::get('google_photos.client_id', '');
        $clientSecret = (string) Config::get('google_photos.client_secret', '');
        if ($clientId === '' || $clientSecret === '') {
            throw new RuntimeException('Missing Google OAuth client configuration.');
        }

        $response = $this->http()->post('https://oauth2.googleapis.com/token', [
            'form_params' => [
                'code' => $code,
                'client_id' => $clientId,
                'client_secret' => $clientSecret,
                'redirect_uri' => $this->redirectUri(),
                'grant_type' => 'authorization_code',
            ],
        ]);

        $payload = json_decode((string) $response->getBody(), true);
        if (! is_array($payload) || empty($payload['access_token'])) {
            throw new RuntimeException('Google token exchange failed: '.((string) $response->getBody()));
        }

        /** @var User $user */
        $user = User::query()->findOrFail($userId);

        $accessToken = (string) $payload['access_token'];
        $refreshToken = isset($payload['refresh_token']) ? (string) $payload['refresh_token'] : null;
        $expiresIn = isset($payload['expires_in']) ? (int) $payload['expires_in'] : 3600;

        $googleEmail = $this->fetchGoogleAccountEmail($accessToken);
        $allowed = (string) Config::get('google_photos.allowed_google_email', '');
        if ($allowed !== '' && strcasecmp($allowed, $googleEmail) !== 0) {
            throw new RuntimeException('Connected Google account ('.$googleEmail.') is not allowed for this site.');
        }

        $token = GooglePhotosToken::query()->firstOrNew(['user_id' => $user->id]);
        $token->google_email = $googleEmail;
        $token->access_token = $accessToken;
        if ($refreshToken) {
            $token->refresh_token = $refreshToken;
        }
        $token->access_token_expires_at = now()->addSeconds(max(60, $expiresIn - 60));
        $token->save();

        return $token;
    }

    public function disconnect(User $user): void
    {
        GooglePhotosToken::query()->where('user_id', $user->id)->delete();
    }

    public function ensureFreshAccessToken(GooglePhotosToken $token): string
    {
        if ($token->access_token && $token->access_token_expires_at && $token->access_token_expires_at->isFuture()) {
            return (string) $token->access_token;
        }

        $refresh = $token->refresh_token;
        if (! $refresh) {
            throw new RuntimeException('Google Photos is not connected (missing refresh token). Connect again.');
        }

        $clientId = (string) Config::get('google_photos.client_id', '');
        $clientSecret = (string) Config::get('google_photos.client_secret', '');
        if ($clientId === '' || $clientSecret === '') {
            throw new RuntimeException('Missing Google OAuth client configuration.');
        }

        $response = $this->http()->post('https://oauth2.googleapis.com/token', [
            'form_params' => [
                'client_id' => $clientId,
                'client_secret' => $clientSecret,
                'refresh_token' => $refresh,
                'grant_type' => 'refresh_token',
            ],
        ]);

        $payload = json_decode((string) $response->getBody(), true);
        if (! is_array($payload) || empty($payload['access_token'])) {
            throw new RuntimeException('Google token refresh failed: '.((string) $response->getBody()));
        }

        $token->access_token = (string) $payload['access_token'];
        $expiresIn = isset($payload['expires_in']) ? (int) $payload['expires_in'] : 3600;
        $token->access_token_expires_at = now()->addSeconds(max(60, $expiresIn - 60));
        $token->save();

        return (string) $token->access_token;
    }

    /**
     * @return array{id:string,pickerUri:string,pollingConfig:array{pollInterval:?string,timeoutIn:?string}|null,mediaItemsSet:bool}
     */
    public function createPickerSession(User $user, int $maxItemCount = 200): array
    {
        $token = GooglePhotosToken::query()->where('user_id', $user->id)->first();
        if (! $token) {
            throw new RuntimeException('Google Photos is not connected.');
        }
        $access = $this->ensureFreshAccessToken($token);

        $maxItemCount = max(1, min(2000, $maxItemCount));
        $response = $this->httpPicker()->post('https://photospicker.googleapis.com/v1/sessions', [
            'headers' => [
                'Authorization' => 'Bearer '.$access,
                'Content-Type' => 'application/json',
            ],
            'json' => [
                'pickingConfig' => [
                    'maxItemCount' => (string) $maxItemCount,
                ],
            ],
        ]);

        $payload = json_decode((string) $response->getBody(), true);
        if (! is_array($payload) || ($response->getStatusCode() ?? 500) >= 400) {
            throw new RuntimeException('Google Photos picker session create failed: '.((string) $response->getBody()));
        }

        return $this->parseSessionPayload($payload, true);
    }

    /**
     * @return array{id:string,pickerUri:string,pollingConfig:array{pollInterval:?string,timeoutIn:?string}|null,mediaItemsSet:bool}
     */
    public function getPickerSession(User $user, string $sessionId): array
    {
        $token = GooglePhotosToken::query()->where('user_id', $user->id)->first();
        if (! $token) {
            throw new RuntimeException('Google Photos is not connected.');
        }
        $access = $this->ensureFreshAccessToken($token);

        $url = 'https://photospicker.googleapis.com/v1/sessions/'.rawurlencode($sessionId);
        $response = $this->httpPicker()->get($url, [
            'headers' => [
                'Authorization' => 'Bearer '.$access,
            ],
        ]);

        $payload = json_decode((string) $response->getBody(), true);
        if (! is_array($payload) || ($response->getStatusCode() ?? 500) >= 400) {
            throw new RuntimeException('Google Photos picker session read failed: '.((string) $response->getBody()));
        }

        return $this->parseSessionPayload($payload, false);
    }

    /**
     * @return array<int,array{id:string,baseUrl:?string,mimeType:?string,filename:?string,type:?string}>
     */
    public function listAllPickedMedia(User $user, string $sessionId, int $maxItems = 200): array
    {
        $token = GooglePhotosToken::query()->where('user_id', $user->id)->first();
        if (! $token) {
            throw new RuntimeException('Google Photos is not connected.');
        }
        $access = $this->ensureFreshAccessToken($token);

        $maxItems = max(1, min(2000, $maxItems));
        $out = [];
        $pageToken = null;
        while (count($out) < $maxItems) {
            $result = $this->listPickedMediaPage($access, $sessionId, $pageToken, 100);
            foreach ($result['items'] as $item) {
                $out[] = $item;
                if (count($out) >= $maxItems) {
                    break 2;
                }
            }
            if (empty($result['nextPageToken'])) {
                break;
            }
            $pageToken = $result['nextPageToken'];
        }

        return $out;
    }

    /**
     * @return array{0:string,1:?string}
     */
    public function downloadPickedMedia(
        User $user,
        string $baseUrl,
        ?string $mimeTypeHint = null,
        bool $preferFullResolution = false,
    ): array {
        $token = GooglePhotosToken::query()->where('user_id', $user->id)->first();
        if (! $token) {
            throw new RuntimeException('Google Photos is not connected.');
        }
        $access = $this->ensureFreshAccessToken($token);

        $downloadUrl = $baseUrl;
        if (! str_contains($downloadUrl, '=')) {
            $isImage = is_string($mimeTypeHint) && str_starts_with(strtolower($mimeTypeHint), 'image/');
            if ($preferFullResolution && $isImage) {
                $downloadUrl .= '=d';
            } else {
                // Avoid downloading very large originals when we only keep a resized WebP copy.
                $downloadUrl .= $isImage ? '=w2048-h2048' : '=d';
            }
        }

        $tmp = tempnam(sys_get_temp_dir(), 'gpp_');
        if ($tmp === false) {
            throw new RuntimeException('Could not create temp file.');
        }
        $target = $tmp.'.bin';
        @unlink($tmp);

        $response = $this->http()->get($downloadUrl, [
            'headers' => [
                'Authorization' => 'Bearer '.$access,
            ],
            'sink' => $target,
        ]);

        if (($response->getStatusCode() ?? 500) >= 400 || ! is_file($target) || filesize($target) === 0) {
            @unlink($target);
            throw new RuntimeException('Google Photos picker download failed (HTTP '.$response->getStatusCode().').');
        }

        $mime = $mimeTypeHint;
        if (! $mime && function_exists('mime_content_type')) {
            $detected = @mime_content_type($target);
            $mime = is_string($detected) ? $detected : null;
        }

        return [$target, $mime];
    }

    /**
     * @return array{items:array<int,array{id:string,baseUrl:?string,mimeType:?string,filename:?string,type:?string}>,nextPageToken:?string}
     */
    private function listPickedMediaPage(string $accessToken, string $sessionId, ?string $pageToken, int $pageSize): array
    {
        $query = [
            'sessionId' => $sessionId,
            'pageSize' => max(1, min(100, $pageSize)),
        ];
        if ($pageToken) {
            $query['pageToken'] = $pageToken;
        }

        $url = 'https://photospicker.googleapis.com/v1/mediaItems?'.http_build_query($query);
        $response = $this->httpPicker()->get($url, [
            'headers' => [
                'Authorization' => 'Bearer '.$accessToken,
            ],
        ]);

        $payload = json_decode((string) $response->getBody(), true);
        if (! is_array($payload) || ($response->getStatusCode() ?? 500) >= 400) {
            throw new RuntimeException('Google Photos picker media list failed: '.((string) $response->getBody()));
        }

        $items = [];
        foreach ((array) ($payload['mediaItems'] ?? []) as $mi) {
            if (! is_array($mi)) {
                continue;
            }
            $mediaFile = (array) ($mi['mediaFile'] ?? $mi['media_file'] ?? []);
            $id = (string) ($mi['id'] ?? '');
            if ($id === '') {
                continue;
            }
            $baseUrl = $mediaFile['baseUrl'] ?? $mediaFile['base_url'] ?? null;
            $mimeType = $mediaFile['mimeType'] ?? $mediaFile['mime_type'] ?? null;
            $filename = $mediaFile['filename'] ?? null;
            $items[] = [
                'id' => $id,
                'type' => isset($mi['type']) ? (string) $mi['type'] : null,
                'baseUrl' => is_string($baseUrl) ? $baseUrl : null,
                'mimeType' => is_string($mimeType) ? $mimeType : null,
                'filename' => is_string($filename) ? $filename : null,
            ];
        }

        $next = isset($payload['nextPageToken']) ? (string) $payload['nextPageToken'] : null;

        return [
            'items' => $items,
            'nextPageToken' => $next !== '' ? $next : null,
        ];
    }

    /**
     * @return array{id:string,pickerUri:string,pollingConfig:array{pollInterval:?string,timeoutIn:?string}|null,mediaItemsSet:bool}
     */
    private function parseSessionPayload(array $payload, bool $requirePickerUri): array
    {
        // Be tolerant to shape variants (camelCase, snake_case, nested session object, resource name).
        $session = $payload;
        if (isset($payload['session']) && is_array($payload['session'])) {
            $session = $payload['session'];
        }

        $id = isset($session['id']) ? (string) $session['id'] : '';
        if ($id === '' && isset($session['name']) && is_string($session['name'])) {
            if (preg_match('#/sessions/([^/]+)$#', $session['name'], $m)) {
                $id = $m[1];
            }
        }

        $pickerUri = '';
        if (isset($session['pickerUri']) && is_string($session['pickerUri'])) {
            $pickerUri = $session['pickerUri'];
        } elseif (isset($session['picker_uri']) && is_string($session['picker_uri'])) {
            $pickerUri = $session['picker_uri'];
        }
        if ($id === '' || ($requirePickerUri && $pickerUri === '')) {
            $keys = implode(', ', array_keys($payload));
            throw new RuntimeException('Google Photos picker session response missing id or pickerUri. Response keys: ['.$keys.']');
        }

        $pollingRaw = Arr::get($session, 'pollingConfig', Arr::get($session, 'polling_config'));
        $polling = null;
        if (is_array($pollingRaw)) {
            $polling = [
                'pollInterval' => isset($pollingRaw['pollInterval'])
                    ? (string) $pollingRaw['pollInterval']
                    : (isset($pollingRaw['poll_interval']) ? (string) $pollingRaw['poll_interval'] : null),
                'timeoutIn' => isset($pollingRaw['timeoutIn'])
                    ? (string) $pollingRaw['timeoutIn']
                    : (isset($pollingRaw['timeout_in']) ? (string) $pollingRaw['timeout_in'] : null),
            ];
        }

        $rawSet = $session['mediaItemsSet'] ?? $session['media_items_set'] ?? $payload['mediaItemsSet'] ?? $payload['media_items_set'] ?? false;
        $mediaItemsSet = $rawSet === true || $rawSet === 1 || $rawSet === '1'
            || (is_string($rawSet) && strtolower($rawSet) === 'true');

        return [
            'id' => $id,
            'pickerUri' => $pickerUri,
            'pollingConfig' => $polling,
            'mediaItemsSet' => $mediaItemsSet,
        ];
    }

    private function fetchGoogleAccountEmail(string $accessToken): string
    {
        $response = $this->http()->get('https://www.googleapis.com/oauth2/v3/userinfo', [
            'headers' => [
                'Authorization' => 'Bearer '.$accessToken,
            ],
        ]);

        $payload = json_decode((string) $response->getBody(), true);
        if (! is_array($payload) || ($response->getStatusCode() ?? 500) >= 400) {
            throw new RuntimeException('Could not read Google account profile: '.((string) $response->getBody()));
        }

        $email = (string) ($payload['email'] ?? '');
        if ($email === '') {
            throw new RuntimeException('Google account email missing from profile response.');
        }

        return $email;
    }
}

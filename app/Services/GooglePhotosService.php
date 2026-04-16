<?php

namespace App\Services;

use App\Models\GooglePhotosToken;
use App\Models\User;
use GuzzleHttp\Client;
use Illuminate\Http\UploadedFile;
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
            // Avoid merging previously granted (legacy) Photos scopes; Library API needs a clean grant.
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

        $accessToken = (string) $payload['access_token'];
        $expiresIn = isset($payload['expires_in']) ? (int) $payload['expires_in'] : 3600;

        $token->access_token = $accessToken;
        $token->access_token_expires_at = now()->addSeconds(max(60, $expiresIn - 60));
        $token->save();

        return $accessToken;
    }

    /**
     * Lists app-created photos via POST mediaItems:search with no filters (Library API returns all
     * app-created media for the user). We avoid mediaItems:search with albumId because mixed albums
     * can return no rows; we avoid relying on GET mediaItems alone because some accounts see empty
     * first pages until pagination.
     *
     * @return array{items: array<int, array{id:string, baseUrl:?string, mimeType:?string, filename:?string}>, nextPageToken:?string}
     */
    public function listRecentMedia(User $user, ?string $pageToken = null, int $pageSize = 24): array
    {
        $token = GooglePhotosToken::query()->where('user_id', $user->id)->first();
        if (! $token) {
            throw new RuntimeException('Google Photos is not connected.');
        }

        $access = $this->ensureFreshAccessToken($token);
        $pageSize = max(1, min(100, $pageSize));

        $loadMore = $pageToken !== null && $pageToken !== '';

        if ($loadMore) {
            return $this->listAppCreatedMediaPage($access, $pageToken, $pageSize);
        }

        // First load: skip empty pages (Google sometimes returns nextPageToken with no mediaItems).
        $cursor = null;
        $maxEmptySkips = 12;
        for ($i = 0; $i < $maxEmptySkips; $i++) {
            $result = $this->listAppCreatedMediaPage($access, $cursor, $pageSize);
            if (count($result['items']) > 0) {
                return $result;
            }
            $next = $result['nextPageToken'] ?? null;
            if ($next === null || $next === '') {
                throw new RuntimeException(
                    'No app-created photos found yet. Use “Upload to import album” (needs photoslibrary.appendonly + reconnect), then load again. Items added only in the Google Photos app are never returned by this API.'
                );
            }
            $cursor = $next;
        }

        $result = $this->listAppCreatedMediaPage($access, $cursor, $pageSize);
        if (count($result['items']) > 0 || ! empty($result['nextPageToken'])) {
            return $result;
        }

        throw new RuntimeException(
            'No app-created photos found yet. Use “Upload to import album” (needs photoslibrary.appendonly + reconnect), then load again. Items added only in the Google Photos app are never returned by this API.'
        );
    }

    /**
     * @return array{items: array<int, array{id:string, baseUrl:?string, mimeType:?string, filename:?string}>, nextPageToken:?string}
     */
    private function listAppCreatedMediaPage(string $access, ?string $pageToken, int $pageSize): array
    {
        $body = ['pageSize' => $pageSize];
        if ($pageToken !== null && $pageToken !== '') {
            $body['pageToken'] = $pageToken;
        }

        $response = $this->http()->post('https://photoslibrary.googleapis.com/v1/mediaItems:search', [
            'headers' => [
                'Authorization' => 'Bearer '.$access,
                'Content-Type' => 'application/json',
            ],
            'json' => $body,
        ]);

        $code = (int) ($response->getStatusCode() ?? 500);
        $rawBody = (string) $response->getBody();
        $payload = json_decode($rawBody, true);
        if (! is_array($payload)) {
            throw new RuntimeException('Google Photos list failed: invalid response.');
        }

        if ($code >= 400 || (isset($payload['error']) && is_array($payload['error']))) {
            if (isset($payload['error']) && is_array($payload['error'])) {
                $msg = (string) ($payload['error']['message'] ?? json_encode($payload['error']));
                throw new RuntimeException('Google Photos list failed: '.$msg);
            }
            if (str_contains($rawBody, 'insufficient authentication scopes')) {
                throw new RuntimeException(
                    'Google Photos list failed: token is missing the app-created Library API scope. In Google Cloud → OAuth consent → Data Access, add https://www.googleapis.com/auth/photoslibrary.readonly.appcreateddata, then disconnect and reconnect Google Photos in admin.'
                );
            }

            throw new RuntimeException('Google Photos list failed: '.$rawBody);
        }

        $items = [];
        foreach ((array) ($payload['mediaItems'] ?? []) as $mi) {
            if (! is_array($mi)) {
                continue;
            }
            $id = (string) ($mi['id'] ?? '');
            if ($id === '') {
                continue;
            }
            $items[] = [
                'id' => $id,
                'baseUrl' => isset($mi['baseUrl']) ? (string) $mi['baseUrl'] : null,
                'mimeType' => Arr::get($mi, 'mediaMetadata.mimeType'),
                'filename' => Arr::get($mi, 'filename'),
            ];
        }

        $next = isset($payload['nextPageToken']) ? (string) $payload['nextPageToken'] : null;

        return [
            'items' => $items,
            'nextPageToken' => $next !== '' ? $next : null,
        ];
    }

    /**
     * @return array{id:string, baseUrl:?string, mimeType:?string, filename:?string}
     */
    public function getMediaItem(User $user, string $mediaItemId): array
    {
        $token = GooglePhotosToken::query()->where('user_id', $user->id)->first();
        if (! $token) {
            throw new RuntimeException('Google Photos is not connected.');
        }

        $access = $this->ensureFreshAccessToken($token);

        $url = 'https://photoslibrary.googleapis.com/v1/mediaItems/'.rawurlencode($mediaItemId);
        $response = $this->http()->get($url, [
            'headers' => [
                'Authorization' => 'Bearer '.$access,
            ],
        ]);

        $payload = json_decode((string) $response->getBody(), true);
        if (! is_array($payload) || ($response->getStatusCode() ?? 500) >= 400) {
            throw new RuntimeException('Google Photos fetch failed: '.((string) $response->getBody()));
        }

        return [
            'id' => (string) ($payload['id'] ?? $mediaItemId),
            'baseUrl' => isset($payload['baseUrl']) ? (string) $payload['baseUrl'] : null,
            'mimeType' => Arr::get($payload, 'mediaMetadata.mimeType'),
            'filename' => Arr::get($payload, 'filename'),
        ];
    }

    /**
     * Downloads bytes to a temp file and returns [tempPath, mimeType|null].
     *
     * @return array{0: string, 1: ?string}
     */
    public function downloadMedia(User $user, string $mediaItemId): array
    {
        $meta = $this->getMediaItem($user, $mediaItemId);
        $baseUrl = $meta['baseUrl'] ?? null;
        if (! $baseUrl) {
            throw new RuntimeException('Google Photos item has no baseUrl.');
        }

        $token = GooglePhotosToken::query()->where('user_id', $user->id)->firstOrFail();
        $access = $this->ensureFreshAccessToken($token);

        $downloadUrl = $baseUrl;
        if (! str_contains($downloadUrl, '=')) {
            $downloadUrl .= '=d';
        }

        $tmp = tempnam(sys_get_temp_dir(), 'gp_');
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
            throw new RuntimeException('Google Photos download failed (HTTP '.$response->getStatusCode().').');
        }

        $mime = $meta['mimeType'] ?? null;
        if (! $mime && function_exists('mime_content_type')) {
            $detected = @mime_content_type($target);
            $mime = is_string($detected) ? $detected : null;
        }

        return [$target, $mime];
    }

    /**
     * Raw upload + batchCreate so the file becomes app-created media in the configured import album.
     *
     * @return array{id: string, baseUrl: ?string, mimeType: ?string, filename: ?string}
     */
    public function uploadIntoImportAlbum(User $user, UploadedFile $file): array
    {
        $albumId = trim((string) Config::get('google_photos.album_id', ''));
        if ($albumId === '') {
            throw new RuntimeException('GOOGLE_PHOTOS_ALBUM_ID is not configured.');
        }

        $token = GooglePhotosToken::query()->where('user_id', $user->id)->first();
        if (! $token) {
            throw new RuntimeException('Google Photos is not connected.');
        }

        $access = $this->ensureFreshAccessToken($token);

        $realPath = $file->getRealPath();
        if ($realPath === false || ! is_readable($realPath)) {
            throw new RuntimeException('Could not read uploaded file.');
        }

        $mime = $file->getMimeType() ?: 'application/octet-stream';
        $uploadToken = $this->uploadRawBytes($access, $realPath, $mime);

        $safeName = $file->getClientOriginalName();
        if ($safeName === '' || strlen($safeName) > 255) {
            $safeName = 'upload.jpg';
        }

        return $this->batchCreateMediaInAlbum($access, $albumId, $uploadToken, $safeName);
    }

    private function uploadRawBytes(string $access, string $absolutePath, string $mimeType): string
    {
        $stream = fopen($absolutePath, 'rb');
        if ($stream === false) {
            throw new RuntimeException('Could not open file for Google Photos upload.');
        }

        try {
            $response = $this->http()->post('https://photoslibrary.googleapis.com/v1/uploads', [
                'headers' => [
                    'Authorization' => 'Bearer '.$access,
                    'Content-Type' => 'application/octet-stream',
                    'X-Goog-Upload-Protocol' => 'raw',
                    'X-Goog-Upload-Content-Type' => $mimeType,
                ],
                'body' => $stream,
            ]);
        } finally {
            if (is_resource($stream)) {
                fclose($stream);
            }
        }

        $code = (int) ($response->getStatusCode() ?? 500);
        $raw = trim((string) $response->getBody());
        if ($code !== 200 || $raw === '') {
            throw new RuntimeException('Google Photos byte upload failed (HTTP '.$code.'): '.$raw);
        }

        return $raw;
    }

    /**
     * @return array{id: string, baseUrl: ?string, mimeType: ?string, filename: ?string}
     */
    private function batchCreateMediaInAlbum(string $access, string $albumId, string $uploadToken, string $fileName): array
    {
        $response = $this->http()->post('https://photoslibrary.googleapis.com/v1/mediaItems:batchCreate', [
            'headers' => [
                'Authorization' => 'Bearer '.$access,
                'Content-Type' => 'application/json',
            ],
            'json' => [
                'albumId' => $albumId,
                'newMediaItems' => [
                    [
                        'simpleMediaItem' => [
                            'uploadToken' => $uploadToken,
                            'fileName' => $fileName,
                        ],
                    ],
                ],
            ],
        ]);

        $payload = json_decode((string) $response->getBody(), true);
        if (! is_array($payload)) {
            throw new RuntimeException('Google Photos batchCreate failed: invalid response.');
        }

        $code = (int) ($response->getStatusCode() ?? 500);
        if ($code !== 200 && $code !== 207) {
            throw new RuntimeException('Google Photos batchCreate failed (HTTP '.$code.'): '.((string) $response->getBody()));
        }

        $results = (array) ($payload['newMediaItemResults'] ?? []);
        $first = $results[0] ?? null;
        if (! is_array($first)) {
            throw new RuntimeException('Google Photos batchCreate returned no results.');
        }

        $media = $first['mediaItem'] ?? null;
        if (is_array($media) && ! empty($media['id'])) {
            return [
                'id' => (string) $media['id'],
                'baseUrl' => isset($media['baseUrl']) ? (string) $media['baseUrl'] : null,
                'mimeType' => Arr::get($media, 'mediaMetadata.mimeType'),
                'filename' => isset($media['filename']) ? (string) $media['filename'] : null,
            ];
        }

        $msg = '';
        if (isset($first['status']) && is_array($first['status'])) {
            $msg = (string) ($first['status']['message'] ?? '');
        }

        throw new RuntimeException('Google Photos batchCreate failed: '.($msg !== '' ? $msg : json_encode($first)));
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

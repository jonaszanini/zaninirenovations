<?php

return [
    'client_id' => env('GOOGLE_PHOTOS_CLIENT_ID'),
    'client_secret' => env('GOOGLE_PHOTOS_CLIENT_SECRET'),

    /**
     * Must match an authorized redirect URI in Google Cloud Console.
     * Default assumes APP_URL + /admin/google-photos/callback
     */
    'redirect_uri' => env('GOOGLE_PHOTOS_REDIRECT_URI'),

    /**
     * Optional safety rail: if set, only this Google account may connect.
     * Example: jonaszanini@gmail.com
     */
    'allowed_google_email' => env('GOOGLE_PHOTOS_ALLOWED_EMAIL'),

    'scopes' => [
        'https://www.googleapis.com/auth/photoslibrary.readonly',
    ],
];

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

    /**
     * Album whose media we list for admin import (Library API: mediaItems.search with albumId).
     * Must be an album created by this same OAuth client (app-created data model).
     */
    'album_id' => env('GOOGLE_PHOTOS_ALBUM_ID'),

    'scopes' => [
        // Needed so we can read the signed-in Google account email via userinfo during connect.
        'openid',
        'email',
        'profile',
        // After March 31, 2025, Library API reads are limited to app-created albums/media.
        'https://www.googleapis.com/auth/photoslibrary.readonly.appcreateddata',
    ],
];

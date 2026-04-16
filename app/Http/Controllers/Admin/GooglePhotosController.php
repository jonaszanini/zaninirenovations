<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\GooglePhotosService;
use Illuminate\Http\Request;
use Throwable;

class GooglePhotosController extends Controller
{
    public function connect(GooglePhotosService $photos)
    {
        try {
            $url = $photos->buildAuthorizeUrl(request()->user());

            return redirect()->away($url);
        } catch (Throwable $e) {
            report($e);

            return redirect()
                ->back()
                ->with('error', $e->getMessage());
        }
    }

    public function callback(Request $request, GooglePhotosService $photos)
    {
        $request->validate([
            'state' => ['required', 'string'],
            'code' => ['required', 'string'],
        ]);

        try {
            $photos->exchangeCodeForTokens(
                (string) $request->string('code'),
                (string) $request->string('state'),
                (int) $request->user()->id,
            );

            return redirect()
                ->route('admin.projects.index')
                ->with('success', 'Google Photos connected.');
        } catch (Throwable $e) {
            report($e);

            return redirect()
                ->route('admin.projects.index')
                ->with('error', 'Google Photos connection failed: '.$e->getMessage());
        }
    }

    public function disconnect(GooglePhotosService $photos)
    {
        $photos->disconnect(request()->user());

        return redirect()
            ->back()
            ->with('success', 'Google Photos disconnected.');
    }

    public function uploadToImportAlbum(Request $request, GooglePhotosService $photos)
    {
        $request->validate([
            'file' => ['required', 'file', 'max:20480', 'mimetypes:image/jpeg,image/png,image/webp,image/gif'],
        ]);

        try {
            $media = $photos->uploadIntoImportAlbum($request->user(), $request->file('file'));

            return response()->json([
                'ok' => true,
                'media' => $media,
            ]);
        } catch (Throwable $e) {
            report($e);

            return response()->json([
                'error' => $e->getMessage(),
            ], 422);
        }
    }

    public function media(Request $request, GooglePhotosService $photos)
    {
        $request->validate([
            'page_token' => ['nullable', 'string', 'max:5000'],
            'list_context' => ['nullable', 'string', 'in:album,library,get'],
        ]);

        try {
            $data = $photos->listRecentMedia(
                $request->user(),
                $request->input('page_token'),
                100,
                $request->input('list_context'),
            );

            $albumId = trim((string) config('google_photos.album_id', ''));
            $listContext = $data['list_context'] ?? null;
            unset($data['list_context']);

            $data['meta'] = [
                'album_id_length' => strlen($albumId),
                'album_id_tail' => $albumId === '' ? '' : substr($albumId, -22),
                'configuration_cached' => app()->configurationIsCached(),
                'list_context' => $listContext,
            ];

            return response()->json($data);
        } catch (Throwable $e) {
            report($e);

            $albumId = trim((string) config('google_photos.album_id', ''));

            return response()->json([
                'error' => $e->getMessage(),
                'meta' => [
                    'album_id_length' => strlen($albumId),
                    'album_id_tail' => $albumId === '' ? '' : substr($albumId, -22),
                    'configuration_cached' => app()->configurationIsCached(),
                ],
            ], 422);
        }
    }
}

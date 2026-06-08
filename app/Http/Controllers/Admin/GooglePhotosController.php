<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\GooglePhotosReconnectRequiredException;
use App\Services\GooglePhotosService;
use Illuminate\Http\Request;
use Throwable;

class GooglePhotosController extends Controller
{
    public function connect(Request $request, GooglePhotosService $photos)
    {
        try {
            $url = $photos->buildAuthorizeUrl(
                $request->user(),
                $this->sanitizeReturnUrl($request->query('return')),
            );

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

        $returnUrl = $this->sanitizeReturnUrl(session('google_photos_oauth_return_url'));

        try {
            $photos->exchangeCodeForTokens(
                (string) $request->string('code'),
                (string) $request->string('state'),
                (int) $request->user()->id,
            );

            session()->forget('google_photos_oauth_return_url');

            return redirect()
                ->to($returnUrl ?: route('admin.portfolio.index'))
                ->with('success', 'Google Photos connected.');
        } catch (Throwable $e) {
            report($e);
            session()->forget('google_photos_oauth_return_url');

            return redirect()
                ->to($returnUrl ?: route('admin.portfolio.index'))
                ->with('error', 'Google Photos connection failed: '.$e->getMessage());
        }
    }

    public function disconnect(Request $request, GooglePhotosService $photos)
    {
        $photos->disconnect($request->user());

        return redirect()
            ->back()
            ->with('success', 'Google Photos disconnected.');
    }

    public function createPickerSession(Request $request, GooglePhotosService $photos)
    {
        $request->validate([
            'max_item_count' => ['nullable', 'integer', 'min:1', 'max:200'],
        ]);

        try {
            $session = $photos->createPickerSession(
                $request->user(),
                (int) $request->integer('max_item_count', 30),
            );

            $pickerUri = $session['pickerUri'];
            $session['pickerUriAutoclose'] = str_ends_with($pickerUri, '/autoclose')
                ? $pickerUri
                : $pickerUri.'/autoclose';

            return response()->json($session);
        } catch (GooglePhotosReconnectRequiredException $e) {
            return response()->json([
                'error' => $e->getMessage(),
                'reconnect_required' => true,
            ], 401);
        } catch (Throwable $e) {
            report($e);

            return response()->json([
                'error' => $e->getMessage(),
            ], 422);
        }
    }

    public function showPickerSession(string $sessionId, Request $request, GooglePhotosService $photos)
    {
        try {
            $session = $photos->getPickerSession($request->user(), $sessionId);

            return response()->json($session);
        } catch (GooglePhotosReconnectRequiredException $e) {
            return response()->json([
                'error' => $e->getMessage(),
                'reconnect_required' => true,
            ], 401);
        } catch (Throwable $e) {
            report($e);

            return response()->json([
                'error' => $e->getMessage(),
            ], 422);
        }
    }

    public function pickedMedia(string $sessionId, Request $request, GooglePhotosService $photos)
    {
        $request->validate([
            'max_items' => ['nullable', 'integer', 'min:1', 'max:200'],
        ]);

        try {
            $session = $photos->getPickerSession($request->user(), $sessionId);
            if (! ($session['mediaItemsSet'] ?? false)) {
                return response()->json([
                    'ready' => false,
                    'pollingConfig' => $session['pollingConfig'] ?? null,
                ]);
            }

            $items = $photos->listAllPickedMedia(
                $request->user(),
                $sessionId,
                (int) $request->integer('max_items', 200),
            );

            return response()->json([
                'ready' => true,
                'items' => $items,
            ]);
        } catch (GooglePhotosReconnectRequiredException $e) {
            return response()->json([
                'error' => $e->getMessage(),
                'reconnect_required' => true,
            ], 401);
        } catch (Throwable $e) {
            report($e);

            return response()->json([
                'error' => $e->getMessage(),
            ], 422);
        }
    }

    public function thumbnail(Request $request, GooglePhotosService $photos)
    {
        $data = $request->validate([
            'url' => ['required', 'string', 'max:4096'],
        ]);

        try {
            [$bytes, $mime] = $photos->fetchPickerThumbnail($request->user(), $data['url']);

            return response($bytes, 200)
                ->header('Content-Type', $mime)
                ->header('Cache-Control', 'private, max-age=600');
        } catch (GooglePhotosReconnectRequiredException $e) {
            return response($e->getMessage(), 401)
                ->header('Content-Type', 'text/plain; charset=UTF-8');
        } catch (Throwable $e) {
            report($e);

            return response($e->getMessage(), 422)
                ->header('Content-Type', 'text/plain; charset=UTF-8');
        }
    }

    /**
     * @param  mixed  $return
     */
    private function sanitizeReturnUrl($return): ?string
    {
        if (! is_string($return) || $return === '') {
            return null;
        }

        if (! str_starts_with($return, '/admin') || str_contains($return, '//')) {
            return null;
        }

        return $return;
    }
}

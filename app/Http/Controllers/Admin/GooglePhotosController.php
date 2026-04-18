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
                ->route('admin.portfolio.index')
                ->with('success', 'Google Photos connected.');
        } catch (Throwable $e) {
            report($e);

            return redirect()
                ->route('admin.portfolio.index')
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
        } catch (Throwable $e) {
            report($e);

            return response()->json([
                'error' => $e->getMessage(),
            ], 422);
        }
    }
}

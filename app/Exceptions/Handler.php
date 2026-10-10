<?php

namespace App\Exceptions;

use App\Services\RoomAreaSignatureRetention;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Exceptions\Handler as ExceptionHandler;
use Throwable;

class Handler extends ExceptionHandler
{
    /**
     * A list of exception types with their corresponding custom log levels.
     *
     * @var array<class-string<\Throwable>, \Psr\Log\LogLevel::*>
     */
    protected $levels = [
        //
    ];

    /**
     * A list of the exception types that are not reported.
     *
     * @var array<int, class-string<\Throwable>>
     */
    protected $dontReport = [
        //
    ];

    /**
     * A list of the inputs that are never flashed to the session on validation exceptions.
     *
     * @var array<int, string>
     */
    protected $dontFlash = [
        'current_password',
        'password',
        'password_confirmation',
    ];

    /**
     * Register the exception handling callbacks for the application.
     *
     * @return void
     */
    public function register()
    {
        $this->renderable(function (QueryException $e, $request) {
            $message = app(RoomAreaSignatureRetention::class)->messageFromQueryException($e);
            if ($message === null) {
                return null;
            }
            if ($request->expectsJson()) {
                return response()->json(['ok' => false, 'message' => $message], 409);
            }

            return redirect()->back()->with('error', $message);
        });
    }

    /**
     * Context merged into Laravel's default exception log entry.
     *
     * @return array<string, mixed>
     */
    protected function context()
    {
        try {
            $request = $this->container->make('request');
        } catch (\Throwable) {
            return [];
        }

        return array_filter([
            'user_id' => optional($request->user())->id,
            'url' => $request->fullUrl(),
            'route' => optional($request->route())->getName(),
        ]);
    }
}

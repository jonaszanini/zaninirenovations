<?php

namespace App\Exceptions;

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
        //
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

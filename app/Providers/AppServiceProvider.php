<?php

namespace App\Providers;

use App\Support\Logging\RequestContext;
use Illuminate\Console\Events\CommandFinished;
use Illuminate\Console\Events\CommandStarting;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     *
     * @return void
     */
    public function register()
    {
        //
    }

    /**
     * Bootstrap any application services.
     *
     * @return void
     */
    public function boot()
    {
        Event::listen(CommandStarting::class, function (CommandStarting $event): void {
            RequestContext::set([
                'request_id' => (string) Str::uuid(),
                'channel' => 'cli',
                'action' => 'artisan:'.$event->command,
            ]);
        });

        Event::listen(CommandFinished::class, function (): void {
            RequestContext::clear();
        });
    }
}

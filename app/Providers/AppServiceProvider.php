<?php

namespace App\Providers;

use App\Models\BlogComment;
use App\Models\EtransferNotice;
use App\Models\Task;
use App\Support\Logging\RequestContext;
use App\Support\ProductionDatabaseGuard;
use Illuminate\Console\Events\CommandFinished;
use Illuminate\Console\Events\CommandStarting;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Facades\View;
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
        if ($this->app->environment('production')) {
            $root = rtrim((string) config('app.url'), '/');
            if ($root !== '') {
                URL::forceRootUrl($root);
                $scheme = parse_url($root, PHP_URL_SCHEME);
                if (is_string($scheme) && $scheme !== '') {
                    URL::forceScheme($scheme);
                }
            }
        }

        Event::listen(CommandStarting::class, function (CommandStarting $event): void {
            // Abort destructive DB commands (migrate:fresh, db:wipe, ...) before
            // they can touch the live production database.
            ProductionDatabaseGuard::handle($event);

            RequestContext::set([
                'request_id' => (string) Str::uuid(),
                'channel' => 'cli',
                'action' => 'artisan:'.$event->command,
            ]);
        });

        Event::listen(CommandFinished::class, function (): void {
            RequestContext::clear();
        });

        // Sidebar "Tasks" badge — cheap enough to run per admin request
        // (single indexed COUNT), avoids a dedicated middleware just for this.
        View::composer('admin.layouts.app', function ($view): void {
            $view->with('sidebarOpenTaskCount', auth()->check() ? Task::query()->open()->count() : 0);
            $view->with('sidebarPendingCommentCount', auth()->check()
                ? BlogComment::query()->where('is_approved', false)->count()
                : 0);
            $view->with('sidebarPendingEtransferCount', auth()->check()
                ? EtransferNotice::query()->pending()->count()
                : 0);
        });
    }
}

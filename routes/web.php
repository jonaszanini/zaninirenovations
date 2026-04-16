<?php

use App\Http\Controllers\Admin\Auth\LoginController;
use App\Http\Controllers\Admin\CategoryController;
use App\Http\Controllers\Admin\GooglePhotosController;
use App\Http\Controllers\Admin\ProjectController;
use App\Http\Controllers\ContactFormController;
use App\Models\Project;
use Illuminate\Support\Facades\Route;

Route::post('/contact-email', [ContactFormController::class, 'submit'])
    ->middleware('throttle:8,1')
    ->name('contact.submit');

Route::view('/warranty', 'site.warranty')->name('warranty');

Route::view('/services/custom-kitchen-remodel', 'site.service-kitchen')->name('service.kitchen');

Route::get('/sitemap.xml', function () {
    return response()
        ->view('site.sitemap-xml', [], 200)
        ->header('Content-Type', 'application/xml; charset=UTF-8');
})->name('sitemap');

Route::get('/robots.txt', function () {
    $base = rtrim((string) config('app.url'), '/');
    $lines = [
        'User-agent: *',
        'Disallow: /admin',
        'Disallow: /admin/',
        'Allow: /',
        '',
        'Sitemap: '.$base.'/sitemap.xml',
        '',
    ];

    return response(implode("\n", $lines), 200)
        ->header('Content-Type', 'text/plain; charset=UTF-8');
})->name('robots');

Route::get('/', function () {
    $projects = Project::query()
        ->with([
            'photos' => fn ($q) => $q->orderBy('sort_order'),
            'categories',
        ])
        ->orderByCompletionLatest()
        ->get();

    $projectFilterCategories = $projects
        ->flatMap(fn (Project $p) => $p->categories)
        ->unique('id')
        ->sortBy(fn ($c) => [$c->sort_order, strtolower($c->name)])
        ->values();

    return view('site.home', compact('projects', 'projectFilterCategories'));
});

Route::middleware('guest')->group(function () {
    Route::get('/admin/login', [LoginController::class, 'showLoginForm'])->name('login');
    Route::post('/admin/login', [LoginController::class, 'login']);
});

Route::middleware('auth')->prefix('admin')->name('admin.')->group(function () {
    Route::post('/logout', [LoginController::class, 'logout'])->name('logout');
    Route::get('/', function () {
        return redirect()->route('admin.projects.index');
    });

    Route::get('google-photos/connect', [GooglePhotosController::class, 'connect'])->name('google-photos.connect');
    Route::get('google-photos/callback', [GooglePhotosController::class, 'callback'])->name('google-photos.callback');
    Route::post('google-photos/disconnect', [GooglePhotosController::class, 'disconnect'])->name('google-photos.disconnect');
    Route::post('google-photos/picker-sessions', [GooglePhotosController::class, 'createPickerSession'])->name('google-photos.picker-sessions.create');
    Route::get('google-photos/picker-sessions/{sessionId}', [GooglePhotosController::class, 'showPickerSession'])->name('google-photos.picker-sessions.show');
    Route::get('google-photos/picker-sessions/{sessionId}/media', [GooglePhotosController::class, 'pickedMedia'])->name('google-photos.picker-sessions.media');

    Route::post('projects/{project}/photos/{photo}/rotate', [ProjectController::class, 'rotatePhoto'])
        ->name('projects.photos.rotate');
    Route::resource('projects', ProjectController::class)->except(['show']);
    Route::resource('categories', CategoryController::class)->except(['show']);
});

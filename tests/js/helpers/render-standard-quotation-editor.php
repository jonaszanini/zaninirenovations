<?php

/**
 * Render the real standard quotation create Blade (admin layout + sidebar)
 * for Puppeteer layout checks. No database: catalog options are empty.
 */
$root = dirname(__DIR__, 3);
require $root.'/vendor/autoload.php';
$app = require $root.'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$viewErrors = new Illuminate\Support\ViewErrorBag();
view()->share('errors', $viewErrors);
Illuminate\Support\Facades\View::share('errors', $viewErrors);

config([
    'database.default' => 'sqlite',
    'database.connections.sqlite.database' => ':memory:',
    'database.connections.sqlite.url' => null,
    'database.connections.sqlite.foreign_key_constraints' => false,
]);
Illuminate\Support\Facades\DB::purge('sqlite');
Illuminate\Support\Facades\DB::purge('mysql');
Illuminate\Support\Facades\DB::setDefaultConnection('sqlite');
Illuminate\Support\Facades\Schema::create('tasks', function ($table): void {
    $table->increments('id');
    $table->timestamp('completed_at')->nullable();
});
Illuminate\Support\Facades\Schema::create('blog_comments', function ($table): void {
    $table->increments('id');
    $table->boolean('is_approved')->default(false);
});
Illuminate\Support\Facades\Schema::create('etransfer_notices', function ($table): void {
    $table->increments('id');
    $table->string('status')->nullable();
});

$user = new App\Models\User();
$user->forceFill([
    'id' => 1,
    'name' => 'QA',
    'email' => 'qa@zanini.test',
    'role' => App\Models\User::ROLE_ADMIN,
]);
Illuminate\Support\Facades\Auth::guard('web')->setUser($user);

$request = Illuminate\Http\Request::create('/admin/quotations/standard/create', 'GET');
$app->instance('request', $request);
Illuminate\Support\Facades\Request::swap($request);

try {
    $route = $app['router']->getRoutes()->match($request);
    $request->setRouteResolver(static fn () => $route);
} catch (Throwable $e) {
    fwrite(STDERR, 'Could not match standard quotation create route: '.$e->getMessage()."\n");
    exit(1);
}

$session = $app->make('session.store');
$session->start();
$request->setLaravelSession($session);

$html = view('admin.quotations.standard.create', [
    'quotation' => null,
    'linkableProjects' => collect(),
    'linkProject' => null,
    'linkedCpq' => null,
    'catalogPickerOptions' => [],
])->render();

$html = preg_replace('#https?://[^/]+(/[^"\']*)#', '$1', $html);

echo $html;

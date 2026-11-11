<?php

/**
 * Render the real admin.clients.index Blade view with the admin sidebar
 * and a long address. Uses an in-memory sqlite so layout composers can
 * COUNT without MySQL.
 */
$root = dirname(__DIR__, 2);
require $root.'/vendor/autoload.php';
$app = require $root.'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\Client;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\MessageBag;
use Illuminate\Support\ViewErrorBag;

config(['database.default' => 'sqlite']);
config(['database.connections.sqlite.database' => ':memory:']);
config(['database.connections.sqlite.foreign_key_constraints' => false]);
DB::purge('sqlite');
DB::setDefaultConnection('sqlite');

Schema::create('tasks', function (Blueprint $table) {
    $table->id();
    $table->timestamp('completed_at')->nullable();
});
Schema::create('blog_comments', function (Blueprint $table) {
    $table->id();
    $table->boolean('is_approved')->default(false);
});
Schema::create('etransfer_notices', function (Blueprint $table) {
    $table->id();
    $table->string('status')->nullable();
});

$route = app('router')->getRoutes()->getByName('admin.clients.index');
if ($route === null) {
    fwrite(STDERR, "admin.clients.index route missing\n");
    exit(1);
}

$request = Request::create('/admin/clients', 'GET');
$request->setRouteResolver(static fn () => $route);
$app->instance('request', $request);
app('url')->forceRootUrl('http://127.0.0.1');
app('url')->setRequest($request);

$user = new User([
    'name' => 'QA Admin',
    'email' => 'qa.admin@example.test',
    'role' => User::ROLE_ADMIN,
]);
Auth::guard('web')->setUser($user);

$errors = new ViewErrorBag;
$errors->put('default', new MessageBag);
view()->share('errors', $errors);

$address = '1025 Grenon Avenue, Britannia neighbourhood, Ottawa, Ontario K2B 8S5, Canada';
$client = new Client([
    'name' => 'Lucca Long Address',
    'email' => 'lucca.long@example.test',
    'email_secondary' => 'lucca.cc@example.test',
    'phone' => '6475550100',
    'address' => $address,
]);
$client->id = 1;
$client->exists = true;
$client->setRelation('tags', collect());
$client->projects_count = 2;

$clients = new LengthAwarePaginator(
    collect([$client]),
    1,
    20,
    1,
    ['path' => '/admin/clients']
);

$html = view('admin.clients.index', [
    'clients' => $clients,
    'q' => '',
    'tagId' => 0,
    'totalClients' => 1,
    'tags' => collect(),
])->render();

echo str_replace(['http://127.0.0.1/', 'https://127.0.0.1/'], '/', $html);

<?php

use App\Http\Controllers\Admin\SharedPriceLibraryController;
use App\Models\SharedCrewRate;
use App\Models\User;
use Illuminate\Http\Request;

require __DIR__.'/../../vendor/autoload.php';
$app = require __DIR__.'/../../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$index = (int) ($argv[1] ?? 0);
$email = (string) ($argv[2] ?? 'admin@zanini.ca');
$markers = ['!', '@', '#', '$', '%', '^', '&', '*', '=', '+', '~', '|', '/', '\\', '{', '}'];
$label = 'Par'.$markers[$index % count($markers)].'allel';

$user = User::query()->where('email', $email)->firstOrFail();
auth()->login($user);

$crew = SharedCrewRate::current();
$payload = [
    'owner_hourly_rate' => number_format((float) $crew->owner_hourly_rate, 2, '.', ''),
    'helper_hourly_rate' => number_format((float) $crew->helper_hourly_rate, 2, '.', ''),
    'hours_per_day' => (string) $crew->hours_per_day,
    'new_works' => [
        'flooring' => [[
            'label' => $label,
            'unit' => 'each',
            'rate' => '1.00',
            'flat_rate' => '2.00',
            'consumers' => ['flooring'],
        ]],
    ],
];

$request = Request::create('/admin/quotations/shared-prices', 'PUT', $payload);
try {
    $app->make(SharedPriceLibraryController::class)->update($request);
    fwrite(STDOUT, 'OK '.$label."\n");
    exit(0);
} catch (Throwable $e) {
    fwrite(STDERR, $e->getMessage()."\n");
    exit(1);
}

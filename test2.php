<?php

require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$q = App\Models\FlooringQuotation::find(3);
$url = $q->inputs['material_image_url_1'];
echo "URL: $url\n";

$hash = md5($url);
$filename = "quotations/materials/{$hash}.jpg";
$absolutePath = storage_path("app/{$filename}");

try {
    $response = \Illuminate\Support\Facades\Http::withHeaders([
        'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36'
    ])->timeout(15)->get($url);
    echo "Status: " . $response->status() . "\n";
    echo "Content-Type: " . $response->header('Content-Type') . "\n";
    if ($response->successful() && str_starts_with((string) $response->header('Content-Type', ''), 'image/')) {
        \Illuminate\Support\Facades\Storage::disk('local')->put($filename, $response->body());
        echo "Saved to $absolutePath\n";
    } else {
        echo "Failed condition: successful=" . ($response->successful() ? 'yes' : 'no') . " content-type=" . $response->header('Content-Type') . "\n";
    }
} catch (\Exception $e) {
    echo "Exception: " . $e->getMessage() . "\n";
}

<?php

require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$q = App\Models\FlooringQuotation::find(3);
if (!$q) {
    echo "Quotation 3 not found.\n";
    exit;
}

$inputs = $q->inputs;
$urls = [
    $inputs['material_image_url_1'] ?? '',
    $inputs['material_image_url_2'] ?? '',
    $inputs['material_image_url_3'] ?? '',
    $inputs['material_image_url_4'] ?? '',
];

$newLocalImages = [];
foreach ($urls as $i => $url) {
    if ($url !== '') {
        $hash = md5($url);
        $filename = "quotations/materials/{$hash}.jpg";
        $absolutePath = storage_path("app/{$filename}");
        
        if (\Illuminate\Support\Facades\Storage::disk('local')->exists($filename)) {
            $newLocalImages[] = $absolutePath;
            echo "File exists: $absolutePath\n";
        } else {
            try {
                $response = \Illuminate\Support\Facades\Http::withoutVerifying()->withHeaders([
                    'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36'
                ])->timeout(30)->get($url);
                if ($response->successful() && str_starts_with((string) $response->header('Content-Type', ''), 'image/')) {
                    \Illuminate\Support\Facades\Storage::disk('local')->put($filename, $response->body());
                    $newLocalImages[] = $absolutePath;
                    echo "Downloaded: $absolutePath\n";
                } else {
                    $newLocalImages[] = $url;
                    echo "Failed check: successful=" . ($response->successful() ? 'true' : 'false') . ", Content-Type=" . $response->header('Content-Type') . "\n";
                }
            } catch (\Exception $e) {
                echo "Exception: " . $e->getMessage() . "\n";
                $newLocalImages[] = $url;
            }
        }
    }
}

$inputs['material_local_images'] = $newLocalImages;
$q->inputs = $inputs;
$q->save();

echo "Updated Database with local images!\n";
print_r($newLocalImages);

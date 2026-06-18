<?php

require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$url = 'https://images.homedepot.ca/productimages/p_1001868220_alt_VTRHDWINOAK6X48-4.4MM-6MIL_b.jpg?product-images=l';

$response = Illuminate\Support\Facades\Http::timeout(5)->get($url);

echo "Status: " . $response->status() . "\n";
echo "Content-Type: " . $response->header('Content-Type') . "\n";
echo "Body length: " . strlen($response->body()) . "\n";

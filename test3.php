<?php

require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$url = 'https://images.homedepot.ca/productimages/p_1001868220_alt_VTRHDWINOAK6X48-4.4MM-6MIL_b.jpg?product-images=l';
$html = '<!DOCTYPE html><html><body><h1>Test</h1><img src="' . $url . '" style="max-width:100px;"></body></html>';

try {
    $pdf = Barryvdh\DomPDF\Facade\Pdf::loadHTML($html)->setOption('isRemoteEnabled', true);
    $pdf->output();
    echo "Success!\n";
} catch (\Exception $e) {
    echo "DomPDF Exception: " . $e->getMessage() . "\n";
}

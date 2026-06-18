<?php

require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$path1 = 'C:\xampp\htdocs\ZaniniRenovations\storage\app/quotations/materials/0c74239a40a1fa4356866d20b4a75f3f.jpg';
$path2 = str_replace('\\', '/', $path1);
$path3 = 'file:///' . $path2;

$html = '
<!DOCTYPE html>
<html>
<body>
<h1>Test 1</h1>
<img src="' . $path1 . '" style="max-width:100px;">
<h1>Test 2</h1>
<img src="' . $path2 . '" style="max-width:100px;">
<h1>Test 3</h1>
<img src="' . $path3 . '" style="max-width:100px;">
</body>
</html>
';

try {
    $pdf = Barryvdh\DomPDF\Facade\Pdf::loadHTML($html)->setOption('isRemoteEnabled', true);
    $pdf->save('test_local_images.pdf');
    echo "Saved test_local_images.pdf\n";
    print_r($pdf->getDomPDF()->getOptions()->getLogOutputFile());
} catch (\Exception $e) {
    echo "DomPDF Exception: " . $e->getMessage() . "\n";
}

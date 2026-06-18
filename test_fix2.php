<?php

require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$q = App\Models\FlooringQuotation::find(3);
if (!$q) {
    echo "Quotation 3 not found.\n";
    exit;
}

$newLocalImages = [
    'C:\xampp\htdocs\ZaniniRenovations\storage\app/quotations/materials/0c74239a40a1fa4356866d20b4a75f3f.jpg',
    'C:\xampp\htdocs\ZaniniRenovations\storage\app/quotations/materials/38af88770db3ec25ec249612ee1a2ced.jpg',
    'C:\xampp\htdocs\ZaniniRenovations\storage\app/quotations/materials/2e5df2faefa5b330a9efc4f4c340bf8c.jpg',
    'C:\xampp\htdocs\ZaniniRenovations\storage\app/quotations/materials/3845b6980a5e21ac94729f233ced5757.jpg'
];

$inputs = $q->inputs;
$inputs['material_local_images'] = $newLocalImages;
$q->update(['inputs' => $inputs]);

echo "Forced DB update!\n";
print_r($q->fresh()->inputs['material_local_images']);

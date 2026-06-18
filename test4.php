<?php

require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$url = 'https://images.homedepot.ca/productimages/p_1001868220_alt_VTRHDWINOAK6X48-4.4MM-6MIL_b.jpg?product-images=l';

try {
    echo "Attempting to fetch image...\n";
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); // Just in case Windows SSL certs are missing
    curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36');
    curl_setopt($ch, CURLOPT_TIMEOUT, 15);
    
    $data = curl_exec($ch);
    $error = curl_error($ch);
    $info = curl_getinfo($ch);
    curl_close($ch);
    
    echo "cURL Error: $error\n";
    echo "Status code: " . $info['http_code'] . "\n";
    echo "Content type: " . $info['content_type'] . "\n";
    echo "Size: " . strlen($data) . "\n";
    
    if ($info['http_code'] == 200) {
        file_put_contents('test_image.jpg', $data);
        echo "Saved test_image.jpg\n";
    }

} catch (\Exception $e) {
    echo "Exception: " . $e->getMessage() . "\n";
}

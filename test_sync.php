<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

$t = \App\Models\TileQuotation::find(1);
if ($t) {
    try {
        // We have to use reflection because syncProjectQuotation is protected
        $controller = app(\App\Http\Controllers\Admin\TileQuotationController::class);
        $reflection = new \ReflectionClass($controller);
        $method = $reflection->getMethod('syncProjectQuotation');
        $method->setAccessible(true);
        $method->invoke($controller, $t);
        echo "SUCCESS\n";
    } catch (\Throwable $e) {
        echo "ERROR: " . $e->getMessage() . "\n";
        echo $e->getTraceAsString();
    }
} else {
    echo "NOT FOUND\n";
}

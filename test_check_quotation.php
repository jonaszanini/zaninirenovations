<?php

require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$c = App\Models\ClientProjectQuotation::find(4);
if ($c) {
    echo "Source Type: " . $c->source_type . "\n";
    echo "Source ID: " . $c->source_id . "\n";
} else {
    echo "Quotation 4 not found.\n";
}

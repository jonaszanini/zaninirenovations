<?php

require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$q = App\Models\ClientProjectQuotation::find(47);
$project = App\Models\ClientProject::find($q->client_project_id);

$pdf = Barryvdh\DomPDF\Facade\Pdf::loadView('admin.client_projects.quotation.pdf', [
    'project' => $project,
    'quotation' => $q,
    'pdfPhotos' => collect(), // dummy
])
    ->setPaper('a4')
    ->setOption('isRemoteEnabled', true)
    ->setOption('isHtml5ParserEnabled', true)
    ->setOption('defaultFont', 'Roboto');

$pdf->render();
$pdf->save('test_quotation_47.pdf');
echo "Saved test_quotation_47.pdf\n";

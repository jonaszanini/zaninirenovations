<?php

namespace App\Console\Commands;

use App\Models\ClientProject;
use App\Models\ClientProjectContract;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Console\Command;

class ContractSignatureTestPdf extends Command
{
    protected $signature = 'contract:signature-test-pdf
                            {--project= : Client project ID (uses first project with contract if omitted)}';

    protected $description = 'Generate a sample contract PDF to preview contractor signature placement';

    public function handle(): int
    {
        $signaturePath = public_path('images/signatures/jonaszanini.png');
        if (! is_file($signaturePath)) {
            $this->error('Missing signature image: public/images/signatures/jonaszanini.png');

            return self::FAILURE;
        }

        $project = $this->resolveProject();
        if (! $project) {
            $this->error('No client project found. Create a project first or pass --project=ID');

            return self::FAILURE;
        }

        $contract = $project->contract;
        if (! $contract || ! $contract->hasContent()) {
            $contract = new ClientProjectContract([
                'html_content' => '<h2>Signature placement test</h2>'
                    .'<p>This PDF is only for checking how the contractor signature image appears above the signature line. '
                    .'Scroll to the last page to review sizing and alignment.</p>'
                    .'<p>Lorem ipsum dolor sit amet, consectetur adipiscing elit. Sed do eiusmod tempor incididunt ut labore et dolore magna aliqua.</p>',
                'version' => 1,
            ]);
        }

        $pdf = Pdf::loadView('admin.client_projects.contract.pdf', [
            'project' => $project,
            'contract' => $contract,
        ])
            ->setPaper('a4')
            ->setOption('isRemoteEnabled', true)
            ->setOption('isHtml5ParserEnabled', true)
            ->setOption('defaultFont', 'Roboto');

        $pdf->render();
        $this->stampPageNumbers($pdf);

        $bytes = $pdf->output();

        $publicFile = public_path('contract-signature-test.pdf');
        $storageFile = storage_path('app/contract-signature-test.pdf');

        file_put_contents($publicFile, $bytes);
        file_put_contents($storageFile, $bytes);

        $this->info('Contract signature test PDF written:');
        $this->line('  '.$publicFile);
        $this->line('  '.$storageFile);
        $this->newLine();
        $this->info('Open in browser (local XAMPP example):');
        $this->line('  '.url('contract-signature-test.pdf'));
        $this->newLine();
        $this->info("Project used: #{$project->id} — {$project->name}");
        if ($project->client_name) {
            $this->line("Client on PDF: {$project->client_name}");
        }

        return self::SUCCESS;
    }

    protected function resolveProject(): ?ClientProject
    {
        $id = $this->option('project');
        if ($id !== null && $id !== '') {
            return ClientProject::query()->find((int) $id);
        }

        $withContract = ClientProject::query()
            ->whereHas('contract', function ($q) {
                $q->whereNotNull('html_content')->where('html_content', '!=', '');
            })
            ->orderByDesc('id')
            ->first();

        if ($withContract) {
            return $withContract->load('contract');
        }

        return ClientProject::query()->orderByDesc('id')->first();
    }

    protected function stampPageNumbers($pdf): void
    {
        $dompdf = $pdf->getDomPDF();
        $canvas = $dompdf->getCanvas();
        $fontMetrics = $dompdf->getFontMetrics();

        $font = $fontMetrics->getFont('Roboto', 'normal') ?: $fontMetrics->getFont('Helvetica', 'normal');
        $size = 9;
        $color = [0.42, 0.45, 0.50];

        $text = 'Page {PAGE_NUM} of {PAGE_COUNT}';
        $reservedWidth = $fontMetrics->getTextWidth('Page 99 of 99', $font, $size);

        $x = $canvas->get_width() - 60 - $reservedWidth;
        $y = $canvas->get_height() - 50;

        $canvas->page_text($x, $y, $text, $font, $size, $color);
    }
}

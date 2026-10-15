<?php

namespace App\Services\Invoice;

use App\Models\ClientProject;
use App\Models\ClientProjectInvoice;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Str;

/**
 * Renders invoice / receipt PDFs and resolves the storage paths and
 * client-facing filenames used for them. Extracted from
 * ClientProjectInvoiceController (pure rendering — no persistence, email,
 * or SMS side effects) so it can be reused and tested independently.
 */
class InvoicePdfService
{
    public function renderInvoicePdf(ClientProject $project, ClientProjectInvoice $invoice)
    {
        $pdf = Pdf::loadView('admin.client_projects.invoice.pdf', [
            'project' => $project,
            'invoice' => $invoice,
            'isHourlyManual' => $project->isHourly() && $invoice->isManual(),
            'hourlyLabourTotal' => $invoice->hourlyLabourTotalFromItems(),
            'hourlyMaterialsTotal' => $invoice->hourlyMaterialsTotalFromItems(),
        ])
            ->setPaper('a4')
            ->setOption('isRemoteEnabled', true)
            ->setOption('isHtml5ParserEnabled', true)
            ->setOption('defaultFont', 'Roboto');

        $pdf->render();
        $this->stampPageNumbers($pdf);

        return $pdf;
    }

    public function renderReceiptPdf(ClientProject $project, ClientProjectInvoice $invoice)
    {
        $pdf = Pdf::loadView('admin.client_projects.invoice.receipt', [
            'project' => $project,
            'invoice' => $invoice,
            'isHourlyManual' => $project->isHourly() && $invoice->isManual(),
            'hourlyLabourTotal' => $invoice->hourlyLabourTotalFromItems(),
            'hourlyMaterialsTotal' => $invoice->hourlyMaterialsTotalFromItems(),
        ])
            ->setPaper('a4')
            ->setOption('isRemoteEnabled', true)
            ->setOption('isHtml5ParserEnabled', true)
            ->setOption('defaultFont', 'Roboto');

        $pdf->render();
        $this->stampPageNumbers($pdf);

        return $pdf;
    }

    public function renderRefundPdf(ClientProject $project, ClientProjectInvoice $invoice)
    {
        $pdf = Pdf::loadView('admin.client_projects.invoice.refund', [
            'project' => $project,
            'invoice' => $invoice,
        ])
            ->setPaper('a4')
            ->setOption('isRemoteEnabled', true)
            ->setOption('isHtml5ParserEnabled', true)
            ->setOption('defaultFont', 'Roboto');

        $pdf->render();
        $this->stampPageNumbers($pdf);

        return $pdf;
    }

    protected function stampPageNumbers($pdf): void
    {
        $dompdf = $pdf->getDomPDF();
        $canvas = $dompdf->getCanvas();
        $fontMetrics = $dompdf->getFontMetrics();

        $font = $fontMetrics->getFont('Roboto', 'normal') ?: $fontMetrics->getFont('Helvetica', 'normal');
        $size = 9;
        $color = [0.42, 0.45, 0.50]; // #6b7280

        $text = 'Page {PAGE_NUM} of {PAGE_COUNT}';
        $reservedWidth = $fontMetrics->getTextWidth('Page 99 of 99', $font, $size);

        $x = $canvas->get_width() - 60 - $reservedWidth;
        $y = $canvas->get_height() - 50;

        $canvas->page_text($x, $y, $text, $font, $size, $color);
    }

    public function invoicePdfStoragePath(ClientProject $project, ClientProjectInvoice $invoice): string
    {
        if ($invoice->isManual()) {
            return sprintf(
                'invoices/project-%d-manual-%d-v%d.pdf',
                $project->id,
                $invoice->id,
                $invoice->version
            );
        }

        return sprintf(
            'invoices/project-%d-installment-%d-v%d.pdf',
            $project->id,
            $invoice->installment_number,
            $invoice->version
        );
    }

    public function receiptPdfStoragePath(ClientProject $project, ClientProjectInvoice $invoice): string
    {
        if ($invoice->isManual()) {
            return sprintf(
                'receipts/project-%d-manual-%d-v%d.pdf',
                $project->id,
                $invoice->id,
                $invoice->version
            );
        }

        return sprintf(
            'receipts/project-%d-installment-%d-v%d.pdf',
            $project->id,
            $invoice->installment_number,
            $invoice->version
        );
    }

    public function invoicePdfFilename(ClientProject $project, ClientProjectInvoice $invoice): string
    {
        $slug = Str::slug($project->name) ?: 'project-'.$project->id;

        return "invoice-{$invoice->invoice_number}-{$slug}.pdf";
    }

    public function receiptPdfFilename(ClientProject $project, ClientProjectInvoice $invoice): string
    {
        $slug = Str::slug($project->name) ?: 'project-'.$project->id;

        return "receipt-{$invoice->invoice_number}-{$slug}.pdf";
    }

    public function refundPdfStoragePath(ClientProject $project, ClientProjectInvoice $invoice): string
    {
        return sprintf(
            'refunds/project-%d-invoice-%d.pdf',
            $project->id,
            $invoice->id
        );
    }

    public function refundPdfFilename(ClientProject $project, ClientProjectInvoice $invoice): string
    {
        $slug = Str::slug($project->name) ?: 'project-'.$project->id;

        return "refund-{$invoice->invoice_number}-{$slug}.pdf";
    }
}

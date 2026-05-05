<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Mail\ClientProjectQuotationClient;
use App\Models\ClientCommunicationLog;
use App\Models\ClientProject;
use App\Models\ClientProjectQuotation;
use App\Models\FenceQuotation;
use App\Models\PavingQuotation;
use App\Models\StandardQuotation;
use App\Services\ClientCommunicationLogger;
use App\Services\ClientProjectStatusService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

/**
 * Manages the structured Quotation/Estimate attached to a ClientProject.
 * Line items come from Fence / Standard / Paving calculators; legacy manual
 * rows are read-only in the UI.
 *
 * Responsibilities here: edit() redirects into calculators, PDF, email, status.
 */
class ClientProjectQuotationController extends Controller
{
    /**
     * Linked Fence / Standard / Paving rows redirect into their standalone
     * calculators. Legacy manual rows have no editor — archived read-only on
     * the project's Quotation tab.
     */
    public function edit(ClientProject $client_project, ClientProjectQuotation $quotation)
    {
        $this->authorizeQuotation($client_project, $quotation);

        if ($quotation->isLegacyManualSource()) {
            return redirect()
                ->route('admin.client-projects.show', $client_project)
                ->withFragment('quotation')
                ->with('warning', 'This document was created with the retired line-item editor. It is read-only; create a new quotation from the hub if you need changes.');
        }

        $redirect = $this->redirectToSpecialisedEditorIfLinked($quotation);
        if ($redirect) {
            return $redirect;
        }

        abort(404);
    }

    protected function redirectToSpecialisedEditorIfLinked(ClientProjectQuotation $quotation): ?\Illuminate\Http\RedirectResponse
    {
        if ($quotation->source_type === ClientProjectQuotation::SOURCE_STANDARD && $quotation->source_id) {
            $sq = StandardQuotation::find($quotation->source_id);
            if ($sq) {
                return redirect()->route('admin.quotations.standard.edit', [$sq, 'return' => 'project']);
            }
        }
        if ($quotation->source_type === ClientProjectQuotation::SOURCE_FENCE && $quotation->source_id) {
            $fq = FenceQuotation::find($quotation->source_id);
            if ($fq) {
                return redirect()->route('admin.quotations.fence.edit', $fq);
            }
        }
        if ($quotation->source_type === ClientProjectQuotation::SOURCE_PAVING && $quotation->source_id) {
            $pq = PavingQuotation::find($quotation->source_id);
            if ($pq) {
                return redirect()->route('admin.quotations.paving.edit', $pq);
            }
        }

        return null;
    }

    protected function authorizeQuotation(ClientProject $project, ClientProjectQuotation $quotation): void
    {
        abort_unless((int) $quotation->client_project_id === (int) $project->id, 404);
    }

    /**
     * Render and stream the PDF inline (so the browser displays it instead
     * of downloading by default). Used by the "Download PDF" button.
     */
    public function downloadPdf(ClientProject $client_project, ClientProjectQuotation $quotation)
    {
        $this->authorizeQuotation($client_project, $quotation);

        if (!$quotation->hasContent()) {
            return back()->with('error', 'There is no quotation to download yet. Please add at least one line item first.');
        }

        $pdf = $this->renderPdf($client_project, $quotation);

        return $pdf->stream($this->pdfFilename($client_project, $quotation));
    }

    /**
     * Generate a fresh PDF, persist it under storage/app/quotations/, email
     * it to the client, and bump the version + sent_at.
     */
    public function send(ClientProject $client_project, ClientProjectQuotation $quotation, ClientProjectStatusService $statusService)
    {
        $this->authorizeQuotation($client_project, $quotation);

        if (!$quotation->hasContent()) {
            return back()->with('error', 'Please add at least one line item before sending the quotation.');
        }

        if (!$client_project->hasClientContactInfo()) {
            $missing = implode(', ', $client_project->missingClientContactFields());

            return back()->with('error', "Cannot send the {$quotation->typeLabel()} — missing client info: {$missing}.");
        }

        // If the quotation has already been sent at least once, the next save
        // is conceptually a new version (so we keep an audit trail of PDFs).
        if ($quotation->isSent()) {
            $quotation->version = (int) $quotation->version + 1;
        }

        $pdf = $this->renderPdf($client_project, $quotation);
        $bytes = $pdf->output();

        $relativePath = sprintf(
            'quotations/project-%d-q%d-v%d.pdf',
            $client_project->id,
            $quotation->id,
            $quotation->version
        );

        Storage::disk('local')->put($relativePath, $bytes);

        $quotation->pdf_path = $relativePath;
        $quotation->sent_at = now();
        if ($quotation->status !== ClientProjectQuotation::STATUS_ACCEPTED) {
            $quotation->status = ClientProjectQuotation::STATUS_SENT;
        }
        $quotation->save();

        $cc = $client_project->secondaryClientEmail();
        $quoteSubject = "{$quotation->typeLabel()} {$quotation->quotation_number} from Zanini Renovations";
        $quoteDescription = "{$quotation->typeLabel()} {$quotation->quotation_number} v{$quotation->version} — total "
            .ClientProject::formatMoney((float) $quotation->grandTotal());

        try {
            $mailer = Mail::to($client_project->client_email);
            if ($cc) {
                $mailer->cc($cc);
            }
            $mailer->send(new ClientProjectQuotationClient(
                $client_project,
                $quotation,
                $bytes,
                $this->pdfFilename($client_project, $quotation)
            ));

            app(ClientCommunicationLogger::class)->logEmail(
                $client_project,
                ClientCommunicationLog::REASON_QUOTATION_SENT,
                $client_project->client_email,
                $cc,
                $quoteSubject,
                $quoteDescription,
            );
        } catch (\Throwable $e) {
            Log::error('Failed to email quotation', [
                'project_id' => $client_project->id,
                'quotation_id' => $quotation->id,
                'exception' => $e->getMessage(),
            ]);

            app(ClientCommunicationLogger::class)->logEmail(
                $client_project,
                ClientCommunicationLog::REASON_QUOTATION_SENT,
                $client_project->client_email,
                $cc,
                $quoteSubject,
                $quoteDescription,
                ClientCommunicationLog::DIRECTION_TO_CLIENT,
                false,
                $e->getMessage(),
            );

            return redirect()
                ->route('admin.client-projects.show', $client_project)
                ->withFragment('quotation')
                ->with('error', 'The PDF was generated but the email could not be sent: '.$e->getMessage());
        }

        // Auto-transition the project to "Quote Sent" once the email leaves
        // successfully. Only promote from Visit scheduled or Quote Pending —
        // never regress a project that's already moved past this point (e.g.
        // proposal accepted, in progress, completed) just because the user
        // re-sent an updated quotation version.
        $sendNote = "{$quotation->typeLabel()} {$quotation->quotation_number} "
            ."emailed to {$client_project->clientEmailRecipientsLabel()} (v{$quotation->version}).";

        if ($client_project->isAwaitingQuotePreparation()) {
            try {
                $statusService->transition(
                    $client_project,
                    ClientProject::STATUS_QUOTE_SENT,
                    $sendNote
                );
            } catch (\Throwable $e) {
                // Don't fail the whole send flow if the status side-effects
                // blow up — the email already went out, the status is just
                // bookkeeping.
                Log::warning('Failed to auto-transition project to Quote Sent', [
                    'project_id' => $client_project->id,
                    'quotation_id' => $quotation->id,
                    'exception' => $e->getMessage(),
                ]);
            }
        } else {
            // Status already past pre-quote stages (e.g. re-sending an updated
            // quote to a client whose project is In Progress). Log an
            // event-only history row so the audit trail still shows the
            // send.
            try {
                $statusService->recordEvent($client_project, $sendNote);
            } catch (\Throwable $e) {
                Log::warning('Failed to record quotation-send history entry', [
                    'project_id' => $client_project->id,
                    'quotation_id' => $quotation->id,
                    'exception' => $e->getMessage(),
                ]);
            }
        }

        return redirect()
            ->route('admin.client-projects.show', $client_project)
            ->withFragment('quotation')
            ->with('success', "{$quotation->typeLabel()} {$quotation->quotation_number} emailed to {$client_project->clientEmailRecipientsLabel()} (v{$quotation->version}).");
    }

    /**
     * Set workflow status (draft / sent / accepted / rejected) for one quotation document.
     */
    public function updateStatus(Request $request, ClientProject $client_project, ClientProjectQuotation $quotation)
    {
        $this->authorizeQuotation($client_project, $quotation);

        $data = $request->validate([
            'status' => [
                'required',
                'string',
                Rule::in([
                    ClientProjectQuotation::STATUS_DRAFT,
                    ClientProjectQuotation::STATUS_SENT,
                    ClientProjectQuotation::STATUS_ACCEPTED,
                    ClientProjectQuotation::STATUS_REJECTED,
                ]),
            ],
        ]);

        $quotation->status = $data['status'];
        $quotation->save();

        $client_project->refresh();
        $client_project->syncFixedContractTotalsFromQuotation();

        return redirect()
            ->route('admin.client-projects.show', $client_project)
            ->withFragment('quotation')
            ->with('success', 'Quotation status updated.');
    }

    /**
     * Build the PDF object from the Blade template. Centralised so that
     * download/email use the exact same template + dompdf options as the
     * Contract feature (Roboto via @font-face, A4, remote enabled, html5
     * parser on).
     *
     * After loading the view we force ->render() so that the underlying
     * dompdf has finalised pagination, and only THEN do we stamp the
     * "Page X of Y" footer text via $canvas->page_text(). Doing this
     * inline from a <script type="text/php"> block doesn't work in
     * dompdf v2 because page_text() resolves the page count at call
     * time, before the additional pages exist (see pdf.blade.php for
     * the full explanation).
     */
    protected function renderPdf(ClientProject $project, ClientProjectQuotation $quotation)
    {
        $pdf = Pdf::loadView('admin.client_projects.quotation.pdf', [
            'project' => $project,
            'quotation' => $quotation,
        ])
            ->setPaper('a4')
            ->setOption('isRemoteEnabled', true)
            ->setOption('isHtml5ParserEnabled', true)
            ->setOption('defaultFont', 'Roboto');

        // Force layout so all pages are created.
        $pdf->render();

        $this->stampPageNumbers($pdf);

        return $pdf;
    }

    /**
     * Draw "Page X of Y" on every page of an already-rendered PDF.
     *
     * Coordinates are PDF points from the top-left of the page;
     * A4 = 595 × 842 pt. The right-edge x is computed against a
     * conservative max-width string so the text stays right-aligned
     * even when the count grows to two digits.
     */
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

    /** Stable, human-friendly filename for the generated PDF. */
    protected function pdfFilename(ClientProject $project, ClientProjectQuotation $quotation): string
    {
        $slug = Str::slug($project->name) ?: 'project-'.$project->id;
        $type = strtolower($quotation->typeLabel());

        return "{$type}-{$quotation->quotation_number}-{$slug}.pdf";
    }
}

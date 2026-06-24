<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Mail\ClientProjectQuotationClient;
use App\Models\ClientCommunicationLog;
use App\Models\ClientProject;
use App\Models\ClientProjectPhoto;
use App\Models\ClientProjectQuotation;
use App\Models\FenceQuotation;
use App\Models\FlooringQuotation;
use App\Models\PaintingQuotation;
use App\Models\PavingQuotation;
use App\Models\StairsQuotation;
use App\Models\StandardQuotation;
use App\Models\TileQuotation;
use App\Services\ClientCommunicationLogger;
use App\Services\ClientProjectStatusService;
use App\Services\ClientSmsService;
use App\Services\MoceanSmsService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

/**
 * Manages the structured Quotation/Estimate attached to a ClientProject.
 * Line items come from Fence / Standard / Paving / Stairs calculators; legacy manual
 * rows are read-only in the UI.
 *
 * Responsibilities here: edit() redirects into calculators, PDF, email, status.
 */
class ClientProjectQuotationController extends Controller
{
    /**
     * Linked Fence / Standard / Paving / Stairs rows redirect into their standalone
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
        if ($quotation->source_type === ClientProjectQuotation::SOURCE_STAIRS && $quotation->source_id) {
            $st = StairsQuotation::find($quotation->source_id);
            if ($st) {
                return redirect()->route('admin.quotations.stairs.edit', [$st, 'return' => 'project']);
            }
        }
        if ($quotation->source_type === ClientProjectQuotation::SOURCE_PAINTING && $quotation->source_id) {
            $pt = PaintingQuotation::find($quotation->source_id);
            if ($pt) {
                return redirect()->route('admin.quotations.painting.edit', [$pt, 'return' => 'project']);
            }
        }
        if ($quotation->source_type === ClientProjectQuotation::SOURCE_FLOORING && $quotation->source_id) {
            $fl = FlooringQuotation::find($quotation->source_id);
            if ($fl) {
                return redirect()->route('admin.quotations.flooring.edit', [$fl, 'return' => 'project']);
            }
        }
        if ($quotation->source_type === ClientProjectQuotation::SOURCE_TILE && $quotation->source_id) {
            $tl = TileQuotation::find($quotation->source_id);
            if ($tl) {
                return redirect()->route('admin.quotations.tile.edit', [$tl, 'return' => 'project']);
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
     * Public, signed URL (no login) so clients can open quotation PDFs from SMS.
     */
    public function guestDownloadQuotationPdf(ClientProject $client_project, ClientProjectQuotation $quotation)
    {
        abort_unless((int) $quotation->client_project_id === (int) $client_project->id, 404);

        if (!$quotation->hasContent()) {
            abort(404, 'Quotation not available.');
        }

        $pdf = $this->renderPdf($client_project, $quotation);

        return $pdf->stream($this->pdfFilename($client_project, $quotation));
    }

    /**
     * Generate a fresh PDF, persist it under storage/app/quotations/, email
     * it to the client, and bump the version + sent_at.
     */
    public function send(
        ClientProject $client_project,
        ClientProjectQuotation $quotation,
        ClientProjectStatusService $statusService,
    )
    {
        $this->authorizeQuotation($client_project, $quotation);

        if (!$quotation->hasContent()) {
            return back()->with('error', 'Please add at least one line item before sending the quotation.');
        }

        $emailTo = trim((string) $client_project->client_email);
        if ($emailTo === '') {
            return back()->with('error', "Cannot email the {$quotation->typeLabel()} — add the client's email address first.");
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

        $quoteSubject = "{$quotation->typeLabel()} {$quotation->quotation_number} from Zanini Renovations";
        $quoteDescription = "{$quotation->typeLabel()} {$quotation->quotation_number} v{$quotation->version} — total "
            .ClientProject::formatMoney((float) $quotation->grandTotal());
        $cc = $client_project->secondaryClientEmail();

        try {
            $mailer = Mail::to($emailTo);
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
                $emailTo,
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
                $emailTo,
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

        $deliverySummary = "emailed to {$client_project->clientEmailRecipientsLabel()}";
        $deliverySuccessFlash = "{$quotation->typeLabel()} {$quotation->quotation_number} emailed to {$client_project->clientEmailRecipientsLabel()} (v{$quotation->version}).";

        // Auto-transition the project to "Quote Sent" once the email leaves
        // successfully. Only promote from Visit scheduled or Quote Pending —
        // never regress a project that's already moved past this point (e.g.
        // proposal accepted, in progress, completed) just because the user
        // re-sent an updated quotation version.
        $sendNote = "{$quotation->typeLabel()} {$quotation->quotation_number} {$deliverySummary} (v{$quotation->version}).";

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
            ->with('success', $deliverySuccessFlash);
    }

    /**
     * SMS the client a link to open/view the quotation PDF (no attachment).
     * Does not email or bump quotation version — purely a link delivery channel.
     */
    public function sendQuotationSms(
        ClientProject $client_project,
        ClientProjectQuotation $quotation,
        ClientSmsService $clientSms,
        MoceanSmsService $moceanSms,
    ) {
        $this->authorizeQuotation($client_project, $quotation);

        $blocked = $this->quotationSmsBlockers($client_project, $quotation, $moceanSms);
        if ($blocked !== []) {
            return back()->with('error', implode(' ', $blocked));
        }

        $smsTo = ClientProject::phoneDigitsForSms($client_project->client_phone);
        if ($smsTo === null) {
            return back()->with('error', 'Cannot send SMS — client phone number is missing or not valid for texting.');
        }

        $signedUrl = $this->signedQuotationShareUrl($client_project, $quotation);
        $totalFmt = ClientProject::formatMoney((float) $quotation->grandTotal());
        $smsBody = "Zanini Renovations — {$quotation->typeLabel()} {$quotation->quotation_number}. Total: {$totalFmt}. View PDF: {$signedUrl}";

        $summary = "{$quotation->typeLabel()} {$quotation->quotation_number} SMS link — {$totalFmt} — \"".Str::limit($smsBody, 120).'"';
        $result = $clientSms->sendToClient(
            $client_project,
            $smsTo,
            $smsBody,
            ClientCommunicationLog::REASON_QUOTATION_SMS_LINK,
            $summary,
        );

        if (! $result['ok']) {
            return back()->with('error', 'SMS could not be sent: '.$result['detail']);
        }

        return redirect()
            ->route('admin.client-projects.show', $client_project)
            ->withFragment('quotation')
            ->with('success', 'SMS sent to the client with a quotation link.');
    }

    /**
     * Set workflow status (draft / sent / accepted / rejected) for one quotation document.
     */
    public function updateStatus(
        Request $request,
        ClientProject $client_project,
        ClientProjectQuotation $quotation,
        ClientProjectStatusService $statusService,
    ) {
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

        if ($data['status'] === ClientProjectQuotation::STATUS_ACCEPTED) {
            $acceptNote = "{$quotation->typeLabel()} {$quotation->quotation_number} marked accepted.";

            if ($client_project->canPromoteToProposalAcceptedFromQuotation()) {
                try {
                    $statusService->transition(
                        $client_project,
                        ClientProject::STATUS_PROPOSAL_ACCEPTED,
                        $acceptNote,
                    );
                } catch (\Throwable $e) {
                    Log::warning('Failed to auto-transition project to Proposal Accepted', [
                        'project_id' => $client_project->id,
                        'quotation_id' => $quotation->id,
                        'exception' => $e->getMessage(),
                    ]);
                }
            } else {
                try {
                    $statusService->recordEvent($client_project, $acceptNote);
                } catch (\Throwable $e) {
                    Log::warning('Failed to record quotation-accepted history entry', [
                        'project_id' => $client_project->id,
                        'quotation_id' => $quotation->id,
                        'exception' => $e->getMessage(),
                    ]);
                }
            }
        }

        return redirect()
            ->route('admin.client-projects.show', $client_project)
            ->withFragment('quotation')
            ->with('success', 'Quotation status updated.');
    }

    /**
     * Optional pre-tax subtotal override (non-zero replaces the sum of line items;
     * HST is scaled to match the line items' taxable / exempt split).
     */
    public function updateSubtotalOverride(
        Request $request,
        ClientProject $client_project,
        ClientProjectQuotation $quotation,
    ) {
        $this->authorizeQuotation($client_project, $quotation);

        if ($quotation->isLegacyManualSource()) {
            return redirect()
                ->route('admin.client-projects.show', $client_project)
                ->withFragment('quotation')
                ->with('error', 'Legacy documents cannot be edited here.');
        }

        if (! $quotation->hasContent()) {
            return redirect()
                ->route('admin.client-projects.show', $client_project)
                ->withFragment('quotation')
                ->with('error', 'Add line items before setting a subtotal override.');
        }

        $rawIn = $request->input('subtotal_override');
        if ($rawIn === null || $rawIn === '') {
            $normalized = null;
        } else {
            $normalized = str_replace([',', ' ', "\u{00A0}"], '', (string) $rawIn);
            $normalized = $normalized === '' ? null : $normalized;
        }
        $request->merge(['subtotal_override' => $normalized]);

        $data = $request->validate([
            'subtotal_override' => ['nullable', 'numeric', 'min:0'],
        ]);

        $raw = $data['subtotal_override'] ?? null;
        if ($raw === null) {
            $quotation->subtotal_override = null;
        } else {
            $v = round((float) $raw, 2);
            $quotation->subtotal_override = $v === 0.0 ? null : $v;
        }

        $quotation->save();

        $client_project->refresh();
        $client_project->syncFixedContractTotalsFromQuotation();

        return redirect()
            ->route('admin.client-projects.show', $client_project)
            ->withFragment('quotation')
            ->with('success', 'Subtotal override updated.');
    }

    /** Choose which project images are appended to the client PDF. */
    public function updatePdfPhotos(
        Request $request,
        ClientProject $client_project,
        ClientProjectQuotation $quotation,
    ) {
        $this->authorizeQuotation($client_project, $quotation);

        if ($quotation->isLegacyManualSource()) {
            return redirect()
                ->route('admin.client-projects.show', $client_project)
                ->withFragment('quotation')
                ->with('error', 'Legacy documents cannot be edited here.');
        }

        $data = $request->validate([
            'pdf_photo_ids' => ['nullable', 'array', 'max:15'],
            'pdf_photo_ids.*' => ['integer'],
        ]);

        $submitted = array_values(array_unique(array_map('intval', $data['pdf_photo_ids'] ?? [])));
        $validIdSet = ClientProjectPhoto::query()
            ->where('client_project_id', $client_project->id)
            ->whereIn('id', $submitted)
            ->get()
            ->filter(fn (ClientProjectPhoto $photo) => $photo->isImage())
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();

        $ordered = array_values(array_filter(
            $submitted,
            fn (int $id) => in_array($id, $validIdSet, true)
        ));

        $quotation->pdf_photo_ids = $ordered === [] ? null : $ordered;
        $quotation->save();

        $count = count($ordered);
        $message = $count === 0
            ? 'No photos will be attached to the client PDF.'
            : ($count === 1
                ? '1 photo will be attached to the client PDF.'
                : "{$count} photos will be attached to the client PDF.");

        return redirect()
            ->route('admin.client-projects.show', $client_project)
            ->withFragment('quotation')
            ->with('success', $message);
    }

    /**
     * Remove a calculator-linked quotation from the project and delete the
     * underlying Fence / Standard / Paving / Stairs record (same net effect as the hub).
     */
    public function destroy(ClientProject $client_project, ClientProjectQuotation $quotation)
    {
        $this->authorizeQuotation($client_project, $quotation);

        if ($quotation->isLegacyManualSource()) {
            return redirect()
                ->route('admin.client-projects.show', $client_project)
                ->withFragment('quotation')
                ->with('error', 'Legacy documents cannot be deleted from here.');
        }

        $number = $quotation->quotation_number;
        ClientProjectQuotation::deleteWithLinkedCalculatorSource($quotation);

        return redirect()
            ->route('admin.client-projects.show', $client_project)
            ->withFragment('quotation')
            ->with('success', 'Quotation '.$number.' was removed from this project.');
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
    protected function signedQuotationShareUrl(ClientProject $project, ClientProjectQuotation $quotation): string
    {
        return URL::temporarySignedRoute(
            'public.client-project.quotation-pdf',
            now()->addYear(),
            ['client_project' => $project, 'quotation' => $quotation],
        );
    }

    /**
     * @return list<string>
     */
    protected function quotationSmsBlockers(
        ClientProject $project,
        ClientProjectQuotation $quotation,
        MoceanSmsService $mocean,
    ): array {
        $out = [];

        if (! $quotation->hasContent()) {
            $out[] = 'Add at least one line item before sending an SMS link.';
        }

        if (! $mocean->isConfigured()) {
            $out[] = 'SMS is not configured — set MOCEAN_API_TOKEN on the server.';
        }

        if (ClientProject::phoneDigitsForSms($project->client_phone) === null) {
            $out[] = 'Add a mobile phone number the client uses for texting (save on Edit project).';
        }

        return $out;
    }

    protected function renderPdf(ClientProject $project, ClientProjectQuotation $quotation)
    {
        $pdf = Pdf::loadView('admin.client_projects.quotation.pdf', [
            'project' => $project,
            'quotation' => $quotation,
            'pdfPhotos' => $quotation->pdfPhotosForRendering($project),
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

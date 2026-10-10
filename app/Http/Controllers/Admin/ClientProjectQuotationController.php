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
use App\Models\LowDeckQuotation;
use App\Models\PaintingQuotation;
use App\Models\PavingQuotation;
use App\Models\StairsQuotation;
use App\Models\StandardQuotation;
use App\Models\BasementQuotation;
use App\Models\ShedQuotation;
use App\Models\TrimmingQuotation;
use App\Models\MillworkQuotation;
use App\Models\TileQuotation;
use App\Services\ClientCommunicationLogger;
use App\Services\ClientProjectStatusService;
use App\Services\ClientSmsService;
use App\Services\MoceanSmsService;
use App\Support\SignedPublicLink;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
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
        if ($quotation->source_type === ClientProjectQuotation::SOURCE_LOW_DECK && $quotation->source_id) {
            $ld = LowDeckQuotation::find($quotation->source_id);
            if ($ld) {
                return redirect()->route('admin.quotations.low-deck.edit', $ld);
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
        if ($quotation->source_type === ClientProjectQuotation::SOURCE_BASEMENT && $quotation->source_id) {
            $br = BasementQuotation::find($quotation->source_id);
            if ($br) {
                return redirect()->route('admin.quotations.basement.edit', [$br, 'return' => 'project']);
            }
        }
        if ($quotation->source_type === ClientProjectQuotation::SOURCE_SHED && $quotation->source_id) {
            $sh = ShedQuotation::find($quotation->source_id);
            if ($sh) {
                return redirect()->route('admin.quotations.shed.edit', [$sh, 'return' => 'project']);
            }
        }
        if ($quotation->source_type === ClientProjectQuotation::SOURCE_TRIMMING && $quotation->source_id) {
            $tm = TrimmingQuotation::find($quotation->source_id);
            if ($tm) {
                return redirect()->route('admin.quotations.trimming.edit', [$tm, 'return' => 'project']);
            }
        }
        if ($quotation->source_type === ClientProjectQuotation::SOURCE_MILLWORK && $quotation->source_id) {
            $mw = MillworkQuotation::find($quotation->source_id);
            if ($mw) {
                return redirect()->route('admin.quotations.millwork.edit', [$mw, 'return' => 'project']);
            }
        }

        return null;
    }

    protected function authorizeQuotation(ClientProject $project, ClientProjectQuotation $quotation): void
    {
        abort_unless((int) $quotation->client_project_id === (int) $project->id, 404);
    }

    /**
     * Client PDF / send must not produce a file while millwork parts are unplaced.
     */
    protected function rejectDraftSource(ClientProjectQuotation $quotation)
    {
        if (! $quotation->isSourceDraft()) {
            return null;
        }

        return back()->with('error', ClientProjectQuotation::SOURCE_DRAFT_SHARE_MESSAGE);
    }

    protected function rejectUnplacedMillworkPdf(ClientProjectQuotation $quotation)
    {
        if ($quotation->source_type !== ClientProjectQuotation::SOURCE_MILLWORK) {
            return null;
        }
        $millwork = $quotation->source_id
            ? MillworkQuotation::query()->find($quotation->source_id)
            : null;
        if (! $millwork || ! $millwork->hasUnplacedCutParts()) {
            return null;
        }

        $isAdmin = auth()->check();
        $message = $isAdmin
            ? MillworkQuotation::unplacedAdminMessage()
            : MillworkQuotation::unplacedClientMessage();
        $view = $isAdmin
            ? 'admin.quotations.millwork.unplaced_blocked'
            : 'site.quotations.unplaced_blocked';

        return response()->view($view, [
            'message' => $message,
            'quotation' => $quotation,
        ], 422);
    }

    /**
     * Render and stream the PDF inline (so the browser displays it instead
     * of downloading by default). Used by the "Download PDF" button.
     */
    public function downloadPdf(ClientProject $client_project, ClientProjectQuotation $quotation)
    {
        $this->authorizeQuotation($client_project, $quotation);

        if ($response = $this->rejectDraftSource($quotation)) {
            return $response;
        }

        if (!$quotation->hasContent()) {
            return back()->with('error', 'There is no quotation to download yet. Please add at least one line item first.');
        }

        if ($response = $this->rejectUnplacedMillworkPdf($quotation)) {
            return $response;
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

        if ($quotation->isSourceDraft()) {
            abort(404, 'Quotation not available.');
        }

        if (!$quotation->hasContent()) {
            abort(404, 'Quotation not available.');
        }

        if ($response = $this->rejectUnplacedMillworkPdf($quotation)) {
            return $response;
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

        if ($response = $this->rejectDraftSource($quotation)) {
            return $response;
        }

        if (!$quotation->hasContent()) {
            return back()->with('error', 'Please add at least one line item before sending the quotation.');
        }

        if ($this->millworkHasUnplacedParts($quotation)) {
            return back()->with('error', MillworkQuotation::unplacedAdminMessage());
        }

        $emailTo = trim((string) $client_project->client_email);
        if ($emailTo === '') {
            return back()->with('error', "Cannot email the {$quotation->typeLabel()} — add the client's email address first.");
        }

        $publishOnly = $quotation->missingPublishOnlyChoices();
        if ($publishOnly !== []) {
            return back()->with('error', $publishOnly[0]);
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

        $quoteSubject = ClientProjectQuotationClient::subjectFor($client_project, $quotation);
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
                    ClientProjectQuotation::STATUS_SUPERSEDED,
                ]),
            ],
        ]);

        if ($data['status'] === ClientProjectQuotation::STATUS_ACCEPTED) {
            if ($this->millworkHasUnplacedParts($quotation)) {
                return redirect()
                    ->route('admin.client-projects.show', $client_project)
                    ->withFragment('quotation')
                    ->with('error', MillworkQuotation::unplacedAdminMessage());
            }
            $result = app(\App\Services\QuoteAcceptanceService::class)->accept(
                $quotation,
                ClientProjectQuotation::ACCEPTED_VIA_ADMIN,
                $request->ip(),
                optional($request->user())->email,
            );

            if ($result === \App\Services\QuoteAcceptanceService::STATUS_INVALID_STATE
                || $result === \App\Services\QuoteAcceptanceService::STATUS_INCOMPLETE) {
                return redirect()
                    ->route('admin.client-projects.show', $client_project)
                    ->withFragment('quotation')
                    ->with('error', $result === \App\Services\QuoteAcceptanceService::STATUS_INCOMPLETE
                        ? MillworkQuotation::unplacedAdminMessage()
                        : 'This quotation cannot be accepted in its current state.');
            }

            return redirect()
                ->route('admin.client-projects.show', $client_project)
                ->withFragment('quotation')
                ->with('success', 'Quotation status updated.');
        }

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
            SignedPublicLink::expiresAt(),
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

        if ($quotation->isSourceDraft()) {
            $out[] = ClientProjectQuotation::SOURCE_DRAFT_SHARE_MESSAGE;
        }

        if (! $quotation->hasContent()) {
            $out[] = 'Add at least one line item before sending an SMS link.';
        }
        if ($this->millworkHasUnplacedParts($quotation)) {
            $out[] = MillworkQuotation::unplacedAdminMessage();
        }

        if (! $mocean->isConfigured()) {
            $out[] = 'SMS is not configured — set MOCEAN_API_TOKEN on the server.';
        }

        if (ClientProject::phoneDigitsForSms($project->client_phone) === null) {
            $out[] = 'Add a mobile phone number the client uses for texting (save on Edit project).';
        }

        foreach ($quotation->missingPublishOnlyChoices() as $message) {
            $out[] = $message;
        }

        return $out;
    }

    protected function renderPdf(ClientProject $project, ClientProjectQuotation $quotation)
    {
        $pdf = Pdf::loadView('admin.client_projects.quotation.pdf', [
            'project' => $project,
            'quotation' => $quotation,
            'pdfPhotos' => $quotation->pdfPhotosForRendering($project),
            'acceptUrl' => $quotation->signedAcceptUrl(),
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

    /**
     * Duplicate a quotation as a new draft, optionally on a different project.
     */
    public function duplicate(
        Request $request,
        ClientProject $client_project,
        ClientProjectQuotation $quotation,
    ) {
        $this->authorizeQuotation($client_project, $quotation);

        if ($quotation->isLegacyManualSource()) {
            return back()->with('error', 'Legacy manual documents cannot be duplicated.');
        }

        $targetProjectId = $request->input('target_project_id');
        $targetProject = $targetProjectId
            ? ClientProject::find($targetProjectId)
            : $client_project;

        if (! $targetProject) {
            return back()->with('error', 'Target project not found.');
        }

        if (in_array($targetProject->status, ClientProject::statusesClosedToNewQuotations(), true)) {
            return back()->with('error', 'The target project no longer accepts new quotations.');
        }

        $newQuotation = DB::transaction(function () use ($quotation, $targetProject) {
            $newSourceId = null;
            $sourceType = $quotation->source_type;

            if ($quotation->source_id && $sourceType) {
                $newSourceId = $this->duplicateCalculatorSource($quotation, $targetProject);
            }

            $newNumber = ClientProjectQuotation::generateNumber($quotation->type);

            $newQuotation = new ClientProjectQuotation();
            $newQuotation->fill([
                'client_project_id' => $targetProject->id,
                'title' => $quotation->title,
                'status' => ClientProjectQuotation::STATUS_DRAFT,
                'source_type' => $sourceType,
                'source_id' => $newSourceId,
                'source_snapshot' => $quotation->source_snapshot,
                'type' => $quotation->type,
                'labour' => $quotation->labour,
                'material' => $quotation->material,
                'items' => $quotation->items,
                'work_description' => $quotation->work_description,
                'payment_installments' => $quotation->payment_installments,
                'payment_percentages' => $quotation->payment_percentages,
                'quotation_number' => $newNumber,
                'version' => 1,
                'pdf_fold_version' => $quotation->pdf_fold_version ?? 1,
            ]);
            $newQuotation->save();

            return $newQuotation;
        });

        $message = "Duplicated as {$newQuotation->quotation_number}";
        if ($targetProject->id !== $client_project->id) {
            $message .= " on project \"{$targetProject->name}\"";
        }
        $message .= '.';

        return redirect()
            ->route('admin.client-projects.show', $targetProject)
            ->withFragment('quotation')
            ->with('success', $message);
    }

    /**
     * Search projects for the duplicate modal picker.
     */
    public function searchProjectsForDuplicate(Request $request): \Illuminate\Http\JsonResponse
    {
        $q = trim((string) $request->input('q', ''));
        if (mb_strlen($q) < 2) {
            return response()->json([]);
        }

        $closedStatuses = ClientProject::statusesClosedToNewQuotations();

        $projects = ClientProject::query()
            ->whereNotIn('status', $closedStatuses)
            ->where(function ($sub) use ($q) {
                $sub->where('name', 'like', '%' . $q . '%')
                    ->orWhere('client_name', 'like', '%' . $q . '%');
            })
            ->orderByDesc('updated_at')
            ->limit(20)
            ->get(['id', 'name', 'client_name']);

        return response()->json($projects->map(fn ($p) => [
            'id' => $p->id,
            'name' => $p->name,
            'label' => $p->client_name . ' — ' . $p->name,
        ])->values());
    }

    /**
     * Duplicate the linked calculator source record for a new quotation.
     */
    protected function duplicateCalculatorSource(
        ClientProjectQuotation $quotation,
        ClientProject $targetProject,
    ): ?int {
        $sourceType = $quotation->source_type;
        $sourceId = $quotation->source_id;

        if (! $sourceId) {
            return null;
        }

        $sourceModel = $quotation->sourceQuotationModel();
        if (! $sourceModel) {
            return null;
        }

        $newData = $sourceModel->toArray();
        unset($newData['id'], $newData['created_at'], $newData['updated_at']);
        $newData['client_project_id'] = $targetProject->id;

        if (isset($newData['quotation_number'])) {
            $newData['quotation_number'] = $this->generateCalculatorNumber($sourceType);
        }

        $class = get_class($sourceModel);
        $newSource = new $class();
        $newSource->fill($newData);
        $newSource->save();

        return $newSource->id;
    }

    /**
     * Generate a new quotation number for a calculator source type.
     */
    protected function generateCalculatorNumber(string $sourceType): string
    {
        $prefix = match ($sourceType) {
            ClientProjectQuotation::SOURCE_FENCE => 'FN',
            ClientProjectQuotation::SOURCE_PAVING => 'PV',
            ClientProjectQuotation::SOURCE_STANDARD => 'SQ',
            ClientProjectQuotation::SOURCE_STAIRS => 'ST',
            ClientProjectQuotation::SOURCE_PAINTING => 'PT',
            ClientProjectQuotation::SOURCE_FLOORING => 'FL',
            ClientProjectQuotation::SOURCE_TILE => 'TL',
            ClientProjectQuotation::SOURCE_LOW_DECK => 'LD',
            ClientProjectQuotation::SOURCE_BASEMENT => 'BR',
            ClientProjectQuotation::SOURCE_SHED => 'SH',
            ClientProjectQuotation::SOURCE_TRIMMING => 'TR',
            ClientProjectQuotation::SOURCE_MILLWORK => 'MW',
            default => 'QT',
        };

        $year = (int) date('Y');
        $next = random_int(1, 9999);

        return sprintf('%s-%d-%04d', $prefix, $year, $next);
    }

    protected function millworkHasUnplacedParts(ClientProjectQuotation $quotation): bool
    {
        if ($quotation->source_type !== ClientProjectQuotation::SOURCE_MILLWORK || ! $quotation->source_id) {
            return false;
        }
        $millwork = MillworkQuotation::query()->find($quotation->source_id);

        return $millwork?->hasUnplacedCutParts() ?? false;
    }
}

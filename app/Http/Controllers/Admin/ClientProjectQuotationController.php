<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Mail\ClientProjectQuotationClient;
use App\Models\ClientCommunicationLog;
use App\Models\ClientProject;
use App\Models\ClientProjectQuotation;
use App\Services\ClientCommunicationLogger;
use App\Services\ClientProjectStatusService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Manages the structured Quotation/Estimate attached to a ClientProject.
 *
 * Mirrors ClientProjectContractController exactly so the two flows
 * behave identically from the user's perspective:
 *   - edit()         shows the structured editor (creating the row on demand)
 *   - update()       saves type/labour/material/items (Save Draft / Save & Close)
 *   - downloadPdf()  generates a fresh PDF on the fly and returns it inline
 *   - send()         generates a fresh PDF, persists it under storage/app/quotations/,
 *                    emails it to the client, and updates sent_at + version
 */
class ClientProjectQuotationController extends Controller
{
    /**
     * Show the editor. Creates an empty quotation row if one doesn't
     * exist yet so the form has something to bind against.
     */
    public function edit(ClientProject $client_project)
    {
        $quotation = $this->firstOrCreateQuotation($client_project);

        return view('admin.client_projects.quotation.edit', [
            'project' => $client_project,
            'quotation' => $quotation,
        ]);
    }

    /**
     * Save the quotation. The "intent" field controls where we redirect:
     * "draft" stays on the editor, "close" goes back to the Quotation tab.
     */
    public function update(Request $request, ClientProject $client_project)
    {
        $data = $request->validate([
            'type' => ['required', 'in:quotation,estimate'],
            'labour' => ['nullable'],
            'material' => ['nullable'],
            'items' => ['nullable', 'array'],
            'items.*.description' => ['nullable', 'string', 'max:2000'],
            'items.*.amount' => ['nullable', 'numeric'],
            'items.*.apply_hst' => ['nullable'],
            'work_description' => ['nullable', 'string', 'max:5000'],
            'intent' => ['nullable', 'in:draft,close'],
        ]);

        $quotation = $this->firstOrCreateQuotation($client_project);

        // Toggle the prefix (QUO/EST) when type changes BEFORE the quotation
        // has been sent — this keeps the displayed number consistent with
        // the current type. After the first send, the number is locked.
        $newType = $data['type'];
        if (!$quotation->isSent() && $quotation->type !== $newType) {
            $quotation->quotation_number = $this->swapNumberPrefix(
                $quotation->quotation_number,
                $newType
            );
        }

        $quotation->type = $newType;
        $quotation->labour = $request->boolean('labour');
        $quotation->material = $request->boolean('material');
        $quotation->items = $this->normaliseItems($data['items'] ?? []);

        $workDescription = trim((string) ($data['work_description'] ?? ''));
        $quotation->work_description = $workDescription === '' ? null : $workDescription;

        $quotation->save();

        $client_project->refresh();
        $client_project->syncFixedContractTotalsFromQuotation();

        $intent = $data['intent'] ?? 'draft';

        if ($intent === 'close') {
            return redirect()
                ->route('admin.client-projects.show', $client_project)
                ->with('success', $quotation->typeLabel().' saved.')
                ->withFragment('quotation');
        }

        return redirect()
            ->route('admin.client-projects.quotation.edit', $client_project)
            ->with('success', 'Draft saved.');
    }

    /**
     * Render and stream the PDF inline (so the browser displays it instead
     * of downloading by default). Used by the "Download PDF" button.
     */
    public function downloadPdf(ClientProject $client_project)
    {
        $quotation = $client_project->quotation;

        if (!$quotation || !$quotation->hasContent()) {
            return back()->with('error', 'There is no quotation to download yet. Please add at least one line item first.');
        }

        $pdf = $this->renderPdf($client_project, $quotation);

        return $pdf->stream($this->pdfFilename($client_project, $quotation));
    }

    /**
     * Generate a fresh PDF, persist it under storage/app/quotations/, email
     * it to the client, and bump the version + sent_at.
     */
    public function send(ClientProject $client_project, ClientProjectStatusService $statusService)
    {
        $quotation = $client_project->quotation;

        if (!$quotation || !$quotation->hasContent()) {
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
            'quotations/project-%d-v%d.pdf',
            $client_project->id,
            $quotation->version
        );

        Storage::disk('local')->put($relativePath, $bytes);

        $quotation->pdf_path = $relativePath;
        $quotation->sent_at = now();
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

    /**
     * Get-or-create the quotation row. We assign the number lazily on
     * first creation so the user can land on the editor without dirtying
     * the sequence if they change their mind and never save anything.
     *
     * Note: firstOrCreate() is atomic via the unique(client_project_id)
     * constraint on the table — concurrent requests can't create two rows.
     */
    protected function firstOrCreateQuotation(ClientProject $project): ClientProjectQuotation
    {
        return $project->quotation()->firstOrCreate(
            [],
            [
                'type' => ClientProjectQuotation::TYPE_QUOTATION,
                'labour' => true,
                'material' => true,
                'items' => [],
                'quotation_number' => ClientProjectQuotation::generateNumber(ClientProjectQuotation::TYPE_QUOTATION),
                'version' => 1,
            ]
        );
    }

    /**
     * Filter empty rows out of the items array and coerce each amount to
     * a positive float. Stored back into `items` (cast as JSON).
     */
    protected function normaliseItems(array $rows): array
    {
        $clean = [];
        foreach ($rows as $row) {
            $description = trim((string) ($row['description'] ?? ''));
            $amount = (float) ($row['amount'] ?? 0);
            if ($description === '' && $amount == 0.0) {
                continue;
            }
            $applyHst = !array_key_exists('apply_hst', $row)
                || filter_var($row['apply_hst'], FILTER_VALIDATE_BOOLEAN);
            $clean[] = [
                'description' => $description,
                'amount' => round($amount, 2),
                'apply_hst' => $applyHst,
            ];
        }

        return $clean;
    }

    /**
     * Replace just the prefix of a quotation number ("QUO-2026-0327" →
     * "EST-2026-0327"). Used when the user toggles type before sending.
     */
    protected function swapNumberPrefix(?string $number, string $newType): ?string
    {
        if (!$number) {
            return ClientProjectQuotation::generateNumber($newType);
        }

        $newPrefix = $newType === ClientProjectQuotation::TYPE_ESTIMATE ? 'EST' : 'QUO';

        return preg_replace('/^[A-Z]+/', $newPrefix, $number);
    }
}

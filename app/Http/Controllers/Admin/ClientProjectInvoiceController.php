<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Mail\ClientProjectInvoiceClient;
use App\Mail\ClientProjectInvoiceReceiptClient;
use App\Mail\ClientProjectInvoiceReminderClient;
use App\Models\ClientProject;
use App\Models\ClientProjectInvoice;
use App\Services\ClientProjectStatusService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Manages invoices attached to a ClientProject.
 *
 * Mirrors ClientProjectQuotationController:
 *   - create()           installment dropdown → lazily creates invoice row, redirects to editor
 *   - edit()             shows the structured editor
 *   - update()           saves items + (manual) description (Save Draft / Save & Close)
 *   - downloadPdf()      streams a freshly-generated invoice PDF inline
 *   - downloadReceipt()  streams the stored receipt PDF (only after payment)
 *   - send()             generates invoice PDF, emails it, marks status = sent
 *   - sendReminder()     emails a payment reminder (no new PDF)
 *   - markPaid()         accepts date + amount, generates a Receipt PDF, emails
 *                        it, transitions status = paid, and auto-marks the
 *                        linked Financials installment as paid when present.
 *   - destroy()          deletes the invoice (and its PDFs) while it is still a draft.
 *
 * Totals are always computed server-side from the raw items + HST rate,
 * so a tampered client-side grand total can't slip past validation.
 */
class ClientProjectInvoiceController extends Controller
{
    /**
     * Create a new invoice (linked to an installment, or manual) and
     * redirect to its editor. If an invoice already exists for the
     * requested installment, we just reopen that one instead of
     * duplicating.
     */
    public function create(Request $request, ClientProject $client_project)
    {
        $data = $request->validate([
            'installment_number' => [
                'nullable',
                'integer',
                'min:1',
                'max:4',
            ],
            'mode' => ['nullable', 'in:installment,manual'],
        ]);

        $mode = $data['mode'] ?? ($request->filled('installment_number') ? 'installment' : 'manual');
        $installmentNumber = $mode === 'installment' ? (int) ($data['installment_number'] ?? 0) : null;

        if ($mode === 'installment') {
            if (!$installmentNumber) {
                return back()->with('error', 'Please pick an installment for the new invoice.');
            }

            // Only allow creating invoices for installments that actually
            // exist in the current payment plan.
            $installments = $client_project->installments;
            if ($installments->count() < $installmentNumber) {
                return back()->with('error', 'That installment does not exist on the current payment plan.');
            }

            $existing = $client_project->invoices()
                ->where('installment_number', $installmentNumber)
                ->first();

            if ($existing) {
                return redirect()
                    ->route('admin.client-projects.invoices.edit', [$client_project, $existing])
                    ->with('info', 'An invoice already exists for this installment — opening the editor.');
            }
        }

        $invoice = $this->createInvoice($client_project, $installmentNumber);

        return redirect()
            ->route('admin.client-projects.invoices.edit', [$client_project, $invoice]);
    }

    public function edit(ClientProject $client_project, ClientProjectInvoice $invoice)
    {
        $this->authorizeInvoiceForProject($client_project, $invoice);

        return view('admin.client_projects.invoice.edit', [
            'project' => $client_project,
            'invoice' => $invoice,
            'lockedTotalWithHst' => $this->lockedTotalWithHst($client_project, $invoice),
            'lockedSubtotal' => $this->lockedSubtotal($client_project, $invoice),
        ]);
    }

    /**
     * Save the invoice. The "intent" field controls redirect target:
     *   - "draft" → stay on the editor
     *   - "close" → back to the Invoice tab
     */
    public function update(Request $request, ClientProject $client_project, ClientProjectInvoice $invoice)
    {
        $this->authorizeInvoiceForProject($client_project, $invoice);

        if ($invoice->isPaid()) {
            return back()->with('error', 'Paid invoices cannot be edited.');
        }

        $data = $request->validate([
            'items' => ['nullable', 'array'],
            'items.*.description' => ['nullable', 'string', 'max:2000'],
            'items.*.amount' => ['nullable', 'numeric'],
            'manual_description' => ['nullable', 'string', 'max:255'],
            'manual_total' => ['nullable', 'numeric', 'min:0'],
            'due_on' => ['nullable', 'date'],
            'intent' => ['nullable', 'in:draft,close'],
        ]);

        $items = $this->normaliseItems($data['items'] ?? []);

        // For manual invoices, the user sets the pre-HST total directly
        // via the manual_total field; we only persist it when present.
        if ($invoice->isManual() && isset($data['manual_description'])) {
            $invoice->installment_description = trim((string) $data['manual_description']) ?: 'Manual invoice';
        }

        $lockedSubtotal = $this->lockedSubtotal($client_project, $invoice, $data['manual_total'] ?? null);

        $invoice->items = $items;
        $invoice->subtotal = (float) array_sum(array_column($items, 'amount'));
        $invoice->hst = round($invoice->subtotal * ClientProjectInvoice::HST_RATE, 2);
        $invoice->total = round($invoice->subtotal + $invoice->hst, 2);

        if ($invoice->isManual() && $data['manual_total'] !== null) {
            // Manual invoices: if the items don't add up to the user-entered
            // manual_total, we still persist a draft so work isn't lost — the
            // UI surfaces a validation warning. For the "send" action this
            // mismatch is a hard stop (see send()).
            $invoice->subtotal = (float) $data['manual_total'];
            $invoice->hst = round($invoice->subtotal * ClientProjectInvoice::HST_RATE, 2);
            $invoice->total = round($invoice->subtotal + $invoice->hst, 2);

            if (abs((float) array_sum(array_column($items, 'amount')) - (float) $data['manual_total']) > 0.01 && count($items)) {
                // Fall back to items-sum as the authoritative subtotal if
                // the user actually typed line items (prefer item sum).
                $invoice->subtotal = (float) array_sum(array_column($items, 'amount'));
                $invoice->hst = round($invoice->subtotal * ClientProjectInvoice::HST_RATE, 2);
                $invoice->total = round($invoice->subtotal + $invoice->hst, 2);
            }
        }

        if (!empty($data['due_on'])) {
            $invoice->due_on = $data['due_on'];
        }

        $invoice->save();

        $intent = $data['intent'] ?? 'draft';

        if ($intent === 'close') {
            return redirect()
                ->route('admin.client-projects.show', $client_project)
                ->with('success', 'Invoice saved.')
                ->withFragment('invoice');
        }

        return redirect()
            ->route('admin.client-projects.invoices.edit', [$client_project, $invoice])
            ->with('success', 'Draft saved.');
    }

    public function downloadPdf(ClientProject $client_project, ClientProjectInvoice $invoice)
    {
        $this->authorizeInvoiceForProject($client_project, $invoice);

        if (!$invoice->hasContent() && $invoice->total <= 0) {
            return back()->with('error', 'There is no invoice content to download yet. Please add at least one line item first.');
        }

        $pdf = $this->renderInvoicePdf($client_project, $invoice);

        return $pdf->stream($this->invoicePdfFilename($client_project, $invoice));
    }

    /**
     * Stream the stored receipt PDF (generated at mark-paid time). If the
     * stored file is missing for any reason we regenerate on demand.
     */
    public function downloadReceipt(ClientProject $client_project, ClientProjectInvoice $invoice)
    {
        $this->authorizeInvoiceForProject($client_project, $invoice);

        if (!$invoice->isPaid()) {
            return back()->with('error', 'Receipt is only available after the invoice is marked paid.');
        }

        $disk = Storage::disk('local');
        if (filled($invoice->receipt_pdf_path) && $disk->exists($invoice->receipt_pdf_path)) {
            return response($disk->get($invoice->receipt_pdf_path), 200, [
                'Content-Type' => 'application/pdf',
                'Content-Disposition' => 'inline; filename="'.$this->receiptPdfFilename($client_project, $invoice).'"',
            ]);
        }

        $pdf = $this->renderReceiptPdf($client_project, $invoice);

        return $pdf->stream($this->receiptPdfFilename($client_project, $invoice));
    }

    public function send(ClientProject $client_project, ClientProjectInvoice $invoice, ClientProjectStatusService $statusService)
    {
        $this->authorizeInvoiceForProject($client_project, $invoice);

        if ($invoice->isPaid()) {
            return back()->with('error', 'This invoice has already been paid.');
        }

        // Require at least one complete line item (both description AND
        // amount > 0). hasContent() alone is too lenient — it allows rows
        // with just a description or just an amount.
        $completeItems = array_filter(
            $invoice->cleanItems(),
            fn ($row) => trim((string) ($row['description'] ?? '')) !== '' && (float) ($row['amount'] ?? 0) > 0
        );
        if (count($completeItems) === 0) {
            return redirect()
                ->route('admin.client-projects.invoices.edit', [$client_project, $invoice])
                ->with('error', 'Please add at least one line item with a description and an amount greater than $0 before sending the invoice.');
        }

        if (empty($invoice->due_on)) {
            return redirect()
                ->route('admin.client-projects.invoices.edit', [$client_project, $invoice])
                ->with('error', 'Please set a due date before sending the invoice.');
        }

        if (!$client_project->hasClientContactInfo()) {
            $missing = implode(', ', $client_project->missingClientContactFields());

            return back()->with('error', "Cannot send the invoice — missing client info: {$missing}.");
        }

        if (!$invoice->isManual()) {
            $lockedSubtotal = $this->lockedSubtotal($client_project, $invoice);
            $actualSubtotal = round(array_sum(array_column($invoice->cleanItems(), 'amount')), 2);
            if (abs($lockedSubtotal - $actualSubtotal) > 0.01) {
                return back()->with('error', sprintf(
                    'Line-item subtotal (%s) must match the locked installment amount (%s) before sending.',
                    ClientProject::formatMoney($actualSubtotal),
                    ClientProject::formatMoney($lockedSubtotal)
                ));
            }
        }

        // Bump version on re-send so the PDF history is preserved on disk.
        if ($invoice->wasEverSent()) {
            $invoice->version = (int) $invoice->version + 1;
        }

        $pdf = $this->renderInvoicePdf($client_project, $invoice);
        $bytes = $pdf->output();

        $relativePath = $this->invoicePdfStoragePath($client_project, $invoice);
        Storage::disk('local')->put($relativePath, $bytes);

        $invoice->invoice_pdf_path = $relativePath;
        $invoice->sent_at = now();
        $invoice->status = $invoice->isPaid()
            ? ClientProjectInvoice::STATUS_PAID
            : ClientProjectInvoice::STATUS_SENT;

        $invoice->save();

        $filename = $this->invoicePdfFilename($client_project, $invoice);

        try {
            $mailer = Mail::to($client_project->client_email);
            if ($cc = $client_project->secondaryClientEmail()) {
                $mailer->cc($cc);
            }
            $mailer->send(new ClientProjectInvoiceClient($client_project, $invoice, $bytes, $filename));
        } catch (\Throwable $e) {
            Log::error('Failed to email invoice', [
                'project_id' => $client_project->id,
                'invoice_id' => $invoice->id,
                'exception' => $e->getMessage(),
            ]);

            return redirect()
                ->route('admin.client-projects.show', $client_project)
                ->withFragment('invoice')
                ->with('error', 'The invoice PDF was generated but the email could not be sent: '.$e->getMessage());
        }

        $note = "Invoice {$invoice->invoice_number} ({$invoice->installmentTitle()}) "
            ."emailed to {$client_project->clientEmailRecipientsLabel()} (v{$invoice->version}).";

        try {
            $statusService->recordEvent($client_project, $note);
        } catch (\Throwable $e) {
            Log::warning('Failed to record invoice-send history entry', [
                'project_id' => $client_project->id,
                'invoice_id' => $invoice->id,
                'exception' => $e->getMessage(),
            ]);
        }

        return redirect()
            ->route('admin.client-projects.show', $client_project)
            ->withFragment('invoice')
            ->with('success', "Invoice {$invoice->invoice_number} emailed to {$client_project->clientEmailRecipientsLabel()} (v{$invoice->version}).");
    }

    /**
     * Send a simple reminder email. Does NOT regenerate the PDF — the
     * same file that was originally emailed is re-attached so the client
     * can't accuse us of changing the amount between sends.
     */
    public function sendReminder(ClientProject $client_project, ClientProjectInvoice $invoice, ClientProjectStatusService $statusService)
    {
        $this->authorizeInvoiceForProject($client_project, $invoice);

        if (!$invoice->isSent()) {
            return back()->with('error', 'Reminders can only be sent after the invoice has been emailed at least once.');
        }

        if (!$client_project->hasClientContactInfo()) {
            $missing = implode(', ', $client_project->missingClientContactFields());

            return back()->with('error', "Cannot send reminder — missing client info: {$missing}.");
        }

        $disk = Storage::disk('local');
        $bytes = null;
        if (filled($invoice->invoice_pdf_path) && $disk->exists($invoice->invoice_pdf_path)) {
            $bytes = $disk->get($invoice->invoice_pdf_path);
        } else {
            // Fallback: regenerate a fresh copy so the reminder isn't
            // blocked just because the stored file has been pruned.
            $bytes = $this->renderInvoicePdf($client_project, $invoice)->output();
        }

        try {
            $mailer = Mail::to($client_project->client_email);
            if ($cc = $client_project->secondaryClientEmail()) {
                $mailer->cc($cc);
            }
            $mailer->send(new ClientProjectInvoiceReminderClient(
                $client_project,
                $invoice,
                $bytes,
                $this->invoicePdfFilename($client_project, $invoice)
            ));
        } catch (\Throwable $e) {
            Log::error('Failed to email invoice reminder', [
                'project_id' => $client_project->id,
                'invoice_id' => $invoice->id,
                'exception' => $e->getMessage(),
            ]);

            return redirect()
                ->route('admin.client-projects.show', $client_project)
                ->withFragment('invoice')
                ->with('error', 'The reminder email could not be sent: '.$e->getMessage());
        }

        try {
            $statusService->recordEvent(
                $client_project,
                "Payment reminder for invoice {$invoice->invoice_number} emailed to {$client_project->clientEmailRecipientsLabel()}."
            );
        } catch (\Throwable $e) {
            Log::warning('Failed to record invoice-reminder history entry', [
                'project_id' => $client_project->id,
                'invoice_id' => $invoice->id,
                'exception' => $e->getMessage(),
            ]);
        }

        return redirect()
            ->route('admin.client-projects.show', $client_project)
            ->withFragment('invoice')
            ->with('success', "Payment reminder emailed to {$client_project->clientEmailRecipientsLabel()}.");
    }

    /**
     * Mark the invoice as paid, generate a Receipt PDF, email it, and
     * auto-mark the linked installment paid on the Financials tab.
     */
    public function markPaid(Request $request, ClientProject $client_project, ClientProjectInvoice $invoice, ClientProjectStatusService $statusService)
    {
        $this->authorizeInvoiceForProject($client_project, $invoice);

        if ($invoice->isPaid()) {
            return back()->with('error', 'This invoice is already marked paid.');
        }

        if (!$invoice->isSent()) {
            return back()->with('error', 'Only sent invoices can be marked paid. Send the invoice to the client first.');
        }

        $data = $request->validate([
            'paid_on' => ['required', 'date'],
            'paid_amount' => ['required', 'numeric', 'min:0'],
            'also_email_receipt' => ['nullable', 'boolean'],
        ]);

        // Track whether this mark-paid flipped the first installment from
        // unpaid → paid, so we can trigger the admin calendar email afterwards.
        $firstInstallmentJustPaid = false;

        DB::transaction(function () use ($invoice, $client_project, $data, &$firstInstallmentJustPaid) {
            $invoice->paid_at = $data['paid_on'];
            $invoice->paid_amount = round((float) $data['paid_amount'], 2);
            $invoice->status = ClientProjectInvoice::STATUS_PAID;

            // Generate + persist the Receipt PDF BEFORE saving status so
            // the receipt_pdf_path is committed atomically with the paid state.
            $pdf = $this->renderReceiptPdf($client_project, $invoice);
            $bytes = $pdf->output();
            $relativePath = $this->receiptPdfStoragePath($client_project, $invoice);
            Storage::disk('local')->put($relativePath, $bytes);
            $invoice->receipt_pdf_path = $relativePath;

            $invoice->save();

            // Auto-sync: mark the linked installment as paid on the
            // Financials payment plan. Only if we're linked to one and
            // it isn't already paid — so the user can manually toggle
            // it first without collision.
            if (!$invoice->isManual()) {
                $installment = $client_project->installments()
                    ->orderBy('sort_order')
                    ->get()
                    ->values()
                    ->get($invoice->installment_number - 1);

                if ($installment && !$installment->is_paid) {
                    $installment->is_paid = true;
                    $installment->paid_on = $data['paid_on'];
                    $installment->save();

                    if ((int) $invoice->installment_number === 1) {
                        $firstInstallmentJustPaid = true;
                    }
                }
            }
        });

        // Re-read the persisted bytes so the email gets the exact saved PDF.
        $disk = Storage::disk('local');
        $receiptBytes = $disk->exists($invoice->receipt_pdf_path)
            ? $disk->get($invoice->receipt_pdf_path)
            : $this->renderReceiptPdf($client_project, $invoice)->output();

        $sendEmail = (bool) ($data['also_email_receipt'] ?? true);
        $emailSent = false;

        if ($sendEmail && $client_project->hasClientContactInfo()) {
            try {
                $mailer = Mail::to($client_project->client_email);
                if ($cc = $client_project->secondaryClientEmail()) {
                    $mailer->cc($cc);
                }
                $mailer->send(new ClientProjectInvoiceReceiptClient(
                    $client_project,
                    $invoice,
                    $receiptBytes,
                    $this->receiptPdfFilename($client_project, $invoice)
                ));
                $emailSent = true;
            } catch (\Throwable $e) {
                Log::error('Failed to email receipt', [
                    'project_id' => $client_project->id,
                    'invoice_id' => $invoice->id,
                    'exception' => $e->getMessage(),
                ]);
            }
        }

        $note = $emailSent
            ? "Invoice {$invoice->invoice_number} marked paid ".
                ClientProject::formatMoney($invoice->paid_amount).
                ". Receipt emailed to {$client_project->clientEmailRecipientsLabel()}."
            : "Invoice {$invoice->invoice_number} marked paid ".
                ClientProject::formatMoney($invoice->paid_amount).
                '.';

        try {
            $statusService->recordEvent($client_project, $note);
        } catch (\Throwable $e) {
            Log::warning('Failed to record mark-paid history entry', [
                'project_id' => $client_project->id,
                'invoice_id' => $invoice->id,
                'exception' => $e->getMessage(),
            ]);
        }

        $flash = $emailSent
            ? "Invoice marked paid. Receipt emailed to {$client_project->clientEmailRecipientsLabel()}."
            : 'Invoice marked paid. (Email not sent.)';

        // If paying this invoice just flipped the 1st installment to paid,
        // trigger the admin calendar email — or warn that dates are still
        // pending, mirroring the Financials togglePaid flow.
        $warning = null;
        if ($firstInstallmentJustPaid) {
            if (!$client_project->fresh()->hasSchedule()) {
                $warning = 'Please set the project start and end dates — the calendar notification will be emailed once both are saved.';
            } else {
                $result = $statusService->maybeSendCalendarDatesEmail($client_project->fresh());
                if ($result === 'sent') {
                    $flash .= ' Calendar notification emailed to the admin.';
                } elseif ($result === 'failed') {
                    $warning = 'The calendar notification email failed to send. Check logs and try again.';
                }
            }
        }

        $redirect = redirect()
            ->route('admin.client-projects.show', $client_project)
            ->withFragment('invoice')
            ->with('success', $flash);

        if ($warning) {
            $redirect->with('warning', $warning);
        }

        return $redirect;
    }

    public function destroy(ClientProject $client_project, ClientProjectInvoice $invoice)
    {
        $this->authorizeInvoiceForProject($client_project, $invoice);

        if ($invoice->isPaid()) {
            return back()->with('error', 'Paid invoices cannot be deleted.');
        }

        $invoice->delete();

        return redirect()
            ->route('admin.client-projects.show', $client_project)
            ->withFragment('invoice')
            ->with('success', 'Invoice deleted.');
    }

    // --- Helpers ----------------------------------------------------------

    protected function authorizeInvoiceForProject(ClientProject $project, ClientProjectInvoice $invoice): void
    {
        abort_unless($invoice->client_project_id === $project->id, 404);
    }

    /**
     * Snapshot the canonical installment description into the invoice at
     * creation time. We deliberately don't keep it perfectly in sync with
     * the Financials tab afterwards, because the number has already been
     * emailed to the client — changing wording on a paid/sent invoice
     * would be misleading.
     */
    protected function createInvoice(ClientProject $project, ?int $installmentNumber): ClientProjectInvoice
    {
        $plan = (int) $project->payment_plan ?: 1;
        $description = null;
        $items = [];
        $subtotal = 0.0;

        // Installment-linked invoices get seeded with a single pre-filled
        // line item that mirrors the installment (description + pre-HST
        // amount), so the user usually just hits "Send" — no retyping.
        // Manual invoices stay empty and let the user enter everything.
        if ($installmentNumber !== null) {
            $description = ClientProjectInvoice::installmentDescriptionFor($plan, $installmentNumber);

            $installment = $project->installments()
                ->orderBy('sort_order')
                ->get()
                ->values()
                ->get($installmentNumber - 1);

            if ($installment) {
                $subtotal = round((float) $installment->amount, 2);
                $items = [[
                    'description' => $description ?: 'Installment '.$installmentNumber,
                    'amount' => $subtotal,
                ]];
            }
        }

        $hst = round($subtotal * ClientProjectInvoice::HST_RATE, 2);

        return $project->invoices()->create([
            'installment_number' => $installmentNumber,
            'installment_description' => $description,
            'invoice_number' => ClientProjectInvoice::generateNumber(),
            'items' => $items,
            'subtotal' => $subtotal,
            'hst' => $hst,
            'total' => round($subtotal + $hst, 2),
            'status' => ClientProjectInvoice::STATUS_DRAFT,
            'version' => 1,
        ]);
    }

    /**
     * The pre-HST amount the invoice's line items must sum to. For
     * installment-linked invoices this is the installment's pre-tax
     * amount (already persisted on the installment row as `amount`).
     * For manual invoices there is no lock — return whatever the user
     * passed (or the invoice's own subtotal as a fallback).
     */
    protected function lockedSubtotal(ClientProject $project, ClientProjectInvoice $invoice, $manualOverride = null): float
    {
        if ($invoice->isManual()) {
            if ($manualOverride !== null && is_numeric($manualOverride)) {
                return round((float) $manualOverride, 2);
            }

            return round((float) $invoice->subtotal, 2);
        }

        $installment = $project->installments()
            ->orderBy('sort_order')
            ->get()
            ->values()
            ->get($invoice->installment_number - 1);

        return round((float) ($installment->amount ?? 0), 2);
    }

    protected function lockedTotalWithHst(ClientProject $project, ClientProjectInvoice $invoice): float
    {
        $sub = $this->lockedSubtotal($project, $invoice);

        return round($sub * (1 + ClientProjectInvoice::HST_RATE), 2);
    }

    protected function normaliseItems(array $rows): array
    {
        $clean = [];
        foreach ($rows as $row) {
            $description = trim((string) ($row['description'] ?? ''));
            $amount = (float) ($row['amount'] ?? 0);
            if ($description === '' && $amount == 0.0) {
                continue;
            }
            $clean[] = [
                'description' => $description,
                'amount' => round($amount, 2),
            ];
        }

        return $clean;
    }

    // --- PDF rendering ---------------------------------------------------

    protected function renderInvoicePdf(ClientProject $project, ClientProjectInvoice $invoice)
    {
        $pdf = Pdf::loadView('admin.client_projects.invoice.pdf', [
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

    protected function renderReceiptPdf(ClientProject $project, ClientProjectInvoice $invoice)
    {
        $pdf = Pdf::loadView('admin.client_projects.invoice.receipt', [
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

    // --- Path / filename helpers -----------------------------------------

    protected function invoicePdfStoragePath(ClientProject $project, ClientProjectInvoice $invoice): string
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

    protected function receiptPdfStoragePath(ClientProject $project, ClientProjectInvoice $invoice): string
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

    protected function invoicePdfFilename(ClientProject $project, ClientProjectInvoice $invoice): string
    {
        $slug = Str::slug($project->name) ?: 'project-'.$project->id;

        return "invoice-{$invoice->invoice_number}-{$slug}.pdf";
    }

    protected function receiptPdfFilename(ClientProject $project, ClientProjectInvoice $invoice): string
    {
        $slug = Str::slug($project->name) ?: 'project-'.$project->id;

        return "receipt-{$invoice->invoice_number}-{$slug}.pdf";
    }
}

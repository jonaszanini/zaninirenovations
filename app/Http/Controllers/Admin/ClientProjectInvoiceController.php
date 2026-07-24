<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Mail\ClientProjectInvoiceClient;
use App\Mail\ClientProjectInvoiceReceiptClient;
use App\Models\ClientCommunicationLog;
use App\Models\ClientProject;
use App\Models\ClientProjectInvoice;
use App\Services\ClientCommunicationLogger;
use App\Services\ClientProjectInvoiceReminderService;
use App\Services\ClientProjectStatusService;
use App\Services\ClientSmsService;
use App\Services\Invoice\InvoiceCreationService;
use App\Services\Invoice\InvoicePdfService;
use App\Services\Invoice\InvoiceTotalsCalculator;
use App\Services\MoceanSmsService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
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
 *                        it, transitions status = paid, aligns the linked
 *                        installment’s pre-tax amount to the invoice subtotal,
 *                        and marks that installment paid when it was not already.
 *   - destroy()          deletes the invoice (and its PDFs) while it is still a draft.
 *
 * Totals are always computed server-side from the raw items + HST rate,
 * so a tampered client-side grand total can't slip past validation.
 */
class ClientProjectInvoiceController extends Controller
{
    public function __construct(
        protected InvoicePdfService $pdfService,
        protected InvoiceTotalsCalculator $totals,
        protected InvoiceCreationService $creationService,
    ) {
    }

    /**
     * Create a new invoice and redirect to its editor. Three modes:
     *   - installment: linked to the N-th installment in the payment plan
     *                  (fixed-price projects only, 1:1 per installment)
     *   - manual:      blank invoice, admin fills everything in
     *   - hourly:      snapshots every unbilled time entry on the project
     *                  as prefilled line items (hourly-billed projects only)
     *
     * Reopens an existing draft when appropriate instead of duplicating.
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
            'mode' => ['nullable', 'in:installment,manual,hourly'],
        ]);

        $mode = $data['mode'] ?? ($request->filled('installment_number') ? 'installment' : 'manual');

        // Guard: mode vs billing type must line up. This keeps the
        // fixed-price (installment / manual) and hourly flows strictly
        // separated so a form bug can't cross-contaminate them.
        if ($mode === 'hourly' && !$client_project->isHourly()) {
            return back()->with('error', '"Create invoice from hours" is only available for hourly-billed projects.');
        }
        if ($mode === 'installment' && $client_project->isHourly()) {
            return back()->with('error', 'Hourly projects do not use the installment invoice flow.');
        }

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

        if ($mode === 'hourly') {
            $unbilled = $client_project->timeEntries()
                ->unbilled()
                ->closed()
                ->orderBy('occurred_on')
                ->get();

            if ($unbilled->isEmpty()) {
                return back()->with('error', 'There are no unbilled time entries to invoice. Log some hours on the Time Tracking tab first.');
            }

            $invoice = $this->creationService->createHourlyInvoice($client_project, $unbilled);

            return redirect()
                ->route('admin.client-projects.invoices.edit', [$client_project, $invoice])
                ->with('success', 'Invoice draft created from '.$unbilled->count().' unbilled time entr'.($unbilled->count() === 1 ? 'y' : 'ies').'. Review and adjust the line items as needed.');
        }

        $invoice = $this->creationService->createInvoice($client_project, $installmentNumber);

        return redirect()
            ->route('admin.client-projects.invoices.edit', [$client_project, $invoice]);
    }

    public function edit(ClientProject $client_project, ClientProjectInvoice $invoice)
    {
        $this->authorizeInvoiceForProject($client_project, $invoice);

        return view('admin.client_projects.invoice.edit', [
            'project' => $client_project,
            'invoice' => $invoice,
            'lockedTotalWithHst' => $this->totals->lockedTotalWithHst($client_project, $invoice),
            'lockedSubtotal' => $this->totals->lockedSubtotal($client_project, $invoice),
            'lockedHst' => $this->totals->lockedHst($client_project, $invoice),
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
            'items.*.line_kind' => ['nullable', Rule::in(['labour', 'materials', 'labour_credit', 'gas'])],
            'items.*.expense_id' => ['nullable', 'integer'],
            'manual_description' => ['nullable', 'string', 'max:255'],
            'manual_total' => ['nullable', 'numeric', 'min:0'],
            'due_on' => ['nullable', 'date'],
            'intent' => ['nullable', 'in:draft,close'],
            'hst_exempt' => ['nullable', 'boolean'],
            'discount_percent' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'discount_amount' => ['nullable', 'string', 'max:40'],
            'discount_comment' => ['nullable', 'string', 'max:120'],
        ]);

        // Persist the HST-exempt flag BEFORE we recompute totals so the
        // helpers that derive HST below see the new value.
        $invoice->hst_exempt = (bool) ($data['hst_exempt'] ?? false);

        $isHourlyManual = $client_project->isHourly() && $invoice->isManual();

        $items = $isHourlyManual
            ? $this->totals->normaliseHourlyInvoiceItems($client_project, $data['items'] ?? [])
            : $this->totals->normaliseItems($data['items'] ?? []);

        // For manual invoices, the user sets the pre-HST total directly
        // via the manual_total field; we only persist it when present.
        // Excluded for hourly projects — totals come from Labour vs Materials rows.
        if ($invoice->isManual() && isset($data['manual_description'])) {
            $invoice->installment_description = trim((string) $data['manual_description']) ?: 'Manual invoice';
        }

        if ($isHourlyManual) {
            [$labour, $materials] = $this->totals->partitionHourlyLineAmounts($items);
            $invoice->items = $items;
            $invoice->discount_percent = null;
            $invoice->discount_amount = null;
            $invoice->discount_comment = null;
            // subtotal column = taxable labour base (pre-HST) only — matches PDF wording.
            $invoice->subtotal = $labour;
            $invoice->hst = $invoice->isHstExempt()
                ? 0.0
                : round($labour * ClientProjectInvoice::HST_RATE, 2);
            $invoice->total = round($labour + $invoice->hst + $materials, 2);
            if ($invoice->total < 0.01) {
                return back()
                    ->withErrors([
                        'items' => 'Invoice total must be at least CA$0.01. Reduce the credit or add more billable lines.',
                    ])
                    ->withInput();
            }
            $this->totals->syncHourlyExpenseLinks($client_project, $invoice, $items);
        } else {
            $invoice->items = $items;
            $gross = round((float) array_sum(array_column($items, 'amount')), 2);

            if (!$invoice->isManual()) {
                [$pctStored, $amtStored, $discountVal] = $this->totals->resolveInstallmentDiscountFromRequest($request, $gross);
                $invoice->discount_percent = $pctStored;
                $invoice->discount_amount = $amtStored;
                $comment = trim((string) ($data['discount_comment'] ?? ''));
                $invoice->discount_comment = $comment !== '' ? Str::limit($comment, 120, '') : null;
                $net = round(max(0.0, $gross - $discountVal), 2);
                $invoice->subtotal = $net;
                $invoice->hst = $this->totals->computeInvoiceHst($client_project, $invoice, $net);
                $invoice->total = round($invoice->subtotal + $invoice->hst, 2);
            } else {
                $invoice->discount_percent = null;
                $invoice->discount_amount = null;
                $invoice->discount_comment = null;
                $invoice->subtotal = $gross;
                $invoice->hst = $this->totals->computeInvoiceHst($client_project, $invoice, $invoice->subtotal);
                $invoice->total = round($invoice->subtotal + $invoice->hst, 2);

                if ($data['manual_total'] !== null && ! $client_project->isHourly()) {
                    // Manual fixed-price invoices: if the items don't add up to the user-entered
                    // manual_total, we still persist a draft so work isn't lost — the
                    // UI surfaces a validation warning. For the "send" action this
                    // mismatch is a hard stop (see send()).
                    $invoice->subtotal = (float) $data['manual_total'];
                    $invoice->hst = $this->totals->computeInvoiceHst($client_project, $invoice, $invoice->subtotal);
                    $invoice->total = round($invoice->subtotal + $invoice->hst, 2);

                    if (abs((float) array_sum(array_column($items, 'amount')) - (float) $data['manual_total']) > 0.01 && count($items)) {
                        // Fall back to items-sum as the authoritative subtotal if
                        // the user actually typed line items (prefer item sum).
                        $invoice->subtotal = (float) array_sum(array_column($items, 'amount'));
                        $invoice->hst = $this->totals->computeInvoiceHst($client_project, $invoice, $invoice->subtotal);
                        $invoice->total = round($invoice->subtotal + $invoice->hst, 2);
                    }
                }
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

        $pdf = $this->pdfService->renderInvoicePdf($client_project, $invoice);

        return $pdf->stream($this->pdfService->invoicePdfFilename($client_project, $invoice));
    }

    /**
     * Public, signed URL (no login) so the client can open the invoice PDF from a phone SMS.
     * Authenticity is enforced via signed middleware + matching project/invoice ids.
     */
    public function guestDownloadInvoicePdf(ClientProject $client_project, ClientProjectInvoice $invoice)
    {
        abort_unless((int) $invoice->client_project_id === (int) $client_project->id, 404);

        if (!$invoice->hasContent() && (float) $invoice->total <= 0) {
            abort(404, 'Invoice not available.');
        }

        $pdf = $this->pdfService->renderInvoicePdf($client_project, $invoice);

        return $pdf->stream($this->pdfService->invoicePdfFilename($client_project, $invoice));
    }

    /**
     * Public, signed URL (no login) so the client can open the receipt PDF from SMS.
     */
    public function guestDownloadReceiptPdf(ClientProject $client_project, ClientProjectInvoice $invoice)
    {
        abort_unless((int) $invoice->client_project_id === (int) $client_project->id, 404);

        if (! $invoice->isPaid()) {
            abort(404, 'Receipt not available.');
        }

        $disk = Storage::disk('local');
        if (filled($invoice->receipt_pdf_path) && $disk->exists($invoice->receipt_pdf_path)) {
            return response($disk->get($invoice->receipt_pdf_path), 200, [
                'Content-Type' => 'application/pdf',
                'Content-Disposition' => 'inline; filename="'.$this->pdfService->receiptPdfFilename($client_project, $invoice).'"',
            ]);
        }

        $pdf = $this->pdfService->renderReceiptPdf($client_project, $invoice);

        return $pdf->stream($this->pdfService->receiptPdfFilename($client_project, $invoice));
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
                'Content-Disposition' => 'inline; filename="'.$this->pdfService->receiptPdfFilename($client_project, $invoice).'"',
            ]);
        }

        $pdf = $this->pdfService->renderReceiptPdf($client_project, $invoice);

        return $pdf->stream($this->pdfService->receiptPdfFilename($client_project, $invoice));
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

        if (blank(trim((string) $client_project->client_email))) {
            return back()->with('error', "Cannot email the invoice — add the client's email address first.");
        }

        // Bump version on re-send so the PDF history is preserved on disk.
        if ($invoice->wasEverSent()) {
            $invoice->version = (int) $invoice->version + 1;
        }

        $pdf = $this->pdfService->renderInvoicePdf($client_project, $invoice);
        $bytes = $pdf->output();

        $relativePath = $this->pdfService->invoicePdfStoragePath($client_project, $invoice);
        Storage::disk('local')->put($relativePath, $bytes);

        $invoice->invoice_pdf_path = $relativePath;
        $invoice->sent_at = now();
        $invoice->status = $invoice->isPaid()
            ? ClientProjectInvoice::STATUS_PAID
            : ClientProjectInvoice::STATUS_SENT;

        $invoice->save();

        $filename = $this->pdfService->invoicePdfFilename($client_project, $invoice);

        $cc = $client_project->secondaryClientEmail();
        $invoiceSubject = "Invoice {$invoice->invoice_number} from Zanini Renovations";
        $invoiceDescription = "Invoice {$invoice->invoice_number} ({$invoice->installmentTitle()}) v{$invoice->version} — total "
            .ClientProject::formatMoney((float) $invoice->total)
            .($invoice->due_on ? ', due '.\Carbon\Carbon::parse($invoice->due_on)->format('M j, Y') : '');

        try {
            $mailer = Mail::to($client_project->client_email);
            if ($cc) {
                $mailer->cc($cc);
            }
            $mailer->send(new ClientProjectInvoiceClient($client_project, $invoice, $bytes, $filename));

            app(ClientCommunicationLogger::class)->logEmail(
                $client_project,
                ClientCommunicationLog::REASON_INVOICE_SENT,
                $client_project->client_email,
                $cc,
                $invoiceSubject,
                $invoiceDescription,
            );
        } catch (\Throwable $e) {
            Log::error('Failed to email invoice', [
                'project_id' => $client_project->id,
                'invoice_id' => $invoice->id,
                'exception' => $e->getMessage(),
            ]);

            app(ClientCommunicationLogger::class)->logEmail(
                $client_project,
                ClientCommunicationLog::REASON_INVOICE_SENT,
                $client_project->client_email,
                $cc,
                $invoiceSubject,
                $invoiceDescription,
                ClientCommunicationLog::DIRECTION_TO_CLIENT,
                false,
                $e->getMessage(),
            );

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
     * SMS the client a Mocean message with a signed link to open/view the invoice PDF (no attachment).
     * Does not email, mark sent, or bump invoice version — purely a link delivery channel.
     */
    public function sendInvoiceSms(
        ClientProject $client_project,
        ClientProjectInvoice $invoice,
        ClientSmsService $clientSms,
        MoceanSmsService $moceanSms,
    ) {
        $this->authorizeInvoiceForProject($client_project, $invoice);

        if ($invoice->isPaid()) {
            return back()->with('error', 'This invoice has already been paid.');
        }

        $blocked = $this->invoiceSmsBlockers($client_project, $invoice, $moceanSms);
        if ($blocked !== []) {
            return back()->with('error', implode(' ', $blocked));
        }

        $smsTo = ClientProject::phoneDigitsForSms($client_project->client_phone);
        if ($smsTo === null) {
            return back()->with('error', 'Cannot send SMS — client phone number is missing or not valid for texting.');
        }

        $signedUrl = URL::temporarySignedRoute(
            'public.client-project.invoice-pdf',
            now()->addYear(),
            ['client_project' => $client_project, 'invoice' => $invoice],
        );

        $totalFmt = ClientProject::formatMoney((float) $invoice->total);
        $smsBody = "Zanini Renovations — Invoice {$invoice->invoice_number}. Total: {$totalFmt}. For details & PDF: {$signedUrl}";

        $summary = "Invoice {$invoice->invoice_number} SMS link — ".$totalFmt.' — "'.Str::limit($smsBody, 120).'"';
        $result = $clientSms->sendToClient(
            $client_project,
            $smsTo,
            $smsBody,
            ClientCommunicationLog::REASON_INVOICE_SMS_LINK,
            $summary,
        );

        if (! $result['ok']) {
            return back()->with('error', 'SMS could not be sent: '.$result['detail']);
        }

        return redirect()
            ->route('admin.client-projects.show', $client_project)
            ->withFragment('invoice')
            ->with('success', 'SMS sent to the client with an invoice link.');
    }

    /**
     * Send a simple reminder email. Does NOT regenerate the PDF — the
     * same file that was originally emailed is re-attached so the client
     * can't accuse us of changing the amount between sends.
     */
    public function sendReminder(
        ClientProject $client_project,
        ClientProjectInvoice $invoice,
        ClientProjectInvoiceReminderService $reminderService,
    ) {
        $this->authorizeInvoiceForProject($client_project, $invoice);

        if (! $invoice->isSent()) {
            return back()->with('error', 'Reminders can only be sent after the invoice has been emailed at least once.');
        }

        $result = $reminderService->sendPaymentReminder($client_project, $invoice);

        if (! $result['ok']) {
            return redirect()
                ->route('admin.client-projects.show', $client_project)
                ->withFragment('invoice')
                ->with('error', 'The payment reminder could not be sent: '.$result['detail']);
        }

        $success = $result['channel'] === 'sms'
            ? "Payment reminder sent via SMS to {$result['detail']}."
            : "Payment reminder emailed to {$result['detail']}.";

        return redirect()
            ->route('admin.client-projects.show', $client_project)
            ->withFragment('invoice')
            ->with('success', $success);
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

        // Drafts can be marked paid too (e.g. client paid in person/cash and
        // we skipped the email/SMS step). We only require the invoice to
        // have something to put on the receipt — at least one line item
        // and a non-zero total.
        if (!$invoice->hasContent() || (float) $invoice->total <= 0) {
            return back()->with('error', 'Add at least one line item with a description and amount before marking the invoice paid.');
        }

        $allowedStatusesOnFirstPaid = array_values(array_filter(
            ClientProject::statuses(),
            fn (string $s) => ! in_array($s, ClientProject::terminalStatuses(), true)
        ));

        $isFirstPaidInvoice = ! $client_project->invoices()
            ->where('status', ClientProjectInvoice::STATUS_PAID)
            ->exists();

        $data = $request->validate([
            'paid_on' => ['required', 'date'],
            'paid_amount' => ['required', 'numeric', 'min:0'],
            'also_email_receipt' => ['nullable', 'boolean'],
            'also_sms_receipt' => ['nullable', 'boolean'],
            'payment_method' => ['required', Rule::in([
                ClientProjectInvoice::PAYMENT_METHOD_CASH,
                ClientProjectInvoice::PAYMENT_METHOD_ETRANSFER,
            ])],
            'project_status' => [
                'nullable',
                'string',
                Rule::in($allowedStatusesOnFirstPaid),
            ],
        ]);

        // Track whether this mark-paid flipped the first installment from
        // unpaid → paid, so we can trigger the admin calendar email afterwards.
        $firstInstallmentJustPaid = false;

        DB::transaction(function () use ($invoice, $client_project, $data, &$firstInstallmentJustPaid) {
            $invoice->paid_at = $data['paid_on'];
            $invoice->paid_amount = round((float) $data['paid_amount'], 2);
            $invoice->payment_method = $data['payment_method'];
            $invoice->status = ClientProjectInvoice::STATUS_PAID;

            // Generate + persist the Receipt PDF BEFORE saving status so
            // the receipt_pdf_path is committed atomically with the paid state.
            $pdf = $this->pdfService->renderReceiptPdf($client_project, $invoice);
            $bytes = $pdf->output();
            $relativePath = $this->pdfService->receiptPdfStoragePath($client_project, $invoice);
            Storage::disk('local')->put($relativePath, $bytes);
            $invoice->receipt_pdf_path = $relativePath;

            $invoice->save();

            // Auto-sync: align the linked installment’s pre-tax amount to what
            // was actually billed on this invoice (subtotal after discount), and
            // mark it paid when it wasn’t already (manual toggle may have paid it first).
            if (!$invoice->isManual()) {
                $installment = $client_project->installments()
                    ->orderBy('sort_order')
                    ->get()
                    ->values()
                    ->get($invoice->installment_number - 1);

                if ($installment) {
                    $wasPaid = (bool) $installment->is_paid;

                    if (!$client_project->isHourly()) {
                        $installment->amount = round(max(0.0, (float) $invoice->subtotal), 2);
                    }

                    if (!$wasPaid) {
                        $installment->is_paid = true;
                        $installment->paid_on = $data['paid_on'];
                    }

                    $installment->save();

                    if (!$wasPaid && (int) $invoice->installment_number === 1) {
                        $firstInstallmentJustPaid = true;
                    }
                }
            }
        });

        // Re-read the persisted bytes so the email gets the exact saved PDF.
        $disk = Storage::disk('local');
        $receiptBytes = $disk->exists($invoice->receipt_pdf_path)
            ? $disk->get($invoice->receipt_pdf_path)
            : $this->pdfService->renderReceiptPdf($client_project, $invoice)->output();

        $sendEmail = $request->boolean('also_email_receipt');
        $sendSms = $request->boolean('also_sms_receipt');
        $emailSent = false;
        $smsSent = false;

        if ($sendEmail && filled(trim((string) $client_project->client_email))) {
            $cc = $client_project->secondaryClientEmail();
            $receiptSubject = "Receipt — invoice {$invoice->invoice_number} paid";
            $receiptDescription = "Receipt for invoice {$invoice->invoice_number} — paid "
                .ClientProject::formatMoney((float) $invoice->paid_amount)
                .' on '.\Carbon\Carbon::parse($invoice->paid_at)->format('M j, Y');

            try {
                $mailer = Mail::to($client_project->client_email);
                if ($cc) {
                    $mailer->cc($cc);
                }
                $mailer->send(new ClientProjectInvoiceReceiptClient(
                    $client_project,
                    $invoice,
                    $receiptBytes,
                    $this->pdfService->receiptPdfFilename($client_project, $invoice)
                ));
                $emailSent = true;

                app(ClientCommunicationLogger::class)->logEmail(
                    $client_project,
                    ClientCommunicationLog::REASON_RECEIPT_SENT,
                    $client_project->client_email,
                    $cc,
                    $receiptSubject,
                    $receiptDescription,
                );
            } catch (\Throwable $e) {
                Log::error('Failed to email receipt', [
                    'project_id' => $client_project->id,
                    'invoice_id' => $invoice->id,
                    'exception' => $e->getMessage(),
                ]);

                app(ClientCommunicationLogger::class)->logEmail(
                    $client_project,
                    ClientCommunicationLog::REASON_RECEIPT_SENT,
                    $client_project->client_email,
                    $cc,
                    $receiptSubject,
                    $receiptDescription,
                    ClientCommunicationLog::DIRECTION_TO_CLIENT,
                    false,
                    $e->getMessage(),
                );
            }
        }

        if ($sendSms) {
            $smsSent = $this->sendReceiptSms($client_project, $invoice);
        }

        $deliveryParts = [];
        if ($emailSent) {
            $deliveryParts[] = 'receipt emailed to '.$client_project->clientEmailRecipientsLabel();
        }
        if ($smsSent) {
            $deliveryParts[] = 'receipt SMS sent to client';
        }
        $deliveryNote = $deliveryParts !== []
            ? '. '.ucfirst(implode('; ', $deliveryParts)).'.'
            : '.';

        $note = "Invoice {$invoice->invoice_number} marked paid "
            .ClientProject::formatMoney($invoice->paid_amount)
            .$deliveryNote;

        try {
            $statusService->recordEvent($client_project, $note);
        } catch (\Throwable $e) {
            Log::warning('Failed to record mark-paid history entry', [
                'project_id' => $client_project->id,
                'invoice_id' => $invoice->id,
                'exception' => $e->getMessage(),
            ]);
        }

        if ($emailSent && $smsSent) {
            $flash = 'Invoice marked paid. Receipt emailed and SMS sent to the client.';
        } elseif ($emailSent) {
            $flash = "Invoice marked paid. Receipt emailed to {$client_project->clientEmailRecipientsLabel()}.";
        } elseif ($smsSent) {
            $flash = 'Invoice marked paid. Receipt SMS sent to the client.';
        } elseif ($sendEmail || $sendSms) {
            $flash = 'Invoice marked paid. Receipt delivery was requested but could not be sent — check contact info and logs.';
        } else {
            $flash = 'Invoice marked paid.';
        }

        $targetStatus = isset($data['project_status']) ? trim((string) $data['project_status']) : '';
        if ($isFirstPaidInvoice && ! $client_project->isClosed() && $targetStatus !== '') {
            $proj = $client_project->fresh();
            if ($proj && $targetStatus !== $proj->status) {
                try {
                    $updated = $statusService->transition(
                        $proj,
                        $targetStatus,
                        'Status set while marking the first paid invoice.'
                    );
                    $flash .= ' Project status updated to '.$updated->statusLabel().'.';
                } catch (\Throwable $e) {
                    Log::warning('markPaid: project_status transition failed', [
                        'project_id' => $client_project->id,
                        'invoice_id' => $invoice->id,
                        'target_status' => $targetStatus,
                        'exception' => $e->getMessage(),
                    ]);
                }
            }
        }

        // If paying this invoice just flipped the 1st installment to paid,
        // trigger the admin calendar email — or warn that dates are still
        // pending, mirroring the Financials togglePaid flow.
        $warning = null;
        if ($firstInstallmentJustPaid) {
            if (!$client_project->fresh()->hasSchedule()) {
                $warning = 'Please set the project start and end dates — the calendar notification will be emailed once both are saved.';
            } else {
                $fresh = $client_project->fresh();
                $result = $statusService->maybeSendCalendarDatesEmail($fresh);
                $fixed = $statusService->maybeSendFixedScheduleConfirmationToClient($fresh->fresh());
                if ($result === 'sent') {
                    $flash .= ' Calendar notification emailed to the admin.';
                } elseif ($result === 'failed') {
                    $warning = 'The calendar notification email failed to send. Check logs and try again.';
                }
                if ($fixed === 'sent') {
                    $flash .= ' Schedule confirmation emailed to the client.';
                } elseif ($fixed === 'failed') {
                    $warn2 = 'The client schedule confirmation email failed to send. Check logs.';
                    $warning = $warning ? $warning.' '.$warn2 : $warn2;
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

    /**
     * SMS the client a signed link to the receipt PDF after mark-as-paid.
     */
    protected function sendReceiptSms(ClientProject $project, ClientProjectInvoice $invoice): bool
    {
        $clientSms = app(ClientSmsService::class);
        if (! $clientSms->isConfigured()) {
            return false;
        }

        $smsTo = ClientProject::phoneDigitsForSms($project->client_phone);
        if ($smsTo === null) {
            return false;
        }

        $signedUrl = URL::temporarySignedRoute(
            'public.client-project.receipt-pdf',
            now()->addYear(),
            ['client_project' => $project, 'invoice' => $invoice],
        );

        $paidFmt = ClientProject::formatMoney((float) $invoice->paid_amount);
        $smsBody = "Zanini Renovations — Receipt for invoice {$invoice->invoice_number}. Paid: {$paidFmt}. View receipt: {$signedUrl}";

        $summary = "Receipt {$invoice->invoice_number} SMS link — {$paidFmt} — \"".Str::limit($smsBody, 120).'"';
        $result = $clientSms->sendToClient(
            $project,
            $smsTo,
            $smsBody,
            ClientCommunicationLog::REASON_RECEIPT_SMS_LINK,
            $summary,
        );

        if (! $result['ok']) {
            Log::error('Failed to SMS receipt', [
                'project_id' => $project->id,
                'invoice_id' => $invoice->id,
                'detail' => $result['detail'],
            ]);
        }

        return $result['ok'];
    }

    /**
     * Blockers for sending the invoice PDF link by SMS (content + Mocean + phone).
     * Does not require client email — SMS can be used when email is missing.
     *
     * @return list<string>
     */
    protected function invoiceSmsBlockers(ClientProject $project, ClientProjectInvoice $invoice, MoceanSmsService $mocean): array
    {
        $out = [];

        $completeItems = array_filter(
            $invoice->cleanItems(),
            fn ($row) => trim((string) ($row['description'] ?? '')) !== '' && (float) ($row['amount'] ?? 0) > 0
        );
        if (count($completeItems) === 0) {
            $out[] = 'Add at least one line item with a description and an amount greater than $0.';
        }

        if (empty($invoice->due_on)) {
            $out[] = 'Set a payment due date on the invoice before sending an SMS link.';
        }

        if (!$mocean->isConfigured()) {
            $out[] = 'SMS is not configured — set MOCEAN_API_TOKEN on the server.';
        }

        if (ClientProject::phoneDigitsForSms($project->client_phone) === null) {
            $out[] = 'Add a mobile phone number the client uses for texting (save on Edit project).';
        }

        return $out;
    }

    protected function authorizeInvoiceForProject(ClientProject $project, ClientProjectInvoice $invoice): void
    {
        abort_unless($invoice->client_project_id === $project->id, 404);
    }

}

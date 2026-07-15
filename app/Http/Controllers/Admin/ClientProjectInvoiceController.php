<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Mail\ClientProjectInvoiceClient;
use App\Mail\ClientProjectInvoiceReceiptClient;
use App\Models\ClientCommunicationLog;
use App\Models\ClientProject;
use App\Models\ClientProjectExpense;
use App\Models\ClientProjectInvoice;
use App\Models\ClientProjectQuotation;
use App\Models\ClientProjectTimeEntry;
use App\Services\ClientCommunicationLogger;
use App\Services\ClientProjectInvoiceReminderService;
use App\Services\ClientProjectStatusService;
use App\Services\ClientSmsService;
use App\Services\MoceanSmsService;
use Barryvdh\DomPDF\Facade\Pdf;
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

            $invoice = $this->createHourlyInvoice($client_project, $unbilled);

            return redirect()
                ->route('admin.client-projects.invoices.edit', [$client_project, $invoice])
                ->with('success', 'Invoice draft created from '.$unbilled->count().' unbilled time entr'.($unbilled->count() === 1 ? 'y' : 'ies').'. Review and adjust the line items as needed.');
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
            'lockedHst' => $this->lockedHst($client_project, $invoice),
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
            ? $this->normaliseHourlyInvoiceItems($client_project, $data['items'] ?? [])
            : $this->normaliseItems($data['items'] ?? []);

        // For manual invoices, the user sets the pre-HST total directly
        // via the manual_total field; we only persist it when present.
        // Excluded for hourly projects — totals come from Labour vs Materials rows.
        if ($invoice->isManual() && isset($data['manual_description'])) {
            $invoice->installment_description = trim((string) $data['manual_description']) ?: 'Manual invoice';
        }

        if ($isHourlyManual) {
            [$labour, $materials] = $this->partitionHourlyLineAmounts($items);
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
            $this->syncHourlyExpenseLinks($client_project, $invoice, $items);
        } else {
            $invoice->items = $items;
            $gross = round((float) array_sum(array_column($items, 'amount')), 2);

            if (!$invoice->isManual()) {
                [$pctStored, $amtStored, $discountVal] = $this->resolveInstallmentDiscountFromRequest($request, $gross);
                $invoice->discount_percent = $pctStored;
                $invoice->discount_amount = $amtStored;
                $comment = trim((string) ($data['discount_comment'] ?? ''));
                $invoice->discount_comment = $comment !== '' ? Str::limit($comment, 120, '') : null;
                $net = round(max(0.0, $gross - $discountVal), 2);
                $invoice->subtotal = $net;
                $invoice->hst = $this->computeInvoiceHst($client_project, $invoice, $net);
                $invoice->total = round($invoice->subtotal + $invoice->hst, 2);
            } else {
                $invoice->discount_percent = null;
                $invoice->discount_amount = null;
                $invoice->discount_comment = null;
                $invoice->subtotal = $gross;
                $invoice->hst = $this->computeInvoiceHst($client_project, $invoice, $invoice->subtotal);
                $invoice->total = round($invoice->subtotal + $invoice->hst, 2);

                if ($data['manual_total'] !== null && ! $client_project->isHourly()) {
                    // Manual fixed-price invoices: if the items don't add up to the user-entered
                    // manual_total, we still persist a draft so work isn't lost — the
                    // UI surfaces a validation warning. For the "send" action this
                    // mismatch is a hard stop (see send()).
                    $invoice->subtotal = (float) $data['manual_total'];
                    $invoice->hst = $this->computeInvoiceHst($client_project, $invoice, $invoice->subtotal);
                    $invoice->total = round($invoice->subtotal + $invoice->hst, 2);

                    if (abs((float) array_sum(array_column($items, 'amount')) - (float) $data['manual_total']) > 0.01 && count($items)) {
                        // Fall back to items-sum as the authoritative subtotal if
                        // the user actually typed line items (prefer item sum).
                        $invoice->subtotal = (float) array_sum(array_column($items, 'amount'));
                        $invoice->hst = $this->computeInvoiceHst($client_project, $invoice, $invoice->subtotal);
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

        $pdf = $this->renderInvoicePdf($client_project, $invoice);

        return $pdf->stream($this->invoicePdfFilename($client_project, $invoice));
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

        $pdf = $this->renderInvoicePdf($client_project, $invoice);

        return $pdf->stream($this->invoicePdfFilename($client_project, $invoice));
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
                'Content-Disposition' => 'inline; filename="'.$this->receiptPdfFilename($client_project, $invoice).'"',
            ]);
        }

        $pdf = $this->renderReceiptPdf($client_project, $invoice);

        return $pdf->stream($this->receiptPdfFilename($client_project, $invoice));
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

        if (blank(trim((string) $client_project->client_email))) {
            return back()->with('error', "Cannot email the invoice — add the client's email address first.");
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
            $pdf = $this->renderReceiptPdf($client_project, $invoice);
            $bytes = $pdf->output();
            $relativePath = $this->receiptPdfStoragePath($client_project, $invoice);
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
            : $this->renderReceiptPdf($client_project, $invoice)->output();

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
                    $this->receiptPdfFilename($client_project, $invoice)
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
        $hst = 0.0;

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
                // HST for installment-linked invoices comes from the
                // quotation's actual HST share, NOT a flat 13% — see
                // ClientProjectInstallment::effectiveHst() for the rationale.
                $hst = round((float) $installment->effectiveHst(), 2);
            } else {
                $hst = round($subtotal * ClientProjectInvoice::HST_RATE, 2);
            }
        } else {
            // Manual invoice: seed pre-tax / HST from accepted quotations on fixed-price jobs.
            if (!$project->isHourly()) {
                $qSub = $project->quotationSubtotalForFinancials();
                if ($qSub !== null && $qSub >= 0.01) {
                    $subtotal = $qSub;
                    $hst = round((float) $project->acceptedQuotationsFinancialAggregateHst(), 2);
                } else {
                    $hst = round($subtotal * ClientProjectInvoice::HST_RATE, 2);
                }
            } else {
                $hst = round($subtotal * ClientProjectInvoice::HST_RATE, 2);
            }
        }

        $snap = $this->buildAcceptedQuotationsSnapshot($project);

        return $project->invoices()->create([
            'installment_number' => $installmentNumber,
            'installment_description' => $description,
            'invoice_number' => ClientProjectInvoice::generateNumber(),
            'items' => $items,
            'subtotal' => $subtotal,
            'hst' => $hst,
            'hst_exempt' => false,
            'total' => round($subtotal + $hst, 2),
            'status' => ClientProjectInvoice::STATUS_DRAFT,
            'version' => 1,
            'included_project_quotation_ids' => $snap['ids'],
            'included_quotations_snapshot' => $snap['rows'],
        ]);
    }

    /**
     * @return array{ids: list<int>, rows: list<array<string, mixed>>}
     */
    protected function buildAcceptedQuotationsSnapshot(ClientProject $project): array
    {
        $accepted = ClientProjectQuotation::query()
            ->where('client_project_id', $project->id)
            ->where('status', ClientProjectQuotation::STATUS_ACCEPTED)
            ->orderBy('id')
            ->get(['id', 'title', 'quotation_number', 'status', 'total_amount']);

        return [
            'ids' => $accepted->pluck('id')->values()->all(),
            'rows' => $accepted->map(static fn (ClientProjectQuotation $q) => [
                'id' => $q->id,
                'title' => $q->title,
                'quotation_number' => $q->quotation_number,
                'status' => $q->status,
                'total_amount' => $q->total_amount !== null ? (float) $q->total_amount : null,
            ])->values()->all(),
        ];
    }

    /**
     * Build a draft invoice for an hourly project from its unbilled,
     * closed time entries. Each entry becomes one editable line item
     * (description = "DD/MM/YYYY — note (or 'Hourly work')", amount =
     * hours × hourly_rate). Every entry is linked back to the new
     * invoice so it's excluded from the next "Create invoice from hours".
     *
     * If the admin deletes the draft later (destroy()), the FK's
     * ON DELETE SET NULL releases the entries back to "unbilled".
     */
    protected function createHourlyInvoice(ClientProject $project, $unbilledEntries): ClientProjectInvoice
    {
        $rate = round((float) ($project->hourly_rate ?? 0), 2);
        $items = [];

        foreach ($unbilledEntries as $entry) {
            $hours = round((float) ($entry->hours ?? 0), 2);
            if ($hours <= 0) {
                continue; // defensive: scopeClosed should already exclude these
            }

            // Build a parts list and join with " · ". Each component is
            // only appended when it has real content, so we never end up
            // with dangling separators.
            //
            //   [date] · [start – end] · [X.XX h @ $Y/h] · [note]
            //
            // The time range is only included when BOTH start_time and
            // end_time are set on the entry — i.e. the admin clocked in
            // and out. Legacy or bulk-entered rows (hours only) fall
            // back to date + total hours, per the user's explicit ask.
            $parts = [];

            if ($entry->occurred_on) {
                $parts[] = ClientProject::formatDate($entry->occurred_on);
            }

            if (!empty($entry->start_time) && !empty($entry->end_time)) {
                $parts[] = sprintf(
                    '%s – %s',
                    ClientProjectTimeEntry::formatTimeForDisplay($entry->start_time, $entry->occurred_on),
                    ClientProjectTimeEntry::formatTimeForDisplay($entry->end_time, $entry->occurred_on)
                );
            }

            $parts[] = sprintf('%.2f h @ %s/h', $hours, ClientProject::formatMoney($rate));

            $note = trim((string) ($entry->note ?? ''));
            if ($note !== '') {
                $parts[] = $note;
            }

            $items[] = [
                'description' => implode(' · ', $parts),
                'amount' => round($hours * $rate, 2),
                'line_kind' => ClientProjectInvoice::LINE_KIND_LABOUR,
            ];
        }

        $unbilledExpenses = $project->expenses()
            ->whereNull('client_project_invoice_id')
            ->orderBy('occurred_on')
            ->get();

        foreach ($unbilledExpenses as $exp) {
            $items[] = [
                'description' => sprintf(
                    'Materials · %s (%s)',
                    $exp->description,
                    $exp->occurred_on->format('M j, Y')
                ),
                // Amount entered on Financials tab is tax-included (Ontario POS style).
                'amount' => round((float) $exp->amount, 2),
                'line_kind' => ClientProjectInvoice::LINE_KIND_MATERIALS,
                'expense_id' => $exp->id,
            ];
        }

        [$labour, $materials] = $this->partitionHourlyLineAmounts($items);
        $hst = round($labour * ClientProjectInvoice::HST_RATE, 2);
        $grand = round($labour + $hst + $materials, 2);

        // Use a transaction so the link-back to time entries is atomic
        // with the invoice insert — an exception anywhere rolls back
        // and leaves the entries unbilled.
        return DB::transaction(function () use ($project, $items, $labour, $hst, $grand, $unbilledEntries) {
            $snap = $this->buildAcceptedQuotationsSnapshot($project);

            $invoice = $project->invoices()->create([
                'installment_number' => null,
                'installment_description' => 'Hourly work',
                'invoice_number' => ClientProjectInvoice::generateNumber(),
                'items' => $items,
                'subtotal' => $labour,
                'hst' => $hst,
                'hst_exempt' => false,
                'total' => $grand,
                'status' => ClientProjectInvoice::STATUS_DRAFT,
                'version' => 1,
                'included_project_quotation_ids' => $snap['ids'],
                'included_quotations_snapshot' => $snap['rows'],
            ]);

            // Attach the time entries to this invoice so they're treated
            // as "billed" for future hourly invoice creation.
            $project->timeEntries()
                ->whereIn('id', $unbilledEntries->pluck('id'))
                ->update(['client_project_invoice_id' => $invoice->id]);

            $this->syncHourlyExpenseLinks($project, $invoice, $items);

            return $invoice;
        });
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

    /**
     * HST that the invoice should bill, given a subtotal.
     *
     * Installment-linked invoices: HST is sliced proportionally from the
     * quotation's actual HST (some quotation lines may have been HST-exempt,
     * so 13% × subtotal would over-charge). The split is anchored on the
     * locked installment subtotal. When the billed subtotal differs (discount),
     * HST scales by the same ratio so the quotation's tax mix is preserved.
     *
     * Manual / hourly invoices: standard 13% on the supplied subtotal.
     */
    protected function computeInvoiceHst(ClientProject $project, ClientProjectInvoice $invoice, float $subtotal): float
    {
        // Cash-paid invoices: no HST charged regardless of installment vs manual.
        if ($invoice->isHstExempt()) {
            return 0.0;
        }

        if ($invoice->isManual()) {
            return round($subtotal * ClientProjectInvoice::HST_RATE, 2);
        }

        $installment = $project->installments()
            ->orderBy('sort_order')
            ->get()
            ->values()
            ->get($invoice->installment_number - 1);

        if (!$installment) {
            return round($subtotal * ClientProjectInvoice::HST_RATE, 2);
        }

        $lockedBase = round((float) ($installment->amount ?? 0), 2);
        $lockedHst = round((float) $installment->effectiveHst(), 2);

        if ($lockedBase <= 0.00001) {
            return round($subtotal * ClientProjectInvoice::HST_RATE, 2);
        }

        return round($lockedHst * ($subtotal / $lockedBase), 2);
    }

    /**
     * The HST amount the invoice will bill (matches whatever
     * computeInvoiceHst() resolves to at the locked subtotal). Used by
     * the editor to show the locked HST breakdown alongside the locked
     * subtotal/total without having to re-derive it on the front end.
     */
    protected function lockedHst(ClientProject $project, ClientProjectInvoice $invoice): float
    {
        if ($invoice->isHstExempt()) {
            return 0.0;
        }

        if ($invoice->isManual()) {
            $sub = $this->lockedSubtotal($project, $invoice);

            return round($sub * ClientProjectInvoice::HST_RATE, 2);
        }

        return $this->computeInvoiceHst($project, $invoice, $this->lockedSubtotal($project, $invoice));
    }

    protected function lockedTotalWithHst(ClientProject $project, ClientProjectInvoice $invoice): float
    {
        return round($this->lockedSubtotal($project, $invoice) + $this->lockedHst($project, $invoice), 2);
    }

    /**
     * Resolve installment invoice discount: percentage takes precedence over a fixed amount.
     *
     * @return array{0: float|null, 1: float|null, 2: float} Stored percent, stored amount column, dollars discounted.
     */
    protected function resolveInstallmentDiscountFromRequest(Request $request, float $grossLineSubtotal): array
    {
        $rawPct = $request->input('discount_percent');
        $rawAmt = $request->input('discount_amount');
        $pct = is_numeric($rawPct) ? (float) $rawPct : 0.0;
        $amt = $this->parseHybridDiscountCadInput($rawAmt, $grossLineSubtotal);

        if ($pct > 0.00001) {
            $d = round(min($grossLineSubtotal * $pct / 100, $grossLineSubtotal), 2);

            return [round($pct, 4), null, $d];
        }

        if ($amt > 0.00001) {
            $d = round(min($amt, $grossLineSubtotal), 2);

            return [null, $d, $d];
        }

        return [null, null, 0.0];
    }

    /**
     * Matches JS cpMoneyMask.parseHybridCadMoney: no "." → digits are minor units / 100;
     * includes "." → plain decimal dollars.
     */
    protected function parseHybridDiscountCadInput(mixed $raw, float $grossLineSubtotal): float
    {
        if ($raw === null || $raw === '') {
            return 0.0;
        }

        $s = preg_replace('/\s+/u', '', str_replace(',', '', trim((string) $raw)));
        if ($s === '') {
            return 0.0;
        }

        if (str_contains($s, '.')) {
            if (! is_numeric($s)) {
                return 0.0;
            }

            return round(min(max(0.0, (float) $s), $grossLineSubtotal), 2);
        }

        $digits = preg_replace('/\D+/', '', $s);
        if ($digits === '') {
            return 0.0;
        }

        $digits = ltrim($digits, '0');
        if ($digits === '') {
            $digits = '0';
        }

        $minor = (int) $digits;

        return round(min($minor / 100.0, $grossLineSubtotal), 2);
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

    /** @return array{0: float, 1: float} Labour (pre-HST) and materials (tax-included) totals. */
    protected function partitionHourlyLineAmounts(array $items): array
    {
        $labour = 0.0;
        $materials = 0.0;
        foreach ($items as $row) {
            $amt = round((float) ($row['amount'] ?? 0), 2);
            $kind = $row['line_kind'] ?? ClientProjectInvoice::LINE_KIND_LABOUR;
            if ($kind === ClientProjectInvoice::LINE_KIND_MATERIALS || $kind === ClientProjectInvoice::LINE_KIND_GAS) {
                $materials += $amt;
            } elseif ($kind === ClientProjectInvoice::LINE_KIND_LABOUR_CREDIT) {
                $labour -= abs($amt);
            } else {
                $labour += $amt;
            }
        }

        return [round($labour, 2), round($materials, 2)];
    }

    /**
     * Normalise hourly invoice rows + validate materials lines that reference
     * a project expense snapshot.
     *
     * @return array<int, array{description: string, amount: float, line_kind: string, expense_id?: int}>
     */
    protected function normaliseHourlyInvoiceItems(ClientProject $project, array $rows): array
    {
        $clean = [];
        foreach ($rows as $row) {
            $description = trim((string) ($row['description'] ?? ''));
            $amount = (float) ($row['amount'] ?? 0);
            $kind = strtolower(trim((string) ($row['line_kind'] ?? ClientProjectInvoice::LINE_KIND_LABOUR)));
            if ($kind === ClientProjectInvoice::LINE_KIND_MATERIALS || $kind === ClientProjectInvoice::LINE_KIND_GAS) {
                // keep
            } elseif ($kind === ClientProjectInvoice::LINE_KIND_LABOUR_CREDIT) {
                $kind = ClientProjectInvoice::LINE_KIND_LABOUR_CREDIT;
            } else {
                $kind = ClientProjectInvoice::LINE_KIND_LABOUR;
            }
            if ($description === '' && $amount == 0.0) {
                continue;
            }

            if ($kind === ClientProjectInvoice::LINE_KIND_LABOUR_CREDIT) {
                $amount = abs($amount);
                if ($amount < 0.0001) {
                    continue;
                }
            }
            if (($kind === ClientProjectInvoice::LINE_KIND_MATERIALS || $kind === ClientProjectInvoice::LINE_KIND_GAS) && $amount < 0) {
                continue;
            }

            $out = [
                'description' => $description,
                'amount' => round($amount, 2),
                'line_kind' => $kind,
            ];

            $eid = isset($row['expense_id']) ? (int) $row['expense_id'] : null;
            if ($eid && $kind === ClientProjectInvoice::LINE_KIND_MATERIALS) {
                $owns = ClientProjectExpense::query()
                    ->where('client_project_id', $project->id)
                    ->where('id', $eid)
                    ->exists();
                if ($owns) {
                    $out['expense_id'] = $eid;
                }
            }

            $clean[] = $out;
        }

        return $clean;
    }

    /**
     * Keep client_project_expenses.client_project_invoice_id aligned with
     * materials lines that reference an expense snapshot.
     */
    protected function syncHourlyExpenseLinks(ClientProject $project, ClientProjectInvoice $invoice, array $items): void
    {
        $wanted = [];
        foreach ($items as $row) {
            if (($row['line_kind'] ?? '') === ClientProjectInvoice::LINE_KIND_MATERIALS && ! empty($row['expense_id'])) {
                $wanted[] = (int) $row['expense_id'];
            }
        }
        $wanted = array_values(array_unique($wanted));

        $clear = ClientProjectExpense::query()
            ->where('client_project_id', $project->id)
            ->where('client_project_invoice_id', $invoice->id);

        if (count($wanted) > 0) {
            $clear->whereNotIn('id', $wanted);
        }

        $clear->update(['client_project_invoice_id' => null]);

        foreach ($wanted as $id) {
            ClientProjectExpense::query()
                ->where('client_project_id', $project->id)
                ->where('id', $id)
                ->update(['client_project_invoice_id' => $invoice->id]);
        }
    }

    // --- PDF rendering ---------------------------------------------------

    protected function renderInvoicePdf(ClientProject $project, ClientProjectInvoice $invoice)
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

    protected function renderReceiptPdf(ClientProject $project, ClientProjectInvoice $invoice)
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

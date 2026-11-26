<?php

namespace Tests\Feature\Invoices;

use App\Mail\ClientProjectInvoiceClient;
use App\Models\ClientProject;
use App\Models\ClientProjectExpense;
use App\Models\ClientProjectInvoice;
use App\Services\Invoice\InvoicePdfService;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Tests\IntegrationTestCase;
use Tests\Support\ActsAsAdmin;

class InvoiceEmailExpenseReceiptsTest extends IntegrationTestCase
{
    use ActsAsAdmin;

    /** @var list<object> */
    private array $receiptWarnings = [];

    public function test_checkbox_on_attaches_receipts_with_names_and_mimes(): void
    {
        [$project, $invoice] = $this->prepareSend();
        $this->addReceipt($project, 1, 'webp', 32, '2026-03-01');
        $this->addReceipt($project, 2, 'pdf', 40, '2026-03-02');
        $total = (float) $invoice->total;

        $html = $this->get(route('admin.client-projects.show', $project))->assertOk()->getContent();
        $this->assertMatchesRegularExpression(
            '/Attach expense receipts\s*<\/span>[\s\S]{0,800}\(2\)/',
            $html
        );
        $this->assertMatchesRegularExpression(
            '/id="cp-send-invoice-attach-receipts"[^>]*\bchecked\b/',
            $html
        );
        $this->assertStringContainsString('data-confirm-checkbox-name="attach_expense_receipts"', $html);
        $this->assertStringContainsString('class="admin-info-tip"', $html);
        $this->assertStringContainsString('File names match the Expenses numbers.', $html);
        $this->assertDoesNotMatchRegularExpression(
            '/<p[^>]*>[^<]*File names match the Expenses numbers\./',
            $html
        );

        $pdf = '%PDF-invoice-on';
        $this->mockPdf($pdf);
        $this->postSend($project, $invoice, ['attach_expense_receipts' => '1']);

        [$mail, $body] = $this->builtMail();
        $this->assertInvoicePdf($mail, $pdf);
        $this->assertSame([
            ['as' => 'Receipt-1.webp', 'mime' => 'image/webp'],
            ['as' => 'Receipt-2.pdf', 'mime' => 'application/pdf'],
        ], $this->receiptAttachments($mail));
        $this->assertStringContainsString(
            '2 receipt files are attached. The file names match the Expenses numbers.',
            $body
        );
        $this->assertStringNotContainsString('The rest are available on request.', $body);
        $this->assertEqualsWithDelta($total, (float) $invoice->fresh()->total, 0.001);
        $this->assertSame([], $this->receiptWarnings);
    }

    public function test_checkbox_off_attaches_only_the_invoice_pdf(): void
    {
        [$project, $invoice] = $this->prepareSend();
        $this->addReceipt($project, 1, 'webp', 32, '2026-03-01');
        $this->addReceipt($project, 2, 'pdf', 40, '2026-03-02');

        $pdf = '%PDF-invoice-off';
        $this->mockPdf($pdf);
        $this->postSend($project, $invoice);

        [$mail, $body] = $this->builtMail();
        $this->assertInvoicePdf($mail, $pdf);
        $this->assertSame([], $this->receiptAttachments($mail));
        $this->assertReceiptCopyAbsent($body);
        $this->assertSame([], $this->receiptWarnings);
    }

    public function test_project_with_no_receipts_is_unchanged(): void
    {
        [$project, $invoice] = $this->prepareSend();
        ClientProjectExpense::factory()->create([
            'client_project_id' => $project->id,
            'expense_ref' => 1,
            'occurred_on' => '2026-03-01',
            'description' => 'Lumber',
            'amount' => 25,
            'receipt_path' => null,
        ]);

        $html = $this->get(route('admin.client-projects.show', $project))->assertOk()->getContent();
        $this->assertStringNotContainsString('Attach expense receipts', $html);
        $this->assertStringNotContainsString('cp-send-invoice-receipt-option', $html);

        $pdf = '%PDF-invoice-none';
        $this->mockPdf($pdf);
        $this->postSend($project, $invoice, ['attach_expense_receipts' => '1']);

        [$mail, $body] = $this->builtMail();
        $this->assertInvoicePdf($mail, $pdf);
        $this->assertSame([], $this->receiptAttachments($mail));
        $this->assertReceiptCopyAbsent($body);
        $this->assertSame([], $this->receiptWarnings);
    }

    public function test_missing_receipt_file_is_skipped(): void
    {
        [$project, $invoice] = $this->prepareSend();
        $missing = $this->addReceipt($project, 1, 'webp', null, '2026-03-01');
        $this->addReceipt($project, 2, 'pdf', 24, '2026-03-02');

        $pdf = '%PDF-invoice-missing';
        $this->mockPdf($pdf);
        $this->postSend($project, $invoice, ['attach_expense_receipts' => '1']);

        [$mail, $body] = $this->builtMail();
        $this->assertInvoicePdf($mail, $pdf);
        $this->assertSame([
            ['as' => 'Receipt-2.pdf', 'mime' => 'application/pdf'],
        ], $this->receiptAttachments($mail));
        $this->assertStringContainsString(
            '1 receipt file is attached. The file name matches the Expenses numbers.',
            $body
        );
        $this->assertStringNotContainsString('The rest are available on request.', $body);

        $this->assertCount(1, $this->receiptWarnings);
        $this->assertSame('warning', $this->receiptWarnings[0]->level);
        $this->assertSame('Invoice email skipped a missing expense receipt', $this->receiptWarnings[0]->message);
        $this->assertSame((int) $missing->id, (int) $this->receiptWarnings[0]->context['expense_id']);
    }

    public function test_size_cap_attaches_what_fits_in_expense_order(): void
    {
        [$project, $invoice] = $this->prepareSend();
        $this->addReceipt($project, 1, 'webp', 100, '2026-03-01');
        $this->addReceipt($project, 2, 'pdf', ClientProjectInvoiceClient::ATTACHMENT_BYTE_CAP, '2026-03-02');
        $this->addReceipt($project, 3, 'webp', 100, '2026-03-03');

        $pdf = str_repeat('P', 200);
        $this->mockPdf($pdf);
        $this->postSend($project, $invoice, ['attach_expense_receipts' => '1']);

        [$mail, $body] = $this->builtMail();
        $this->assertInvoicePdf($mail, $pdf);
        $this->assertSame([
            ['as' => 'Receipt-1.webp', 'mime' => 'image/webp'],
            ['as' => 'Receipt-3.webp', 'mime' => 'image/webp'],
        ], $this->receiptAttachments($mail));
        $this->assertStringContainsString(
            '2 receipt files are attached. The file names match the Expenses numbers.',
            $body
        );
        $this->assertStringContainsString('The rest are available on request.', $body);
        $this->assertSame([], $this->receiptWarnings);
    }

    /**
     * @return array{0: ClientProject, 1: ClientProjectInvoice}
     */
    private function prepareSend(): array
    {
        Mail::fake();
        Storage::fake('local');
        $this->receiptWarnings = [];
        Log::listen(function ($event) {
            if ($event->level === 'warning' && $event->message === 'Invoice email skipped a missing expense receipt') {
                $this->receiptWarnings[] = $event;
            }
        });
        $this->actingAsAdmin();

        $project = ClientProject::factory()->create([
            'status' => ClientProject::STATUS_IN_PROGRESS,
            'billing_type' => ClientProject::BILLING_FIXED,
            'client_name' => 'Alex Client',
            'client_email' => 'alex@example.com',
            'name' => 'Kitchen refresh',
        ]);

        $invoice = ClientProjectInvoice::factory()->for($project, 'project')->create([
            'due_on' => '2026-12-15',
            'installment_number' => null,
            'installment_description' => 'Manual invoice',
        ]);

        return [$project, $invoice];
    }

    private function addReceipt(ClientProject $project, int $ref, string $ext, ?int $bytes, string $date): ClientProjectExpense
    {
        $relative = "expense-receipts/{$project->id}/Receipt-{$ref}.{$ext}";
        $expense = ClientProjectExpense::factory()->create([
            'client_project_id' => $project->id,
            'expense_ref' => $ref,
            'occurred_on' => $date,
            'description' => 'Supplies '.$ref,
            'amount' => 12.50,
            'receipt_path' => $relative,
        ]);

        if ($bytes !== null) {
            Storage::disk('local')->put($relative, 'x');
            $absolute = Storage::disk('local')->path($relative);
            $handle = fopen($absolute, 'c');
            $this->assertNotFalse($handle);
            $this->assertTrue(ftruncate($handle, $bytes));
            fclose($handle);
        }

        return $expense;
    }

    private function mockPdf(string $bytes): void
    {
        $this->mock(InvoicePdfService::class, function ($mock) use ($bytes) {
            $pdf = new class($bytes)
            {
                public function __construct(private string $bytes)
                {
                }

                public function output(): string
                {
                    return $this->bytes;
                }
            };

            $mock->shouldReceive('renderInvoicePdf')->andReturn($pdf);
            $mock->shouldReceive('invoicePdfStoragePath')->andReturn('invoices/test-invoice.pdf');
            $mock->shouldReceive('invoicePdfFilename')->andReturn('Invoice-100.pdf');
        });
    }

    private function postSend(ClientProject $project, ClientProjectInvoice $invoice, array $payload = []): void
    {
        $this->post(route('admin.client-projects.invoices.send', [$project, $invoice]), $payload)
            ->assertRedirect()
            ->assertSessionHas('success');
    }

    /**
     * @return array{0: ClientProjectInvoiceClient, 1: string}
     */
    private function builtMail(): array
    {
        $matches = Mail::sent(ClientProjectInvoiceClient::class);
        $this->assertCount(1, $matches);
        /** @var ClientProjectInvoiceClient $mail */
        $mail = $matches->first();
        $mail->build();
        $html = view($mail->view, $mail->viewData)->render();

        return [$mail, $html];
    }

    private function assertInvoicePdf(ClientProjectInvoiceClient $mail, string $bytes): void
    {
        $this->assertCount(1, $mail->rawAttachments);
        $this->assertSame('Invoice-100.pdf', $mail->rawAttachments[0]['name']);
        $this->assertSame('application/pdf', $mail->rawAttachments[0]['options']['mime'] ?? null);
        $this->assertSame($bytes, $mail->rawAttachments[0]['data']);
    }

    /**
     * @return list<array{as: string|null, mime: string|null}>
     */
    private function receiptAttachments(ClientProjectInvoiceClient $mail): array
    {
        return array_map(function (array $row) {
            return [
                'as' => $row['options']['as'] ?? null,
                'mime' => $row['options']['mime'] ?? null,
            ];
        }, $mail->attachments);
    }

    private function assertReceiptCopyAbsent(string $body): void
    {
        $this->assertStringNotContainsString('receipt file', $body);
        $this->assertStringNotContainsString('Expenses numbers', $body);
        $this->assertStringNotContainsString('available on request', $body);
    }
}

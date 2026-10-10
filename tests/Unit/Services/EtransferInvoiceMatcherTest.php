<?php

namespace Tests\Unit\Services;

use App\Models\ClientProject;
use App\Models\ClientProjectInstallment;
use App\Models\ClientProjectInvoice;
use App\Models\EtransferNotice;
use App\Services\Etransfer\EtransferInvoiceMatcher;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Tests\TestCase;

class EtransferInvoiceMatcherTest extends TestCase
{
    private EtransferInvoiceMatcher $matcher;

    protected function setUp(): void
    {
        parent::setUp();
        $this->matcher = new EtransferInvoiceMatcher;
    }

    public function test_invoice_number_in_message_ranks_first(): void
    {
        $target = $this->makeInvoice(1, 'INV-2026-0042', 500.00, 'Other Person', 'other@example.test');
        $other = $this->makeInvoice(2, 'INV-2026-0001', 1130.00, 'Jane Citizen', 'jane@example.test');

        $notice = new EtransferNotice([
            'sender_name' => 'Someone Else',
            'sender_email' => 'nobody@example.test',
            'amount' => 1130.00,
            'message' => 'Please apply to INV-2026-0042',
        ]);

        $ranked = $this->matcher->rank($notice, collect([$target, $other]));

        $this->assertSame(1, $ranked[0]['invoice_id']);
        $this->assertContains('Invoice number', $ranked[0]['reasons']);
        $this->assertGreaterThanOrEqual(EtransferInvoiceMatcher::SCORE_INVOICE_NUMBER, $ranked[0]['score']);
    }

    public function test_amount_and_name_match_open_invoice(): void
    {
        $invoice = $this->makeInvoice(10, 'INV-2026-0100', 1130.00, 'Jane Citizen', 'jane@example.test');
        $notice = new EtransferNotice([
            'sender_name' => 'Jane Citizen',
            'sender_email' => 'jane@example.test',
            'amount' => 1130.00,
            'message' => 'kitchen deposit',
        ]);

        $ranked = $this->matcher->rank($notice, collect([$invoice]));

        $this->assertCount(1, $ranked);
        $this->assertContains('Amount matches', $ranked[0]['reasons']);
        $this->assertContains('Sender email', $ranked[0]['reasons']);
        $this->assertContains('Name similar', $ranked[0]['reasons']);
    }

    public function test_amount_matches_unpaid_deposit_instalment(): void
    {
        $invoice = $this->makeInvoice(11, 'INV-2026-0200', 2260.00, 'Alex Martin', 'alex@example.test', 1);
        $installment = new ClientProjectInstallment([
            'sort_order' => 0,
            'label' => 'On signing',
            'amount' => 2000.00,
            'is_paid' => false,
        ]);
        $invoice->project->setRelation('installments', collect([$installment]));

        $notice = new EtransferNotice([
            'sender_name' => 'Alex Martin',
            'amount' => 2260.00,
            'message' => 'deposit',
        ]);

        $ranked = $this->matcher->rank($notice, collect([$invoice]));
        $this->assertNotEmpty($ranked);
        $this->assertTrue(
            in_array('Amount matches', $ranked[0]['reasons'], true)
            || in_array('Deposit amount', $ranked[0]['reasons'], true)
        );
    }

    public function test_paid_invoices_without_memo_number_are_skipped(): void
    {
        $paid = $this->makeInvoice(12, 'INV-2026-0300', 100.00, 'Pat Lee', 'pat@example.test');
        $paid->status = ClientProjectInvoice::STATUS_PAID;

        $notice = new EtransferNotice([
            'sender_name' => 'Pat Lee',
            'amount' => 100.00,
            'message' => 'thanks',
        ]);

        $this->assertSame([], $this->matcher->rank($notice, collect([$paid])));
    }

    public function test_zohar_barak_paid_invoice_in_memo_is_flagged_already_paid(): void
    {
        $paid = $this->makeInvoice(257, 'INV-2026-0257', 2260.00, 'Zohar Barak', 'zohar@example.test');
        $paid->status = ClientProjectInvoice::STATUS_PAID;

        $unrelated = $this->makeInvoice(300, 'INV-2026-0300', 500.00, 'Recent Client', 'recent@example.test');

        $notice = new EtransferNotice([
            'sender_name' => 'Zohar Barak',
            'sender_email' => null,
            'amount' => 2260.00,
            'message' => 'invoice #INV-2026-0257',
        ]);

        $ranked = $this->matcher->rank($notice, collect([$unrelated, $paid]));

        $this->assertNotEmpty($ranked);
        $this->assertSame(257, $ranked[0]['invoice_id']);
        $this->assertSame('INV-2026-0257', $ranked[0]['invoice_number']);
        $this->assertTrue($ranked[0]['already_paid']);
        $this->assertContains(EtransferInvoiceMatcher::REASON_ALREADY_PAID, $ranked[0]['reasons']);
        $this->assertContains('Invoice number', $ranked[0]['reasons']);
        $this->assertSame(ClientProjectInvoice::STATUS_PAID, $ranked[0]['status']);
    }

    public function test_malcolm_travis_amount_and_name_match_inv_2026_0258(): void
    {
        $invoice = $this->makeInvoice(258, 'INV-2026-0258', 379.61, 'Malcolm Travis', 'malcolm@example.test');
        $other = $this->makeInvoice(301, 'INV-2026-0301', 1800.00, 'Other Client', 'other@example.test');

        $notice = new EtransferNotice([
            'sender_name' => 'Malcolm Travis',
            'sender_email' => null,
            'amount' => 379.61,
            'message' => null,
        ]);

        $ranked = $this->matcher->rank($notice, collect([$other, $invoice]));

        $this->assertNotEmpty($ranked);
        $this->assertSame(258, $ranked[0]['invoice_id']);
        $this->assertSame('INV-2026-0258', $ranked[0]['invoice_number']);
        $this->assertFalse($ranked[0]['already_paid']);
        $this->assertContains('Amount matches', $ranked[0]['reasons']);
        $this->assertContains('Name similar', $ranked[0]['reasons']);
        $this->assertGreaterThanOrEqual($this->matcher->minMatchScore(), $ranked[0]['score']);
    }

    public function test_recency_alone_is_below_confident_match_threshold(): void
    {
        $recent = $this->makeInvoice(400, 'INV-2026-0400', 1800.00, 'Unrelated Person', 'unrelated@example.test');

        $notice = new EtransferNotice([
            'sender_name' => 'Someone Else',
            'sender_email' => 'nobody@example.test',
            'amount' => 55.55,
            'message' => 'thanks',
        ]);

        $ranked = $this->matcher->rank($notice, collect([$recent]));

        $this->assertSame([], $ranked);
        $this->assertGreaterThan(EtransferInvoiceMatcher::SCORE_RECENCY_MAX, $this->matcher->minMatchScore());
    }

    public function test_last_name_similarity(): void
    {
        $score = $this->matcher->nameSimilarity('Jane Citizen', 'J. Citizen');
        $this->assertGreaterThanOrEqual(EtransferInvoiceMatcher::NAME_SIMILARITY_THRESHOLD, $score);
    }

    private function makeInvoice(
        int $id,
        string $number,
        float $total,
        string $clientName,
        string $clientEmail,
        ?int $installmentNumber = null,
    ): ClientProjectInvoice {
        $project = new ClientProject([
            'name' => $clientName.' Project',
            'client_name' => $clientName,
            'client_email' => $clientEmail,
        ]);
        $project->id = $id + 100;
        $project->setRelation('client', null);
        $project->setRelation('installments', new Collection);

        $invoice = new ClientProjectInvoice([
            'invoice_number' => $number,
            'total' => $total,
            'status' => ClientProjectInvoice::STATUS_SENT,
            'installment_number' => $installmentNumber,
            'sent_at' => Carbon::now()->subDays(3),
        ]);
        $invoice->id = $id;
        $invoice->setRelation('project', $project);

        return $invoice;
    }
}

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

    public function test_open_cited_amount_match_ranks_above_paid_cited_invoice(): void
    {
        $paid = $this->makeInvoice(257, 'INV-2026-0257', 2260.00, 'Zohar Barak', 'zohar@example.test');
        $paid->status = ClientProjectInvoice::STATUS_PAID;
        $open = $this->makeInvoice(258, 'INV-2026-0258', 379.61, 'Malcolm Travis', 'malcolm@example.test');

        $notice = new EtransferNotice([
            'sender_name' => 'Zohar Barak',
            'amount' => 379.61,
            'message' => 'INV-2026-0257 and INV-2026-0258',
        ]);

        $suggestion = $this->matcher->suggest($notice, collect([$open, $paid]));

        $this->assertSame(258, $suggestion['best']['invoice_id']);
        $this->assertSame('INV-2026-0258', $suggestion['best']['invoice_number']);
        $this->assertTrue($suggestion['recordable']);
        $this->assertFalse($suggestion['already_paid']);
        $this->assertFalse($suggestion['ambiguous']);
    }

    public function test_already_paid_cited_invoice_ranks_above_unrelated_open_invoice(): void
    {
        $paid = $this->makeInvoice(257, 'INV-2026-0257', 2260.00, 'Zohar Barak', 'zohar@example.test');
        $paid->status = ClientProjectInvoice::STATUS_PAID;
        $unrelated = $this->makeInvoice(300, 'INV-2026-0300', 2260.00, 'Recent Client', 'recent@example.test');

        $notice = new EtransferNotice([
            'sender_name' => 'Someone Else',
            'amount' => 2260.00,
            'message' => 'invoice #INV-2026-0257',
        ]);

        $suggestion = $this->matcher->suggest($notice, collect([$unrelated, $paid]));

        $this->assertSame(257, $suggestion['best']['invoice_id']);
        $this->assertTrue($suggestion['already_paid']);
        $this->assertFalse($suggestion['recordable']);
        $this->assertContains(EtransferInvoiceMatcher::REASON_ALREADY_PAID, $suggestion['best']['reasons']);
    }

    public function test_short_memo_forms_require_invoice_keyword(): void
    {
        $paid = $this->makeInvoice(257, 'INV-2026-0257', 2260.00, 'Zohar Barak', 'zohar@example.test');
        $paid->status = ClientProjectInvoice::STATUS_PAID;

        foreach (['#257', '0257', 'Unit #257 bathroom'] as $memo) {
            $this->assertFalse(
                $this->matcher->invoiceNumberAppears('INV-2026-0257', mb_strtolower($memo)),
                'Bare or unit form should not cite INV-2026-0257: '.$memo
            );
            $notice = new EtransferNotice([
                'sender_name' => 'Zohar Barak',
                'amount' => 100.00,
                'message' => $memo,
            ]);
            $this->assertSame([], $this->matcher->rank($notice, collect([$paid])), 'Memo '.$memo);
        }

        foreach (['invoice 257', 'inv 257', 'invoice #257', 'inv#257'] as $memo) {
            $this->assertTrue(
                $this->matcher->shortInvoiceSequenceAppears('INV-2026-0257', mb_strtolower($memo)),
                'Expected '.$memo.' to cite INV-2026-0257'
            );

            $notice = new EtransferNotice([
                'sender_name' => 'Zohar Barak',
                'amount' => 100.00,
                'message' => $memo,
            ]);
            $ranked = $this->matcher->rank($notice, collect([$paid]));
            $this->assertNotEmpty($ranked, 'Expected a candidate for memo '.$memo);
            $this->assertSame('INV-2026-0257', $ranked[0]['invoice_number']);
            $this->assertTrue($ranked[0]['already_paid']);
            $this->assertSame(EtransferInvoiceMatcher::CITATION_SHORT, $ranked[0]['citation']);
        }
    }

    public function test_unit_hash_does_not_outrank_malcolm_amount_match(): void
    {
        $malcolm = $this->makeInvoice(258, 'INV-2026-0258', 379.61, 'Malcolm Travis', 'malcolm@example.test');
        $other = $this->makeInvoice(305, 'INV-2026-0305', 900.00, 'Other Client', 'other@example.test');

        $notice = new EtransferNotice([
            'sender_name' => 'Malcolm Travis',
            'amount' => 379.61,
            'message' => 'Unit #305 bathroom',
        ]);

        $this->assertFalse($this->matcher->invoiceNumberAppears('INV-2026-0305', 'unit #305 bathroom'));

        $suggestion = $this->matcher->suggest($notice, collect([$other, $malcolm]));
        $this->assertSame(258, $suggestion['best']['invoice_id']);
        $this->assertSame('INV-2026-0258', $suggestion['best']['invoice_number']);
        $this->assertTrue($suggestion['recordable']);
        $this->assertFalse($suggestion['ambiguous']);
    }

    public function test_phone_number_ending_in_invoice_digits_is_not_a_citation(): void
    {
        $malcolm = $this->makeInvoice(258, 'INV-2026-0258', 379.61, 'Malcolm Travis', 'malcolm@example.test');
        $other = $this->makeInvoice(259, 'INV-2026-0259', 900.00, 'Other Client', 'other@example.test');

        $notice = new EtransferNotice([
            'sender_name' => 'Malcolm Travis',
            'amount' => 379.61,
            'message' => 'Call 647-834-0258',
        ]);

        $this->assertFalse($this->matcher->invoiceNumberAppears('INV-2026-0258', 'call 647-834-0258'));
        $this->assertStringNotContainsString('0258', $this->matcher->maskPhoneNumbers('call 647-834-0258'));

        $suggestion = $this->matcher->suggest($notice, collect([$other, $malcolm]));
        $this->assertSame('INV-2026-0258', $suggestion['best']['invoice_number']);
        $this->assertContains('Amount matches', $suggestion['best']['reasons']);
        $this->assertNull($suggestion['best']['citation']);
    }

    public function test_bare_hash_does_not_match_invoice_from_any_year(): void
    {
        $paid = $this->makeInvoice(257, 'INV-2025-0257', 2260.00, 'Zohar Barak', 'zohar@example.test');
        $paid->status = ClientProjectInvoice::STATUS_PAID;

        $notice = new EtransferNotice([
            'sender_name' => 'Someone Else',
            'amount' => 50.00,
            'message' => '#257',
        ]);

        $this->assertFalse($this->matcher->invoiceNumberAppears('INV-2025-0257', '#257'));
        $this->assertSame([], $this->matcher->rank($notice, collect([$paid])));
    }

    public function test_short_form_without_same_client_or_amount_is_ignored(): void
    {
        $other = $this->makeInvoice(305, 'INV-2026-0305', 900.00, 'Other Client', 'other@example.test');

        $notice = new EtransferNotice([
            'sender_name' => 'Unrelated Person',
            'amount' => 50.00,
            'message' => 'invoice #305',
        ]);

        $this->assertTrue($this->matcher->shortInvoiceSequenceAppears('INV-2026-0305', 'invoice #305'));
        $this->assertSame([], $this->matcher->rank($notice, collect([$other])));
    }

    public function test_four_digit_token_is_not_a_citation(): void
    {
        $invoice = $this->makeInvoice(305, 'INV-2026-0305', 900.00, 'Other Client', 'other@example.test');
        $invoice->status = ClientProjectInvoice::STATUS_PAID;

        $notice = new EtransferNotice([
            'sender_name' => 'Other Client',
            'amount' => 900.00,
            'message' => '0305',
        ]);

        $this->assertFalse($this->matcher->invoiceNumberAppears('INV-2026-0305', '0305'));
        $this->assertSame([], $this->matcher->rank($notice, collect([$invoice])));
    }

    public function test_full_invoice_number_is_accepted_anywhere_in_the_memo(): void
    {
        $target = $this->makeInvoice(42, 'INV-2026-0042', 500.00, 'Other Person', 'other@example.test');

        $notice = new EtransferNotice([
            'sender_name' => 'Someone Else',
            'amount' => 12.00,
            'message' => 'Thanks — please apply leftover to INV-2026-0042 after the tile.',
        ]);

        $this->assertTrue($this->matcher->fullInvoiceNumberAppears('INV-2026-0042', mb_strtolower((string) $notice->message)));
        $ranked = $this->matcher->rank($notice, collect([$target]));
        $this->assertSame('INV-2026-0042', $ranked[0]['invoice_number']);
        $this->assertSame(EtransferInvoiceMatcher::CITATION_FULL, $ranked[0]['citation']);
    }

    public function test_short_form_never_outranks_amount_match(): void
    {
        $malcolm = $this->makeInvoice(258, 'INV-2026-0258', 379.61, 'Malcolm Travis', 'malcolm@example.test');
        $other = $this->makeInvoice(305, 'INV-2026-0305', 900.00, 'Other Client', 'other@example.test');

        $notice = new EtransferNotice([
            'sender_name' => 'Other Client',
            'amount' => 379.61,
            'message' => 'invoice #305',
        ]);

        $suggestion = $this->matcher->suggest($notice, collect([$other, $malcolm]));
        $this->assertTrue($suggestion['ambiguous']);
        $this->assertSame('conflict', $suggestion['ambiguous_reason']);
        $this->assertNull($suggestion['best']);
        $this->assertFalse($suggestion['recordable']);
        $this->assertSame('INV-2026-0305', $suggestion['ambiguous_labels'][0]['invoice_number']);
        $this->assertSame('Other Client', $suggestion['ambiguous_labels'][0]['client_name']);
        $this->assertSame('INV-2026-0258', $suggestion['ambiguous_labels'][1]['invoice_number']);
        $this->assertSame('Malcolm Travis', $suggestion['ambiguous_labels'][1]['client_name']);
    }

    public function test_cited_other_client_conflicts_with_sender_amount_name_match(): void
    {
        $malcolm = $this->makeInvoice(258, 'INV-2026-0258', 379.61, 'Malcolm Travis', 'malcolm@example.test');
        $zohar = $this->makeInvoice(400, 'INV-2026-0400', 500.00, 'Zohar Barak', 'zohar@example.test');

        $notice = new EtransferNotice([
            'sender_name' => 'Zohar Barak',
            'amount' => 500.00,
            'message' => 'INV-2026-0258',
        ]);

        $suggestion = $this->matcher->suggest($notice, collect([$malcolm, $zohar]));
        $this->assertTrue($suggestion['ambiguous']);
        $this->assertSame('conflict', $suggestion['ambiguous_reason']);
        $this->assertNull($suggestion['best']);
        $this->assertFalse($suggestion['recordable']);
        $this->assertSame('INV-2026-0258', $suggestion['ambiguous_labels'][0]['invoice_number']);
        $this->assertSame('Malcolm Travis', $suggestion['ambiguous_labels'][0]['client_name']);
        $this->assertSame('INV-2026-0400', $suggestion['ambiguous_labels'][1]['invoice_number']);
        $this->assertSame('Zohar Barak', $suggestion['ambiguous_labels'][1]['client_name']);
    }

    public function test_cited_zohar_invoice_conflicts_with_malcolm_amount_name_match(): void
    {
        $malcolm = $this->makeInvoice(258, 'INV-2026-0258', 379.61, 'Malcolm Travis', 'malcolm@example.test');
        $zohar = $this->makeInvoice(400, 'INV-2026-0400', 500.00, 'Zohar Barak', 'zohar@example.test');

        $notice = new EtransferNotice([
            'sender_name' => 'Malcolm Travis',
            'amount' => 379.61,
            'message' => 'INV-2026-0400',
        ]);

        $suggestion = $this->matcher->suggest($notice, collect([$malcolm, $zohar]));
        $this->assertTrue($suggestion['ambiguous']);
        $this->assertSame('conflict', $suggestion['ambiguous_reason']);
        $this->assertNull($suggestion['best']);
        $this->assertFalse($suggestion['recordable']);
        $this->assertSame('INV-2026-0400', $suggestion['ambiguous_labels'][0]['invoice_number']);
        $this->assertSame('Zohar Barak', $suggestion['ambiguous_labels'][0]['client_name']);
        $this->assertSame('INV-2026-0258', $suggestion['ambiguous_labels'][1]['invoice_number']);
        $this->assertSame('Malcolm Travis', $suggestion['ambiguous_labels'][1]['client_name']);
    }

    public function test_phone_masking_excludes_digits_from_lookup_tokens(): void
    {
        $this->assertSame([], $this->matcher->mentionLookupTokens('call 647-834-0258'));
        $this->assertSame([], $this->matcher->mentionLookupTokens('please call inv 647-834-0258'));
        $this->assertContains('257', $this->matcher->mentionLookupTokens('invoice #257'));
        $this->assertContains('INV-2026-0258', $this->matcher->mentionLookupTokens('please apply INV-2026-0258'));
    }

    public function test_short_form_same_client_without_amount_is_accepted(): void
    {
        $paid = $this->makeInvoice(257, 'INV-2026-0257', 2260.00, 'Zohar Barak', 'zohar@example.test');
        $paid->status = ClientProjectInvoice::STATUS_PAID;

        $notice = new EtransferNotice([
            'sender_name' => 'Z. Barak',
            'amount' => 100.00,
            'message' => 'invoice #257',
        ]);

        $ranked = $this->matcher->rank($notice, collect([$paid]));
        $this->assertNotEmpty($ranked);
        $this->assertSame('INV-2026-0257', $ranked[0]['invoice_number']);
        $this->assertSame(EtransferInvoiceMatcher::CITATION_SHORT, $ranked[0]['citation']);
        $this->assertContains('Name similar', $ranked[0]['reasons']);
        $this->assertNotContains('Amount matches', $ranked[0]['reasons']);
    }

    public function test_phone_masking_stops_invoice_keyword_from_eating_phone_digits(): void
    {
        $invoice = $this->makeInvoice(647, 'INV-2026-0647', 900.00, 'Other Client', 'other@example.test');
        $memo = 'please call inv 647-834-0258';

        $this->assertFalse($this->matcher->shortInvoiceSequenceAppears('INV-2026-0647', $memo));
        $this->assertFalse($this->matcher->invoiceNumberAppears('INV-2026-0647', $memo));
        $this->assertStringNotContainsString('647', $this->matcher->maskPhoneNumbers($memo));

        $notice = new EtransferNotice([
            'sender_name' => 'Unrelated Person',
            'amount' => 12.00,
            'message' => $memo,
        ]);
        $this->assertSame([], $this->matcher->rank($notice, collect([$invoice])));
    }

    public function test_old_paid_loose_hash_does_not_hide_amount_match(): void
    {
        $paid = $this->makeInvoice(305, 'INV-2025-0305', 900.00, 'Other Client', 'other@example.test');
        $paid->status = ClientProjectInvoice::STATUS_PAID;
        $malcolm = $this->makeInvoice(258, 'INV-2026-0258', 379.61, 'Malcolm Travis', 'malcolm@example.test');

        $notice = new EtransferNotice([
            'sender_name' => 'Malcolm Travis',
            'amount' => 379.61,
            'message' => 'unit #305',
        ]);

        $suggestion = $this->matcher->suggest($notice, collect([$paid, $malcolm]));
        $this->assertSame('INV-2026-0258', $suggestion['best']['invoice_number']);
        $this->assertFalse($suggestion['already_paid']);
        $this->assertTrue($suggestion['recordable']);
    }

    public function test_two_open_invoices_cited_in_memo_are_ambiguous(): void
    {
        $one = $this->makeInvoice(257, 'INV-2026-0257', 500.00, 'Zohar Barak', 'zohar@example.test');
        $two = $this->makeInvoice(258, 'INV-2026-0258', 379.61, 'Malcolm Travis', 'malcolm@example.test');

        $notice = new EtransferNotice([
            'sender_name' => 'Someone Else',
            'amount' => 379.61,
            'message' => 'INV-2026-0257 and INV-2026-0258',
        ]);

        $suggestion = $this->matcher->suggest($notice, collect([$one, $two]));
        $this->assertTrue($suggestion['ambiguous']);
        $this->assertSame('cited', $suggestion['ambiguous_reason']);
        $this->assertNull($suggestion['best']);
        $this->assertFalse($suggestion['recordable']);
    }

    public function test_amount_tie_among_open_invoices_is_ambiguous(): void
    {
        $one = $this->makeInvoice(10, 'INV-2026-0010', 379.61, 'Alpha Client', 'alpha@example.test');
        $two = $this->makeInvoice(11, 'INV-2026-0011', 379.61, 'Beta Client', 'beta@example.test');

        $notice = new EtransferNotice([
            'sender_name' => 'Unknown Sender',
            'amount' => 379.61,
            'message' => 'thanks',
        ]);

        $suggestion = $this->matcher->suggest($notice, collect([$one, $two]));

        $this->assertTrue($suggestion['ambiguous']);
        $this->assertNull($suggestion['best']);
        $this->assertFalse($suggestion['recordable']);
        $this->assertCount(2, $suggestion['candidates']);
    }

    public function test_threshold_operator_matches_equal_and_rejects_strictly_below(): void
    {
        $this->assertSame(15, EtransferInvoiceMatcher::DEFAULT_MIN_MATCH_SCORE);
        $this->assertSame(15, $this->matcher->minMatchScore());
        $this->assertTrue($this->matcher->isConfidentScore(15));
        $this->assertFalse($this->matcher->isConfidentScore(14));

        $configSrc = file_get_contents(config_path('etransfer.php'));
        $this->assertNotFalse($configSrc);
        $this->assertStringContainsString("env('ETRANSFER_MIN_MATCH_SCORE', 15)", $configSrc);

        $recent = $this->makeInvoice(400, 'INV-2026-0400', 1800.00, 'Unrelated Person', 'unrelated@example.test');
        $notice = new EtransferNotice([
            'sender_name' => 'Someone Else',
            'sender_email' => 'nobody@example.test',
            'amount' => 55.55,
            'message' => 'thanks',
        ]);

        config(['etransfer.min_match_score' => 10]);
        $atBoundary = new EtransferInvoiceMatcher;
        $this->assertSame(10, $atBoundary->minMatchScore());
        $this->assertNotEmpty($atBoundary->rank($notice, collect([$recent])));

        config(['etransfer.min_match_score' => 11]);
        $aboveRecency = new EtransferInvoiceMatcher;
        $this->assertSame(11, $aboveRecency->minMatchScore());
        $this->assertSame([], $aboveRecency->rank($notice, collect([$recent])));
    }

    public function test_min_match_score_is_not_silently_clamped(): void
    {
        config(['etransfer.min_match_score' => 5]);
        $matcher = new EtransferInvoiceMatcher;
        $this->assertSame(5, $matcher->minMatchScore());

        config(['etransfer.min_match_score' => 0]);
        $invalid = new EtransferInvoiceMatcher;
        $this->assertSame(EtransferInvoiceMatcher::DEFAULT_MIN_MATCH_SCORE, $invalid->minMatchScore());
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

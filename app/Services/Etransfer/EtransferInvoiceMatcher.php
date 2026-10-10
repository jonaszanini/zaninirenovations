<?php

namespace App\Services\Etransfer;

use App\Models\ClientProject;
use App\Models\ClientProjectInvoice;
use App\Models\EtransferNotice;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

/**
 * Rank invoices against an incoming Interac notice.
 *
 * Scoring (highest first):
 *  - a full INV-YYYY-NNNN anywhere in the memo / raw text
 *  - a short number only directly after inv / invoice / inv# / invoice #
 *    (bare #NNN or a 4-digit token is not a citation; phone digits are ignored).
 *    A short-form hit also needs the same client or an amount match, and it
 *    never outranks an amount match.
 *  - amount equals the invoice outstanding total, or an unpaid deposit/instalment
 *  - sender email / name similar to the client
 *  - recency of the invoice
 *
 * Paid invoices are skipped unless the memo cites their number — then they
 * are kept and flagged "Already paid". A cited paid invoice ranks above an
 * unrelated open invoice, but an open invoice that is also cited and matches
 * the amount ranks higher than that paid invoice.
 *
 * A score strictly below the configured minimum is not a confident
 * suggestion. A score equal to the minimum is a match. Recency alone is 10;
 * the default minimum is 15. The minimum is not silently raised.
 */
class EtransferInvoiceMatcher
{
    public const MAX_RESULTS = 5;

    public const SCORE_INVOICE_NUMBER = 100;

    /** Below an outstanding-amount match so a short citation cannot outrank it. */
    public const SCORE_SHORT_INVOICE_NUMBER = 15;

    public const SCORE_AMOUNT_OUTSTANDING = 40;

    public const SCORE_AMOUNT_INSTALLMENT = 30;

    public const SCORE_EMAIL = 25;

    public const SCORE_NAME_MAX = 20;

    public const SCORE_RECENCY_MAX = 10;

    public const DEFAULT_MIN_MATCH_SCORE = 15;

    public const NAME_SIMILARITY_THRESHOLD = 0.55;

    public const REASON_ALREADY_PAID = 'Already paid';

    public const REASON_INVOICE_NUMBER = 'Invoice number';

    public const REASON_AMOUNT = 'Amount matches';

    public const REASON_DEPOSIT = 'Deposit amount';

    public const REASON_INSTALLMENT = 'Instalment amount';

    public const CITATION_FULL = 'full';

    public const CITATION_SHORT = 'short';

    /** Full INV-YYYY-NNNN (optional spaces/dashes). */
    public const FULL_INVOICE_NUMBER_PATTERN = '/\binv[-\s]?\d{4}[-\s]?\d+\b/iu';

    /** Short sequence only directly after inv / invoice / inv# / invoice #. */
    public const SHORT_INVOICE_CITATION_PATTERN = '/\b(?:invoice|inv)(?:\s*#|\s+|#)(\d{3,6})\b/iu';

    /** @var list<string> */
    public const AMOUNT_REASONS = [
        self::REASON_AMOUNT,
        self::REASON_DEPOSIT,
        self::REASON_INSTALLMENT,
    ];

    /**
     * @return list<array{
     *   invoice_id: int,
     *   invoice_number: string,
     *   project_id: int,
     *   project_name: string,
     *   client_name: string,
     *   amount: float,
     *   outstanding: float,
     *   status: string,
     *   score: int,
     *   already_paid: bool,
     *   citation: ?string,
     *   reasons: list<string>
     * }>
     */
    public function rank(EtransferNotice $notice, ?Collection $invoices = null): array
    {
        $invoices = $this->mergeMentionedInvoices($notice, $invoices ?? $this->openInvoices());
        $haystack = $this->haystack($notice);
        $amount = round((float) $notice->amount, 2);
        $senderName = trim((string) $notice->sender_name);
        $senderEmail = strtolower(trim((string) $notice->sender_email));
        $minScore = $this->minMatchScore();

        $ranked = [];

        foreach ($invoices as $invoice) {
            if (! $invoice instanceof ClientProjectInvoice) {
                continue;
            }
            if ((float) $invoice->total <= 0) {
                continue;
            }

            $project = $invoice->relationLoaded('project') ? $invoice->project : $invoice->project;
            if (! $project instanceof ClientProject) {
                continue;
            }

            $clientName = trim((string) ($project->invoiceBillToName() ?: $project->client_name));
            $alreadyPaid = $invoice->isPaid();
            $outstanding = $this->outstandingBalance($invoice);
            $amountMatched = ! $alreadyPaid && (
                $this->amountsMatch($amount, $outstanding)
                || $this->matchesUnpaidInstallment($project, $invoice, $amount)
            );
            $nameScore = $this->nameSimilarity($senderName, $clientName);
            $sameClient = $nameScore >= self::NAME_SIMILARITY_THRESHOLD;

            $fullCited = $this->fullInvoiceNumberAppears((string) $invoice->invoice_number, $haystack);
            $shortCited = ! $fullCited && $this->shortInvoiceSequenceAppears((string) $invoice->invoice_number, $haystack);
            $shortConfirmed = $shortCited && ($sameClient || $amountMatched);
            $mentioned = $fullCited || $shortConfirmed;

            if ($alreadyPaid && ! $mentioned) {
                continue;
            }

            $score = 0;
            $reasons = [];
            $citation = null;

            if ($alreadyPaid) {
                $reasons[] = self::REASON_ALREADY_PAID;
            }

            if ($fullCited) {
                $score += self::SCORE_INVOICE_NUMBER;
                $reasons[] = self::REASON_INVOICE_NUMBER;
                $citation = self::CITATION_FULL;
            } elseif ($shortConfirmed) {
                $score += self::SCORE_SHORT_INVOICE_NUMBER;
                $reasons[] = self::REASON_INVOICE_NUMBER;
                $citation = self::CITATION_SHORT;
            }

            if ($amountMatched && $this->amountsMatch($amount, $outstanding)) {
                $score += self::SCORE_AMOUNT_OUTSTANDING;
                $reasons[] = self::REASON_AMOUNT;
            } elseif ($amountMatched) {
                $score += self::SCORE_AMOUNT_INSTALLMENT;
                $reasons[] = $invoice->installment_number === 1 ? self::REASON_DEPOSIT : self::REASON_INSTALLMENT;
            }

            $emails = $this->projectEmails($project);
            if ($senderEmail !== '' && in_array($senderEmail, $emails, true)) {
                $score += self::SCORE_EMAIL;
                $reasons[] = 'Sender email';
            }

            if ($sameClient) {
                $score += (int) round(self::SCORE_NAME_MAX * $nameScore);
                $reasons[] = 'Name similar';
            }

            $recency = $this->recencyScore($invoice);
            if ($recency > 0) {
                $score += $recency;
                if ($recency >= 6) {
                    $reasons[] = 'Recent invoice';
                }
            }

            if ($score <= 0 && ! $alreadyPaid) {
                continue;
            }

            if (! $alreadyPaid && ! $this->isConfidentScore($score, $minScore)) {
                continue;
            }

            $ranked[] = [
                'invoice_id' => (int) $invoice->id,
                'invoice_number' => (string) $invoice->invoice_number,
                'project_id' => (int) $project->id,
                'project_name' => (string) $project->name,
                'client_name' => $clientName !== '' ? $clientName : (string) $project->client_name,
                'amount' => round((float) $invoice->total, 2),
                'outstanding' => $outstanding,
                'status' => (string) $invoice->status,
                'score' => $score,
                'already_paid' => $alreadyPaid,
                'citation' => $citation,
                'reasons' => $reasons,
            ];
        }

        usort($ranked, function (array $a, array $b): int {
            if ($a['score'] !== $b['score']) {
                return $b['score'] <=> $a['score'];
            }
            if ($a['already_paid'] !== $b['already_paid']) {
                return $a['already_paid'] <=> $b['already_paid'];
            }

            return $a['invoice_number'] <=> $b['invoice_number'];
        });

        return array_slice($ranked, 0, self::MAX_RESULTS);
    }

    /**
     * Decide what the admin card should preselect.
     *
     * @param  list<array<string, mixed>>  $ranked
     * @return array{
     *   best: ?array<string, mixed>,
     *   recordable: bool,
     *   ambiguous: bool,
     *   already_paid: bool,
     *   ambiguous_reason: ?string,
     *   ambiguous_labels: list<array{invoice_number: string, client_name: string}>
     * }
     */
    public function interpret(array $ranked): array
    {
        $open = array_values(array_filter($ranked, fn (array $row) => empty($row['already_paid'])));
        $openFull = array_values(array_filter($open, fn (array $row) => ($row['citation'] ?? null) === self::CITATION_FULL));
        $openShort = array_values(array_filter($open, fn (array $row) => ($row['citation'] ?? null) === self::CITATION_SHORT));
        $amountOpen = array_values(array_filter($open, fn (array $row) => $this->hasAmountReason($row)));
        $citedOpen = array_values(array_filter($open, fn (array $row) => ($row['citation'] ?? null) !== null));
        $identityOpen = array_values(array_filter($open, fn (array $row) => $this->hasIdentityReason($row)));
        $citedPaid = array_values(array_filter(
            $ranked,
            fn (array $row) => ! empty($row['already_paid']) && ($row['citation'] ?? null) === self::CITATION_FULL
        ));
        $shortPaid = array_values(array_filter(
            $ranked,
            fn (array $row) => ! empty($row['already_paid']) && ($row['citation'] ?? null) === self::CITATION_SHORT
        ));

        if (count($openFull) > 1) {
            return $this->decision(null, false, true, 'cited');
        }

        $conflict = $this->citationIdentityConflict($citedOpen, $identityOpen);
        if ($conflict !== null) {
            return $this->decision(null, false, true, 'conflict', $conflict);
        }

        if (count($openFull) === 1) {
            return $this->decision($openFull[0], true, false);
        }

        if ($citedPaid !== []) {
            return $this->decision($citedPaid[0], false, false);
        }

        if (count($amountOpen) >= 2) {
            return $this->decision(null, false, true, 'amount');
        }

        if (count($amountOpen) === 1) {
            return $this->decision($amountOpen[0], true, false);
        }

        if (count($openShort) > 1) {
            return $this->decision(null, false, true, 'cited');
        }

        if (count($openShort) === 1) {
            return $this->decision($openShort[0], true, false);
        }

        if ($open !== []) {
            return $this->decision($open[0], true, false);
        }

        if ($shortPaid !== []) {
            return $this->decision($shortPaid[0], false, false);
        }

        return $this->decision(null, false, false);
    }

    /**
     * @return array{
     *   candidates: list<array<string, mixed>>,
     *   best: ?array<string, mixed>,
     *   recordable: bool,
     *   ambiguous: bool,
     *   already_paid: bool,
     *   ambiguous_reason: ?string,
     *   ambiguous_labels: list<array{invoice_number: string, client_name: string}>
     * }
     */
    public function suggest(EtransferNotice $notice, ?Collection $invoices = null): array
    {
        $candidates = $this->rank($notice, $invoices);

        return $this->interpret($candidates) + ['candidates' => $candidates];
    }

    /**
     * Score equal to the minimum is a match; strictly below is not.
     */
    public function isConfidentScore(int $score, ?int $minScore = null): bool
    {
        return $score >= ($minScore ?? $this->minMatchScore());
    }

    /**
     * Configured minimum. Invalid values fall back to {@see DEFAULT_MIN_MATCH_SCORE}.
     * A valid low value (for example 5) is kept as-is — it is not raised to 11.
     */
    public function minMatchScore(): int
    {
        $configured = config('etransfer.min_match_score', self::DEFAULT_MIN_MATCH_SCORE);
        if (is_int($configured) || (is_string($configured) && is_numeric(trim($configured)))) {
            $score = (int) $configured;
            if ($score >= 1) {
                return $score;
            }
        }

        return self::DEFAULT_MIN_MATCH_SCORE;
    }

    /**
     * Unpaid invoices that can still receive a payment (draft or sent).
     *
     * @return Collection<int, ClientProjectInvoice>
     */
    public function openInvoices(): Collection
    {
        return ClientProjectInvoice::query()
            ->with(['project.client', 'project.installments'])
            ->whereIn('status', [
                ClientProjectInvoice::STATUS_DRAFT,
                ClientProjectInvoice::STATUS_SENT,
            ])
            ->where('total', '>', 0)
            ->orderByDesc('sent_at')
            ->orderByDesc('id')
            ->limit(400)
            ->get();
    }

    public function outstandingBalance(ClientProjectInvoice $invoice): float
    {
        if ($invoice->isPaid()) {
            return 0.0;
        }

        return round((float) $invoice->total, 2);
    }

    public function nameSimilarity(string $a, string $b): float
    {
        $left = $this->normalizeName($a);
        $right = $this->normalizeName($b);
        if ($left === '' || $right === '') {
            return 0.0;
        }
        if ($left === $right) {
            return 1.0;
        }

        similar_text($left, $right, $percent);
        $ratio = $percent / 100;

        $leftParts = preg_split('/\s+/', $left) ?: [];
        $rightParts = preg_split('/\s+/', $right) ?: [];
        $lastLeft = end($leftParts) ?: '';
        $lastRight = end($rightParts) ?: '';
        if ($lastLeft !== '' && $lastLeft === $lastRight && mb_strlen($lastLeft) >= 3) {
            $ratio = max($ratio, 0.72);
        }

        return round($ratio, 3);
    }

    /**
     * @param  array<string, mixed>|null  $best
     * @param  list<array<string, mixed>>  $ambiguousPair
     * @return array{
     *   best: ?array<string, mixed>,
     *   recordable: bool,
     *   ambiguous: bool,
     *   already_paid: bool,
     *   ambiguous_reason: ?string,
     *   ambiguous_labels: list<array{invoice_number: string, client_name: string}>
     * }
     */
    private function decision(?array $best, bool $recordable, bool $ambiguous, ?string $ambiguousReason = null, array $ambiguousPair = []): array
    {
        $labels = [];
        foreach ($ambiguousPair as $row) {
            $labels[] = [
                'invoice_number' => (string) ($row['invoice_number'] ?? ''),
                'client_name' => (string) ($row['client_name'] ?? ''),
            ];
        }

        return [
            'best' => $best,
            'recordable' => $recordable,
            'ambiguous' => $ambiguous,
            'already_paid' => $best !== null && ! empty($best['already_paid']),
            'ambiguous_reason' => $ambiguous ? $ambiguousReason : null,
            'ambiguous_labels' => $ambiguous ? $labels : [],
        ];
    }

    /**
     * Cited open invoice for one client vs amount/name match for another.
     *
     * @param  list<array<string, mixed>>  $citedOpen
     * @param  list<array<string, mixed>>  $identityOpen
     * @return list<array<string, mixed>>|null
     */
    private function citationIdentityConflict(array $citedOpen, array $identityOpen): ?array
    {
        foreach ($citedOpen as $cited) {
            foreach ($identityOpen as $identity) {
                if ((int) ($cited['invoice_id'] ?? 0) === (int) ($identity['invoice_id'] ?? 0)) {
                    continue;
                }
                if ($this->sameClientRow($cited, $identity)) {
                    continue;
                }

                return [$cited, $identity];
            }
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $a
     * @param  array<string, mixed>  $b
     */
    private function sameClientRow(array $a, array $b): bool
    {
        $left = $this->normalizeName((string) ($a['client_name'] ?? ''));
        $right = $this->normalizeName((string) ($b['client_name'] ?? ''));

        return $left !== '' && $left === $right;
    }

    /**
     * @param  array<string, mixed>  $row
     */
    private function hasIdentityReason(array $row): bool
    {
        if ($this->hasAmountReason($row)) {
            return true;
        }

        return $this->hasReason($row, 'Name similar') || $this->hasReason($row, 'Sender email');
    }

    /**
     * @param  array<string, mixed>  $row
     */
    private function hasReason(array $row, string $reason): bool
    {
        $reasons = $row['reasons'] ?? [];

        return is_array($reasons) && in_array($reason, $reasons, true);
    }

    /**
     * @param  array<string, mixed>  $row
     */
    private function hasAmountReason(array $row): bool
    {
        foreach (self::AMOUNT_REASONS as $reason) {
            if ($this->hasReason($row, $reason)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Pull invoices cited in the memo (including paid) into the candidate set.
     *
     * @param  Collection<int, mixed>  $invoices
     * @return Collection<int, mixed>
     */
    private function mergeMentionedInvoices(EtransferNotice $notice, Collection $invoices): Collection
    {
        $haystack = $this->haystack($notice);
        $tokens = $this->mentionLookupTokens($haystack);
        if ($tokens === []) {
            return $invoices;
        }

        $hasPersisted = $invoices->contains(
            fn ($invoice) => $invoice instanceof ClientProjectInvoice && $invoice->exists
        );
        if ($invoices->isNotEmpty() && ! $hasPersisted) {
            return $invoices;
        }

        $presentIds = [];
        foreach ($invoices as $invoice) {
            if ($invoice instanceof ClientProjectInvoice && $invoice->id) {
                $presentIds[(int) $invoice->id] = true;
            }
        }

        try {
            $found = ClientProjectInvoice::query()
                ->with(['project.client', 'project.installments'])
                ->where('total', '>', 0)
                ->where(function ($query) use ($tokens) {
                    foreach ($tokens as $token) {
                        $query->orWhere('invoice_number', 'like', '%'.$token.'%');
                    }
                })
                ->orderByDesc('id')
                ->limit(80)
                ->get();
        } catch (\Throwable $e) {
            Log::warning('Could not load invoices cited in an e-transfer memo.', [
                'exception' => $e,
                'tokens' => $tokens,
            ]);

            return $invoices;
        }

        $extra = $found->filter(function ($invoice) use ($haystack, $presentIds) {
            if (! $invoice instanceof ClientProjectInvoice) {
                return false;
            }
            if (isset($presentIds[(int) $invoice->id])) {
                return false;
            }

            return $this->invoiceNumberAppears((string) $invoice->invoice_number, $haystack);
        });

        return $invoices->concat($extra)->values();
    }

    /**
     * Tokens used for the cited-invoice LIKE lookup. Phone digits are masked
     * first so a number like 647-834-0258 never becomes a lookup token.
     *
     * @return list<string>
     */
    public function mentionLookupTokens(string $haystack): array
    {
        if ($haystack === '') {
            return [];
        }

        $tokens = [];
        $safe = $this->maskPhoneNumbers($haystack);

        if (preg_match_all(self::FULL_INVOICE_NUMBER_PATTERN, $safe, $matches)) {
            foreach ($matches[0] as $raw) {
                $tokens[] = strtoupper(preg_replace('/\s+/u', '-', trim($raw)) ?? trim($raw));
            }
        }

        if (preg_match_all(self::SHORT_INVOICE_CITATION_PATTERN, $safe, $matches)) {
            foreach ($matches[1] as $digits) {
                $tokens[] = $digits;
                $tokens[] = ltrim($digits, '0') ?: $digits;
            }
        }

        $unique = [];
        foreach ($tokens as $token) {
            $token = preg_replace('/[^A-Za-z0-9\-]/', '', (string) $token) ?? '';
            if ($token !== '') {
                $unique[$token] = $token;
            }
        }

        return array_values($unique);
    }

    private function haystack(EtransferNotice $notice): string
    {
        return mb_strtolower(trim(
            (string) $notice->message."\n".(string) $notice->raw_text
        ));
    }

    public function invoiceNumberAppears(string $invoiceNumber, string $haystack): bool
    {
        return $this->fullInvoiceNumberAppears($invoiceNumber, $haystack)
            || $this->shortInvoiceSequenceAppears($invoiceNumber, $haystack);
    }

    public function fullInvoiceNumberAppears(string $invoiceNumber, string $haystack): bool
    {
        $invoiceNumber = trim($invoiceNumber);
        if ($invoiceNumber === '' || $haystack === '') {
            return false;
        }

        $haystack = mb_strtolower($haystack);
        if (str_contains($haystack, mb_strtolower($invoiceNumber))) {
            return true;
        }

        $want = $this->compactInvoiceNumber($invoiceNumber);
        if ($want === '' || ! preg_match_all(self::FULL_INVOICE_NUMBER_PATTERN, $haystack, $matches)) {
            return false;
        }

        foreach ($matches[0] as $raw) {
            if ($this->compactInvoiceNumber($raw) === $want) {
                return true;
            }
        }

        return false;
    }

    public function shortInvoiceSequenceAppears(string $invoiceNumber, string $haystack): bool
    {
        $sequence = $this->invoiceSequence($invoiceNumber);
        if ($sequence === '' || $haystack === '') {
            return false;
        }

        $unpadded = ltrim($sequence, '0');
        if ($unpadded === '') {
            $unpadded = '0';
        }

        $safe = $this->maskPhoneNumbers(mb_strtolower($haystack));
        if (! preg_match_all(self::SHORT_INVOICE_CITATION_PATTERN, $safe, $matches)) {
            return false;
        }

        foreach ($matches[1] as $digits) {
            $got = ltrim((string) $digits, '0');
            if ($got === '') {
                $got = '0';
            }
            if ($got === $unpadded) {
                return true;
            }
        }

        return false;
    }

    public function maskPhoneNumbers(string $text): string
    {
        $patterns = [
            '/\+?1?[\s.\-]*(?:\(?\d{3}\)?[\s.\-]*)\d{3}[\s.\-]*\d{4}\b/u',
            '/\b\d{10,11}\b/u',
        ];

        foreach ($patterns as $pattern) {
            $text = preg_replace($pattern, ' ', $text) ?? $text;
        }

        return $text;
    }

    private function invoiceSequence(string $invoiceNumber): string
    {
        if (preg_match('/(\d+)$/', trim($invoiceNumber), $m)) {
            return $m[1];
        }

        return '';
    }

    private function compactInvoiceNumber(string $invoiceNumber): string
    {
        return preg_replace('/[\s\-]+/u', '', mb_strtolower(trim($invoiceNumber))) ?? '';
    }

    private function matchesUnpaidInstallment(ClientProject $project, ClientProjectInvoice $invoice, float $amount): bool
    {
        $installments = $project->relationLoaded('installments')
            ? $project->installments
            : $project->installments()->orderBy('sort_order')->get();

        if ($installments->isEmpty()) {
            return false;
        }

        if ($invoice->isManual()) {
            foreach ($installments as $row) {
                if ($row->is_paid) {
                    continue;
                }
                if ($this->amountsMatch($amount, $row->effectiveTotal()) || $this->amountsMatch($amount, (float) $row->amount)) {
                    return true;
                }
            }

            return false;
        }

        $linked = $installments->values()->get(((int) $invoice->installment_number) - 1);
        if (! $linked || $linked->is_paid) {
            return false;
        }

        return $this->amountsMatch($amount, $linked->effectiveTotal())
            || $this->amountsMatch($amount, (float) $linked->amount);
    }

    /**
     * @return list<string>
     */
    private function projectEmails(ClientProject $project): array
    {
        $emails = [
            strtolower(trim((string) $project->client_email)),
            strtolower(trim((string) $project->client_email_secondary)),
        ];

        $client = $project->relationLoaded('client') ? $project->client : null;
        if ($client) {
            $emails[] = strtolower(trim((string) $client->email));
            $emails[] = strtolower(trim((string) $client->email_secondary));
        }

        return array_values(array_filter($emails, fn (string $e) => $e !== '' && filter_var($e, FILTER_VALIDATE_EMAIL)));
    }

    private function recencyScore(ClientProjectInvoice $invoice): int
    {
        $anchor = $invoice->sent_at ?? $invoice->created_at;
        if (! $anchor) {
            return 0;
        }

        $days = (int) now()->diffInDays($anchor, false);
        $age = abs($days);
        if ($age <= 14) {
            return self::SCORE_RECENCY_MAX;
        }
        if ($age >= 120) {
            return 0;
        }

        return (int) round(self::SCORE_RECENCY_MAX * (1 - (($age - 14) / 106)));
    }

    private function amountsMatch(float $a, float $b): bool
    {
        return abs(round($a, 2) - round($b, 2)) < 0.015;
    }

    private function normalizeName(string $name): string
    {
        $converted = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $name);
        $name = is_string($converted) ? $converted : $name;
        $name = strtolower($name);
        $name = preg_replace('/[^a-z0-9\s]/', ' ', $name) ?? $name;
        $name = preg_replace('/\s+/', ' ', $name) ?? $name;

        return trim($name);
    }
}

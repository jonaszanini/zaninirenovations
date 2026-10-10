<?php

namespace App\Services\Etransfer;

use App\Models\ClientProject;
use App\Models\ClientProjectInvoice;
use App\Models\EtransferNotice;
use Illuminate\Support\Collection;

/**
 * Rank invoices against an incoming Interac notice.
 *
 * Scoring (highest first):
 *  - invoice number appears in the memo / raw text
 *  - amount equals the invoice outstanding total, or an unpaid deposit/instalment
 *  - sender email / name similar to the client
 *  - recency of the invoice
 *
 * Paid invoices are skipped unless the memo cites their number — then they
 * are kept, flagged "Already paid", and ranked first so we do not suggest
 * an unrelated open invoice.
 *
 * A score at or below the configured minimum (recency alone is 10) is not
 * a confident suggestion.
 */
class EtransferInvoiceMatcher
{
    public const MAX_RESULTS = 5;

    public const SCORE_INVOICE_NUMBER = 100;

    public const SCORE_AMOUNT_OUTSTANDING = 40;

    public const SCORE_AMOUNT_INSTALLMENT = 30;

    public const SCORE_EMAIL = 25;

    public const SCORE_NAME_MAX = 20;

    public const SCORE_RECENCY_MAX = 10;

    public const DEFAULT_MIN_MATCH_SCORE = 15;

    public const NAME_SIMILARITY_THRESHOLD = 0.55;

    public const REASON_ALREADY_PAID = 'Already paid';

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

            $mentioned = $this->invoiceNumberAppears((string) $invoice->invoice_number, $haystack);
            $alreadyPaid = $invoice->isPaid();
            if ($alreadyPaid && ! $mentioned) {
                continue;
            }

            $project = $invoice->relationLoaded('project') ? $invoice->project : $invoice->project;
            if (! $project instanceof ClientProject) {
                continue;
            }

            $score = 0;
            $reasons = [];

            if ($alreadyPaid) {
                $reasons[] = self::REASON_ALREADY_PAID;
            }

            if ($mentioned) {
                $score += self::SCORE_INVOICE_NUMBER;
                $reasons[] = 'Invoice number';
            }

            $outstanding = $this->outstandingBalance($invoice);
            if (! $alreadyPaid && $this->amountsMatch($amount, $outstanding)) {
                $score += self::SCORE_AMOUNT_OUTSTANDING;
                $reasons[] = 'Amount matches';
            } elseif (! $alreadyPaid && $this->matchesUnpaidInstallment($project, $invoice, $amount)) {
                $score += self::SCORE_AMOUNT_INSTALLMENT;
                $reasons[] = $invoice->installment_number === 1 ? 'Deposit amount' : 'Instalment amount';
            }

            $emails = $this->projectEmails($project);
            if ($senderEmail !== '' && in_array($senderEmail, $emails, true)) {
                $score += self::SCORE_EMAIL;
                $reasons[] = 'Sender email';
            }

            $clientName = trim((string) ($project->invoiceBillToName() ?: $project->client_name));
            $nameScore = $this->nameSimilarity($senderName, $clientName);
            if ($nameScore >= self::NAME_SIMILARITY_THRESHOLD) {
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

            if (! $alreadyPaid && $score < $minScore) {
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
                'reasons' => $reasons,
            ];
        }

        usort($ranked, function (array $a, array $b): int {
            if ($a['already_paid'] !== $b['already_paid']) {
                return $b['already_paid'] <=> $a['already_paid'];
            }
            if ($a['score'] !== $b['score']) {
                return $b['score'] <=> $a['score'];
            }

            return $a['invoice_number'] <=> $b['invoice_number'];
        });

        return array_slice($ranked, 0, self::MAX_RESULTS);
    }

    /**
     * Lowest score that may be offered as a suggestion. Recency alone (10)
     * must stay below this so a recent unrelated invoice is never "best".
     */
    public function minMatchScore(): int
    {
        $configured = config('etransfer.min_match_score', self::DEFAULT_MIN_MATCH_SCORE);
        $score = is_numeric($configured) ? (int) $configured : self::DEFAULT_MIN_MATCH_SCORE;

        return max(self::SCORE_RECENCY_MAX + 1, $score);
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
     * Pull invoices cited in the memo (including paid) into the candidate set.
     *
     * @param  Collection<int, mixed>  $invoices
     * @return Collection<int, mixed>
     */
    private function mergeMentionedInvoices(EtransferNotice $notice, Collection $invoices): Collection
    {
        $mentioned = $this->extractInvoiceNumbers($this->haystack($notice));
        if ($mentioned === []) {
            return $invoices;
        }

        $present = [];
        foreach ($invoices as $invoice) {
            if (! $invoice instanceof ClientProjectInvoice) {
                continue;
            }
            $present[$this->compactInvoiceNumber((string) $invoice->invoice_number)] = true;
        }

        $missing = [];
        foreach ($mentioned as $number) {
            $compact = $this->compactInvoiceNumber($number);
            if ($compact !== '' && ! isset($present[$compact])) {
                $missing[] = $number;
            }
        }

        if ($missing === []) {
            return $invoices;
        }

        try {
            $found = ClientProjectInvoice::query()
                ->with(['project.client', 'project.installments'])
                ->where(function ($query) use ($missing) {
                    foreach ($missing as $number) {
                        $compact = $this->compactInvoiceNumber($number);
                        $query->orWhere('invoice_number', $number);
                        if ($compact !== '') {
                            $query->orWhereRaw('LOWER(REPLACE(invoice_number, "-", "")) = ?', [$compact]);
                        }
                    }
                })
                ->get();
        } catch (\Throwable) {
            return $invoices;
        }

        return $invoices->concat($found)->unique(fn ($invoice) => $invoice->id ?? spl_object_id($invoice))->values();
    }

    /**
     * @return list<string>
     */
    private function extractInvoiceNumbers(string $haystack): array
    {
        if ($haystack === '') {
            return [];
        }

        if (! preg_match_all('/\bINV[-\s]?\d{4}[-\s]?\d+\b/iu', $haystack, $matches)) {
            return [];
        }

        $numbers = [];
        foreach ($matches[0] as $raw) {
            $normalized = strtoupper(preg_replace('/\s+/u', '-', trim($raw)) ?? trim($raw));
            if ($normalized !== '') {
                $numbers[$this->compactInvoiceNumber($normalized)] = $normalized;
            }
        }

        return array_values($numbers);
    }

    private function compactInvoiceNumber(string $invoiceNumber): string
    {
        return preg_replace('/[\s\-]+/u', '', mb_strtolower(trim($invoiceNumber))) ?? '';
    }

    private function haystack(EtransferNotice $notice): string
    {
        return mb_strtolower(trim(
            (string) $notice->message."\n".(string) $notice->raw_text
        ));
    }

    private function invoiceNumberAppears(string $invoiceNumber, string $haystack): bool
    {
        $invoiceNumber = trim($invoiceNumber);
        if ($invoiceNumber === '' || $haystack === '') {
            return false;
        }

        $compactHay = $this->compactInvoiceNumber($haystack);
        $compactInv = $this->compactInvoiceNumber($invoiceNumber);

        return str_contains(mb_strtolower($haystack), mb_strtolower($invoiceNumber))
            || ($compactInv !== '' && str_contains($compactHay, $compactInv));
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

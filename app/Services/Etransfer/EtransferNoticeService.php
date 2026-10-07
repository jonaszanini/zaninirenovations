<?php

namespace App\Services\Etransfer;

use App\Models\ClientProjectInvoice;
use App\Models\EtransferNotice;
use App\Services\Invoice\MarkInvoicePaidService;
use App\Support\CadMoney;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class EtransferNoticeService
{
    public function __construct(
        protected EtransferNoticeParser $parser,
        protected EtransferInvoiceMatcher $matcher,
        protected MarkInvoicePaidService $markPaid,
    ) {
    }

    /**
     * @param  array<string, mixed>  $input
     * @return array{status: string, notice: EtransferNotice, candidates: list<array<string, mixed>>}
     */
    public function ingest(array $input, string $source): array
    {
        $rawText = isset($input['raw_text']) ? trim((string) $input['raw_text']) : '';
        $parsed = $rawText !== '' ? $this->parser->parse($rawText) : [
            'sender_name' => null,
            'sender_email' => null,
            'amount' => null,
            'currency' => EtransferNotice::CURRENCY_CAD,
            'reference_number' => null,
            'message' => null,
            'received_at' => null,
        ];

        $senderName = $this->firstFilled($input['sender_name'] ?? null, $parsed['sender_name'] ?? null) ?? 'Unknown sender';
        $senderEmail = $this->firstFilled($input['sender_email'] ?? null, $parsed['sender_email'] ?? null);
        $reference = $this->firstFilled($input['reference'] ?? null, $input['reference_number'] ?? null, $parsed['reference_number'] ?? null);
        $message = $this->firstFilled($input['message'] ?? null, $parsed['message'] ?? null);
        $currency = strtoupper($this->firstFilled($input['currency'] ?? null, $parsed['currency'] ?? null) ?? EtransferNotice::CURRENCY_CAD);
        if ($currency === '') {
            $currency = EtransferNotice::CURRENCY_CAD;
        }

        $rawAmount = $input['amount'] ?? null;
        if (is_string($rawAmount)) {
            $rawAmount = str_replace(['$', 'CAD', 'CDN', 'CA'], '', $rawAmount);
        }
        $amount = CadMoney::parse($rawAmount);
        if ($amount === null || $amount <= 0) {
            $amount = $parsed['amount'] ?? null;
        }

        $receivedAt = $this->parseReceivedAt($input['received_at'] ?? null) ?? $parsed['received_at'] ?? now();

        if ($amount === null || $amount <= 0) {
            throw ValidationException::withMessages([
                'amount' => 'Could not determine the e-transfer amount. Include amount in the email or send it as a field.',
            ]);
        }

        if (is_string($reference)) {
            $reference = trim($reference);
            $reference = $reference === '' ? null : $reference;
        }

        if ($reference !== null) {
            $existing = EtransferNotice::query()->where('reference_number', $reference)->first();
            if ($existing) {
                $candidates = $existing->isPending()
                    ? $this->matcher->rank($existing)
                    : [];

                return [
                    'status' => 'duplicate',
                    'notice' => $existing,
                    'candidates' => $candidates,
                ];
            }
        }

        $notice = new EtransferNotice([
            'received_at' => $receivedAt,
            'sender_name' => $senderName,
            'sender_email' => $senderEmail,
            'amount' => round((float) $amount, 2),
            'currency' => mb_substr($currency, 0, 3),
            'reference_number' => $reference,
            'message' => $message,
            'raw_text' => $rawText !== '' ? $rawText : null,
            'source' => $source,
            'status' => EtransferNotice::STATUS_PENDING,
        ]);

        $candidates = $this->matcher->rank($notice);
        $notice->match_score = $candidates[0]['score'] ?? null;
        $notice->save();

        return [
            'status' => 'created',
            'notice' => $notice,
            'candidates' => $candidates,
        ];
    }

    /**
     * Record the notice as a normal invoice payment (e-Transfer) and mark it matched.
     *
     * @return array{ok: bool, error?: string, flash?: string, warning?: string|null}
     */
    public function recordPayment(EtransferNotice $notice, ClientProjectInvoice $invoice): array
    {
        if (! $notice->isPending()) {
            return ['ok' => false, 'error' => 'This e-transfer has already been processed.'];
        }

        $project = $invoice->project;
        if (! $project) {
            return ['ok' => false, 'error' => 'That invoice has no project.'];
        }

        $paidOn = $notice->received_at
            ? $notice->received_at->toDateString()
            : now()->toDateString();

        $extra = 'Recorded from incoming e-transfer';
        if (filled($notice->reference_number)) {
            $extra .= ' (ref '.$notice->reference_number.')';
        }
        $extra .= '.';

        $result = null;

        DB::transaction(function () use ($notice, $invoice, $project, $paidOn, $extra, &$result) {
            $locked = EtransferNotice::query()->whereKey($notice->id)->lockForUpdate()->first();
            if (! $locked || ! $locked->isPending()) {
                $result = ['ok' => false, 'error' => 'This e-transfer has already been processed.'];

                return;
            }

            $result = $this->markPaid->mark(
                $project,
                $invoice,
                $paidOn,
                round((float) $notice->amount, 2),
                ClientProjectInvoice::PAYMENT_METHOD_ETRANSFER,
                false,
                false,
                null,
                $extra,
            );

            if (! ($result['ok'] ?? false)) {
                return;
            }

            $invoice->refresh();
            $locked->status = EtransferNotice::STATUS_MATCHED;
            $locked->invoice_id = $invoice->id;
            $locked->payment_id = $invoice->id;
            $locked->save();
        });

        if ($result['ok'] ?? false) {
            $notice->refresh();
        }

        return $result ?? ['ok' => false, 'error' => 'Could not record the payment.'];
    }

    public function ignore(EtransferNotice $notice): void
    {
        if (! $notice->isPending()) {
            return;
        }

        $notice->status = EtransferNotice::STATUS_IGNORED;
        $notice->save();
    }

    /**
     * @return array<string, mixed>
     */
    public function serializeNotice(EtransferNotice $notice): array
    {
        return [
            'id' => (int) $notice->id,
            'received_at' => optional($notice->received_at)?->toIso8601String(),
            'sender_name' => $notice->sender_name,
            'sender_email' => $notice->sender_email,
            'amount' => round((float) $notice->amount, 2),
            'currency' => $notice->currency,
            'reference' => $notice->reference_number,
            'message' => $notice->message,
            'source' => $notice->source,
            'status' => $notice->status,
            'invoice_id' => $notice->invoice_id,
            'payment_id' => $notice->payment_id,
            'match_score' => $notice->match_score,
        ];
    }

    private function firstFilled(mixed ...$values): ?string
    {
        foreach ($values as $value) {
            if ($value === null) {
                continue;
            }
            $trimmed = trim((string) $value);
            if ($trimmed !== '') {
                return $trimmed;
            }
        }

        return null;
    }

    private function parseReceivedAt(mixed $value): ?Carbon
    {
        if ($value === null || $value === '') {
            return null;
        }

        try {
            return Carbon::parse((string) $value, (string) config('app.timezone', 'America/Toronto'));
        } catch (\Throwable) {
            return null;
        }
    }
}

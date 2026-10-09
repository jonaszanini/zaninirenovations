<?php

namespace App\Models\Concerns;

use App\Models\ClientProjectQuotation;
use App\Support\QuotationReplyToAccept;
use Illuminate\Validation\ValidationException;

/**
 * Required Yes/No on calculator quotation save: ask the client to reply
 * "I accept" to the quotation email. No default — null until the admin picks.
 */
trait HasReplyToAcceptChoice
{
    public function initializeHasReplyToAcceptChoice(): void
    {
        $this->mergeFillable([QuotationReplyToAccept::FIELD]);
    }

    public static function bootHasReplyToAcceptChoice(): void
    {
        static::saving(function ($model): void {
            $request = request();
            $routeName = $request->route()?->getName();
            if (! QuotationReplyToAccept::isQuotationSaveRoute($routeName)) {
                return;
            }

            $parsed = QuotationReplyToAccept::parse($request->input(QuotationReplyToAccept::FIELD));
            if ($parsed === null && ! \App\Support\QuotationSaveIntent::isDraft($request)) {
                throw ValidationException::withMessages([
                    QuotationReplyToAccept::FIELD => QuotationReplyToAccept::MESSAGE,
                ]);
            }

            $model->setAttribute(QuotationReplyToAccept::FIELD, QuotationReplyToAccept::toStorage($parsed));
        });

        static::saved(function ($model): void {
            $type = ClientProjectQuotation::sourceTypeForModel($model);
            $id = (int) $model->getKey();
            if ($type === null || $id < 1) {
                return;
            }

            $cpq = ClientProjectQuotation::findForSource($type, $id);
            if ($cpq === null) {
                return;
            }

            $parsed = QuotationReplyToAccept::parse($model->getAttribute(QuotationReplyToAccept::FIELD));
            if (QuotationReplyToAccept::parse($cpq->ask_reply_to_accept) === $parsed) {
                return;
            }

            $cpq->ask_reply_to_accept = QuotationReplyToAccept::toStorage($parsed);
            $cpq->saveQuietly();
        });
    }

    public function asksClientToReplyIAccept(): bool
    {
        return QuotationReplyToAccept::parse($this->getAttribute(QuotationReplyToAccept::FIELD)) === true;
    }
}

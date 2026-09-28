<?php

namespace App\Models\Concerns;

use App\Support\QuotationClientEmailMessage;
use Illuminate\Validation\ValidationException;

/**
 * Adds the client email note to a quotation model.
 *
 * Every admin quotation form posts `client_email_message` via
 * admin.partials.quotation_client_email_message; this trait picks it up on
 * save so each controller does not need its own handling.
 */
trait HasClientEmailMessage
{
    public function initializeHasClientEmailMessage(): void
    {
        $this->mergeFillable([QuotationClientEmailMessage::FIELD]);
    }

    public static function bootHasClientEmailMessage(): void
    {
        static::saving(function ($model): void {
            if (app()->runningInConsole()) {
                return;
            }

            $request = request();
            if (! $request->routeIs('admin.quotations.*') || ! $request->exists(QuotationClientEmailMessage::FIELD)) {
                return;
            }

            $raw = (string) $request->input(QuotationClientEmailMessage::FIELD, '');
            if (mb_strlen($raw) > QuotationClientEmailMessage::MAX_LENGTH) {
                throw ValidationException::withMessages([
                    QuotationClientEmailMessage::FIELD => 'The client email message is too long.',
                ]);
            }

            $model->setAttribute(QuotationClientEmailMessage::FIELD, QuotationClientEmailMessage::normalize($raw));
        });
    }

    public function clientEmailMessageHtml(): ?string
    {
        return QuotationClientEmailMessage::forEmail($this->getAttribute(QuotationClientEmailMessage::FIELD));
    }
}

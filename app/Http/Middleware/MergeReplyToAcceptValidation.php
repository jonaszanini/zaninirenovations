<?php

namespace App\Http\Middleware;

use App\Support\QuotationReplyToAccept;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\ViewErrorBag;
use Illuminate\Validation\ValidationException;

/**
 * When a calculator quotation save fails other validation, still attach the
 * required Yes/No “reply I accept” reminder so it is not hidden behind
 * unrelated field errors.
 */
class MergeReplyToAcceptValidation
{
    public function handle(Request $request, Closure $next)
    {
        $routeName = $request->route()?->getName();
        if (! QuotationReplyToAccept::isQuotationSaveRoute($routeName)) {
            return $next($request);
        }

        $unset = QuotationReplyToAccept::parse($request->input(QuotationReplyToAccept::FIELD)) === null;

        try {
            $response = $next($request);
        } catch (ValidationException $e) {
            if ($unset && empty($e->errors()[QuotationReplyToAccept::FIELD])) {
                $messages = $e->errors();
                $messages[QuotationReplyToAccept::FIELD] = [QuotationReplyToAccept::MESSAGE];

                throw ValidationException::withMessages($messages);
            }

            throw $e;
        }

        if ($unset) {
            $this->mergeFlashedError($request);
        }

        return $response;
    }

    private function mergeFlashedError(Request $request): void
    {
        if (! $request->hasSession()) {
            return;
        }

        $errors = $request->session()->get('errors');
        if (! $errors instanceof ViewErrorBag || $errors->isEmpty()) {
            return;
        }

        if ($errors->has(QuotationReplyToAccept::FIELD)) {
            return;
        }

        $errors->getBag('default')->add(
            QuotationReplyToAccept::FIELD,
            QuotationReplyToAccept::MESSAGE
        );
    }
}

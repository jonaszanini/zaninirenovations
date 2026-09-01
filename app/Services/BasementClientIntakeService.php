<?php

namespace App\Services;

use App\Http\Controllers\Admin\BasementQuotationController;
use App\Models\BasementQuotation;
use InvalidArgumentException;

class BasementClientIntakeService
{
    public function __construct(private BasementClientIntakeMapper $mapper)
    {
    }

    /**
     * @param  array<string, mixed>  $answers
     */
    public function submit(BasementQuotation $quote, array $answers): BasementQuotation
    {
        if ($quote->isClientIntakeSubmitted()) {
            throw new InvalidArgumentException('This form has already been submitted.');
        }
        if (! $quote->supportsClientIntake()) {
            throw new InvalidArgumentException('This quotation cannot use the client intake form.');
        }

        $mapped = $this->mapper->apply($answers, is_array($quote->inputs) ? $quote->inputs : []);
        $inputs = $mapped['inputs'];
        $calc = (new BasementQuotationCalculator)->calculate($inputs);

        $quote->inputs = $inputs;
        $quote->calculation = $calc;
        $quote->final_quote_total = round((float) ($calc['summary']['final_quote_total'] ?? 0), 2);
        $quote->client_intake_answers = $answers;
        $quote->client_filled_keys = $mapped['filled_keys'];
        $quote->client_intake_submitted_at = now();
        $quote->version = (int) $quote->version + 1;
        $quote->save();

        if ($quote->isLinked() && $quote->project) {
            app(BasementQuotationController::class)->syncLinkedQuote($quote->project, $quote->fresh());
        }

        return $quote->fresh();
    }
}

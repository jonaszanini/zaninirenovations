<?php

namespace Tests\Unit\Support;

use App\Support\QuotationSaveIntent;
use Illuminate\Http\Request;
use Tests\TestCase;

class QuotationSaveIntentTest extends TestCase
{
    /**
     * @return array<string, array{0: array<string, mixed>, 1: string}>
     */
    public function requestCases(): array
    {
        return [
            'publish only' => [['save_intent' => 'publish'], QuotationSaveIntent::PUBLISH],
            'draft only' => [['save_intent' => 'draft'], QuotationSaveIntent::DRAFT],
            'publish plus leftover is_draft' => [
                ['save_intent' => 'publish', 'is_draft' => '1'],
                QuotationSaveIntent::PUBLISH,
            ],
            'draft plus is_draft=0' => [
                ['save_intent' => 'draft', 'is_draft' => '0'],
                QuotationSaveIntent::DRAFT,
            ],
            'empty save_intent is publish' => [
                ['save_intent' => '', 'is_draft' => '1'],
                QuotationSaveIntent::PUBLISH,
            ],
            'tile legacy is_draft=1' => [['is_draft' => '1'], QuotationSaveIntent::DRAFT],
            'tile legacy is_draft=0' => [['is_draft' => '0'], QuotationSaveIntent::PUBLISH],
            'no flags is publish' => [[], QuotationSaveIntent::PUBLISH],
        ];
    }

    /**
     * @dataProvider requestCases
     *
     * @param  array<string, mixed>  $input
     */
    public function test_from_request_is_the_canonical_intent(array $input, string $expected): void
    {
        $request = Request::create('/admin/quotations/example', 'POST', $input);

        $this->assertSame($expected, QuotationSaveIntent::fromRequest($request));
        $this->assertSame($expected === QuotationSaveIntent::DRAFT, QuotationSaveIntent::isDraft($request));
    }
}

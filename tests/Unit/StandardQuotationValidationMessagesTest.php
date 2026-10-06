<?php

namespace Tests\Unit;

use App\Support\StandardQuotationValidationMessages;
use Illuminate\Support\Facades\Validator;
use Tests\TestCase;

class StandardQuotationValidationMessagesTest extends TestCase
{
    public function test_long_material_description_names_the_job_and_material(): void
    {
        $works = [[
            'title' => 'Stairs change',
            'materials' => [[
                'short_name' => 'Oak tread',
                'description' => str_repeat('a', 501),
            ]],
        ]];

        $message = $this->descriptionError($works);

        $this->assertSame(
            'Under Materials on Job 1 (Stairs change) ("Oak tread"): the Description is 501 characters. Shorten that Description field to 500 characters or fewer, then save again.',
            $message
        );
        $this->assertStringNotContainsString('works.0.materials.0.description', $message);
    }

    public function test_long_description_without_a_short_name_quotes_the_start_of_the_text(): void
    {
        $works = [[
            'title' => '',
            'materials' => [[
                'short_name' => '',
                'description' => 'Baltic Birch Plywood for the stair landing. '.str_repeat('x', 470),
            ]],
        ]];

        $message = $this->descriptionError($works);

        $this->assertStringContainsString('Under Materials on Job 1 ("Baltic Birch Plywood for the stair landi..."):', $message);
        $this->assertStringContainsString('the Description is '.mb_strlen('Baltic Birch Plywood for the stair landing. '.str_repeat('x', 470)).' characters', $message);
        $this->assertStringNotContainsString('works.0', $message);
    }

    public function test_second_material_row_is_numbered(): void
    {
        $works = [[
            'title' => '',
            'materials' => [
                ['short_name' => 'Primer', 'description' => 'OK'],
                ['short_name' => 'Paint', 'description' => str_repeat('b', 510)],
            ],
        ]];

        $validator = Validator::make(
            ['works' => $works],
            ['works.*.materials.*.description' => ['nullable', 'string', 'max:500']],
            StandardQuotationValidationMessages::forWorks($works)
        );

        $this->assertTrue($validator->fails());
        $this->assertSame(
            'Under Materials on Job 1, row 2 ("Paint"): the Description is 510 characters. Shorten that Description field to 500 characters or fewer, then save again.',
            $validator->errors()->first('works.0.materials.1.description')
        );
    }

    /**
     * @param  list<array<string, mixed>>  $works
     */
    private function descriptionError(array $works): string
    {
        $validator = Validator::make(
            ['works' => $works],
            ['works.*.materials.*.description' => ['nullable', 'string', 'max:500']],
            StandardQuotationValidationMessages::forWorks($works)
        );

        $this->assertTrue($validator->fails());

        return (string) $validator->errors()->first('works.0.materials.0.description');
    }
}

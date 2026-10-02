<?php

namespace Tests\Unit;

use Illuminate\Support\Facades\Validator;
use Tests\TestCase;

class ValidationMessageTest extends TestCase
{
    public function test_missing_fields_are_named_in_the_message(): void
    {
        $validator = Validator::make([], [
            'title' => ['required', 'string'],
            'project_category_ids' => ['required', 'array', 'min:1'],
            'completed_on' => ['required', 'date'],
        ]);

        $this->assertSame(
            [
                'The title field is required.',
                'Select at least one category.',
                'The completed on field is required.',
            ],
            $validator->errors()->all(),
        );
    }
}

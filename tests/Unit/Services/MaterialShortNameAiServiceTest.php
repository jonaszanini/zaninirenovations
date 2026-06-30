<?php

namespace Tests\Unit\Services;

use App\Models\Material;
use App\Services\GeminiService;
use App\Services\MaterialShortNameAiService;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;
use RuntimeException;

class MaterialShortNameAiServiceTest extends TestCase
{
    public function test_generate_short_names_returns_validated_results(): void
    {
        $gemini = $this->createMock(GeminiService::class);
        $gemini->method('generateStructuredJson')->willReturn([
            'items' => [
                ['index' => 0, 'id' => 10, 'short_name' => '4x4x10 PT Post'],
                ['index' => 1, 'id' => 11, 'short_name' => "6' Fence Board"],
            ],
        ]);

        $service = $this->makeTestService($gemini);

        $results = $service->generateShortNames([
            ['index' => 0, 'id' => 10, 'store_name' => 'Pressure Treated 4 x 4 x 10 Post'],
            ['index' => 1, 'id' => 11, 'store_name' => 'Fence Board 6 ft'],
        ]);

        $this->assertSame([
            ['id' => 10, 'short_name' => '4x4x10 PT Post'],
            ['id' => 11, 'short_name' => "6' Fence Board"],
        ], $results);
    }

    public function test_generate_short_names_rejects_wrong_count(): void
    {
        $gemini = $this->createMock(GeminiService::class);
        $gemini->method('generateStructuredJson')->willReturn([
            'items' => [
                ['index' => 0, 'id' => 10, 'short_name' => 'Only one'],
            ],
        ]);

        $service = $this->makeTestService($gemini);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('expected 2');

        $service->generateShortNames([
            ['index' => 0, 'id' => 10, 'store_name' => 'A'],
            ['index' => 1, 'id' => 11, 'store_name' => 'B'],
        ]);
    }

    public function test_generate_short_names_rejects_id_mismatch(): void
    {
        $gemini = $this->createMock(GeminiService::class);
        $gemini->method('generateStructuredJson')->willReturn([
            'items' => [
                ['index' => 0, 'id' => 99, 'short_name' => 'Wrong id'],
            ],
        ]);

        $service = $this->makeTestService($gemini);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('id mismatch');

        $service->generateShortNames([
            ['index' => 0, 'id' => 10, 'store_name' => 'A'],
        ]);
    }

    public function test_generate_and_apply_batch_updates_in_memory_materials(): void
    {
        $gemini = $this->createMock(GeminiService::class);
        $gemini->method('generateStructuredJson')->willReturn([
            'items' => [
                ['index' => 0, 'id' => 1, 'short_name' => '4x4 Post Cap'],
            ],
        ]);

        $service = $this->makeTestService($gemini);

        $material = new class extends Material
        {
            public function save(array $options = [])
            {
                return true;
            }
        };
        $material->id = 1;
        $material->name = 'Veranda cap';
        $material->short_name = null;

        $result = $service->generateAndApplyBatch([$material], overwrite: false);

        $this->assertSame(1, $result['updated']);
        $this->assertSame('4x4 Post Cap', $material->short_name);
    }

    public function test_validate_and_normalize_response_via_reflection(): void
    {
        $service = new MaterialShortNameAiService($this->createMock(GeminiService::class));
        $method = new ReflectionMethod($service, 'validateAndNormalizeResponse');
        $method->setAccessible(true);

        $input = [
            ['index' => 0, 'id' => 5, 'store_name' => 'Long name'],
        ];

        $output = $method->invoke($service, $input, [
            'items' => [
                ['index' => 0, 'id' => 5, 'short_name' => 'Short'],
            ],
        ]);

        $this->assertSame([['id' => 5, 'short_name' => 'Short']], $output);
    }

    private function makeTestService(GeminiService $gemini): MaterialShortNameAiService
    {
        return new class($gemini) extends MaterialShortNameAiService
        {
            protected function resolveInstructionText(): string
            {
                return 'test instruction';
            }
        };
    }
}

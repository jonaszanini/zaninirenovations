<?php

namespace App\Services;

use App\Models\AiInstruction;
use App\Models\Material;
use Illuminate\Database\Eloquent\Builder;
use RuntimeException;

class MaterialShortNameAiService
{
    public function __construct(
        private GeminiService $gemini,
    ) {}

    /**
     * @return Builder<Material>
     */
    public function materialsQuery(string $mode): Builder
    {
        $query = Material::query()->orderBy('id');

        if ($mode === 'missing') {
            $query->where(function ($q) {
                $q->whereNull('short_name')->orWhere('short_name', '');
            });
        } elseif ($mode !== 'all') {
            throw new RuntimeException('Invalid mode: '.$mode);
        }

        return $query;
    }

    /**
     * @param  list<int>  $materialIds
     * @return list<list<int>>
     */
    public function buildBatches(array $materialIds): array
    {
        $batchSize = max(1, (int) config('materials.ai_short_name_batch_size', 25));

        return array_values(array_map(
            fn (array $chunk) => array_values($chunk),
            array_chunk($materialIds, $batchSize),
        ));
    }

    /**
     * @param  list<Material>  $materials
     * @return array{updated: int, skipped: int, errors: list<string>, results: list<array{id: int, short_name: string}>}
     */
    public function generateAndApplyBatch(array $materials, bool $overwrite = false): array
    {
        if ($materials === []) {
            return ['updated' => 0, 'skipped' => 0, 'errors' => [], 'results' => []];
        }

        $items = [];
        foreach ($materials as $index => $material) {
            $items[] = [
                'index' => $index,
                'id' => (int) $material->id,
                'store_name' => trim((string) $material->name),
            ];
        }

        $results = $this->generateShortNames($items);

        $updated = 0;
        $skipped = 0;
        $errors = [];
        $applied = [];

        foreach ($results as $result) {
            $material = collect($materials)->first(fn (Material $m) => (int) $m->id === (int) $result['id']);
            if ($material === null) {
                continue;
            }

            if (! $overwrite && filled($material->short_name)) {
                $skipped++;

                continue;
            }

            $shortName = $result['short_name'];
            if ($shortName === $material->short_name) {
                $skipped++;

                continue;
            }

            $material->short_name = $shortName;
            $material->save();
            $updated++;
            $applied[] = ['id' => (int) $material->id, 'short_name' => $shortName];
        }

        return [
            'updated' => $updated,
            'skipped' => $skipped,
            'errors' => $errors,
            'results' => $applied,
        ];
    }

    /**
     * @param  list<array{index: int, id: int, store_name: string}>  $items
     * @return list<array{id: int, short_name: string}>
     */
    public function generateShortNames(array $items, int $retryCount = 0): array
    {
        if ($items === []) {
            return [];
        }

        $userText = json_encode(['items' => $items], JSON_UNESCAPED_UNICODE);
        if ($userText === false) {
            throw new RuntimeException('Could not encode material names for Gemini.');
        }

        try {
            $raw = $this->gemini->generateStructuredJson(
                $this->resolveInstructionText(),
                $userText,
                $this->responseSchema(),
            );
        } catch (RuntimeException $e) {
            if ($retryCount < 1) {
                return $this->generateShortNames($items, $retryCount + 1);
            }

            throw $e;
        }

        return $this->validateAndNormalizeResponse($items, $raw);
    }

    /**
     * @param  list<array{index: int, id: int, store_name: string}>  $inputItems
     * @param  array<string, mixed>  $raw
     * @return list<array{id: int, short_name: string}>
     */
    private function validateAndNormalizeResponse(array $inputItems, array $raw): array
    {
        $outputItems = $raw['items'] ?? null;
        if (! is_array($outputItems)) {
            throw new RuntimeException('Gemini response missing items array.');
        }

        $expectedCount = count($inputItems);
        if (count($outputItems) !== $expectedCount) {
            throw new RuntimeException(
                'Gemini returned '.count($outputItems).' short name(s) but expected '.$expectedCount.'.'
            );
        }

        $inputByIndex = [];
        foreach ($inputItems as $item) {
            $inputByIndex[(int) $item['index']] = $item;
        }

        $results = [];
        $seenIndices = [];

        foreach ($outputItems as $outputItem) {
            if (! is_array($outputItem)) {
                throw new RuntimeException('Gemini returned an invalid item in items array.');
            }

            $index = $outputItem['index'] ?? null;
            $id = $outputItem['id'] ?? null;
            $shortName = $outputItem['short_name'] ?? null;

            if (! is_int($index) && ! (is_string($index) && ctype_digit($index))) {
                throw new RuntimeException('Gemini item missing valid index.');
            }
            $index = (int) $index;

            if (! is_int($id) && ! (is_string($id) && ctype_digit($id))) {
                throw new RuntimeException('Gemini item missing valid id.');
            }
            $id = (int) $id;

            if (! is_string($shortName)) {
                throw new RuntimeException('Gemini item #'.$index.' missing short_name.');
            }

            $shortName = trim($shortName);
            if ($shortName === '') {
                throw new RuntimeException('Gemini returned empty short_name for item #'.$index.'.');
            }
            if (strlen($shortName) > 120) {
                $shortName = substr($shortName, 0, 120);
            }

            if (! isset($inputByIndex[$index])) {
                throw new RuntimeException('Gemini returned unexpected index '.$index.'.');
            }

            if ($seenIndices[$index] ?? false) {
                throw new RuntimeException('Gemini returned duplicate index '.$index.'.');
            }
            $seenIndices[$index] = true;

            $expected = $inputByIndex[$index];
            if ((int) $expected['id'] !== $id) {
                throw new RuntimeException(
                    'Gemini id mismatch at index '.$index.': expected '.$expected['id'].', got '.$id.'.'
                );
            }

            $results[$index] = [
                'id' => $id,
                'short_name' => $shortName,
            ];
        }

        for ($i = 0; $i < $expectedCount; $i++) {
            if (! isset($seenIndices[$i])) {
                throw new RuntimeException('Gemini response missing index '.$i.'.');
            }
        }

        ksort($results);

        return array_values($results);
    }

    protected function resolveInstructionText(): string
    {
        $instruction = AiInstruction::forKey(AiInstruction::KEY_MATERIAL_SHORT_NAME);
        if (! $instruction) {
            throw new RuntimeException('Material short name AI instruction is not configured. Add it under Tools → AI Instructions.');
        }

        return $this->systemInstruction($instruction->instruction);
    }

    protected function systemInstruction(string $instruction): string
    {
        return $instruction;
    }

    /**
     * @return array<string, mixed>
     */
    private function responseSchema(): array
    {
        return [
            'type' => 'OBJECT',
            'properties' => [
                'items' => [
                    'type' => 'ARRAY',
                    'description' => 'One entry per input item, same order, same index and id values, plus short_name.',
                    'items' => [
                        'type' => 'OBJECT',
                        'properties' => [
                            'index' => [
                                'type' => 'INTEGER',
                                'description' => 'Same index from the input item.',
                            ],
                            'id' => [
                                'type' => 'INTEGER',
                                'description' => 'Same material id from the input item.',
                            ],
                            'short_name' => [
                                'type' => 'STRING',
                                'description' => 'Condensed contractor-friendly label, max 120 characters.',
                            ],
                        ],
                        'required' => ['index', 'id', 'short_name'],
                    ],
                ],
            ],
            'required' => ['items'],
        ];
    }
}

<?php

namespace App\Services;

use App\Models\FenceQuotation;
use App\Models\LowDeckQuotation;
use App\Models\MillworkQuotation;
use App\Models\ShedQuotation;
use App\Models\StairsQuotation;
use App\Services\Millwork\MillworkPartBuilder;
use Illuminate\Support\Collection;

/**
 * Pulls existing quotation cut/material data into lumber (1D) and
 * millwork sheet (2D) cut-list rows. Does not recalculate quotes.
 */
class QuotationCutListImportService
{
    public const TYPES = [
        'low_deck' => 'Low deck',
        'fence' => 'Fence',
        'shed' => 'Shed',
        'millwork' => 'Millwork',
        'stairs' => 'Stairs',
    ];

    private MillworkPartBuilder $partBuilder;

    public function __construct(?MillworkPartBuilder $partBuilder = null)
    {
        $this->partBuilder = $partBuilder ?? new MillworkPartBuilder();
    }

    /**
     * Search-select options: recent first, filter by client or quote #.
     *
     * @return list<array<string, mixed>>
     */
    public function search(string $q = '', ?string $id = null): array
    {
        $id = trim((string) $id);
        if ($id !== '') {
            $payload = $this->import($id);
            if ($payload === null) {
                return [];
            }

            return [$this->optionFromPayload($payload)];
        }

        $q = trim($q);
        $rows = [];
        foreach ($this->recentQuotes($q) as $row) {
            $rows[] = $row;
        }
        usort($rows, static function (array $a, array $b): int {
            return strcmp((string) ($b['updated_at'] ?? ''), (string) ($a['updated_at'] ?? ''));
        });

        return array_slice($rows, 0, 30);
    }

    /**
     * @return array<string, mixed>|null
     */
    public function import(string $id): ?array
    {
        $parsed = $this->parseId($id);
        if ($parsed === null) {
            return null;
        }
        [$type, $pk] = $parsed;

        return match ($type) {
            'low_deck' => $this->fromLowDeck($pk),
            'fence' => $this->fromFence($pk),
            'shed' => $this->fromShed($pk),
            'millwork' => $this->fromMillwork($pk),
            'stairs' => $this->fromStairs($pk),
            default => null,
        };
    }

    /**
     * @return array{0: string, 1: int}|null
     */
    public function parseId(string $id): ?array
    {
        if (! preg_match('/^(low_deck|fence|shed|millwork|stairs):(\d+)$/', trim($id), $m)) {
            return null;
        }

        return [$m[1], (int) $m[2]];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function recentQuotes(string $q): array
    {
        $like = $q !== '' ? '%'.$q.'%' : null;
        $out = [];

        $out = array_merge($out, $this->mapQuotes(
            'low_deck',
            LowDeckQuotation::query()
                ->when($like, fn ($query) => $query->where(function ($sub) use ($like) {
                    $sub->where('quote_number', 'like', $like)
                        ->orWhere('client_name', 'like', $like);
                }))
                ->orderByDesc('updated_at')
                ->limit(20)
                ->get(['id', 'quote_number', 'client_name', 'client_project_id', 'updated_at'])
        ));

        $out = array_merge($out, $this->mapQuotes(
            'fence',
            FenceQuotation::query()
                ->when($like, fn ($query) => $query->where(function ($sub) use ($like) {
                    $sub->where('quote_number', 'like', $like)
                        ->orWhere('client_name', 'like', $like);
                }))
                ->orderByDesc('updated_at')
                ->limit(20)
                ->get(['id', 'quote_number', 'client_name', 'client_project_id', 'updated_at'])
        ));

        $out = array_merge($out, $this->mapQuotes(
            'shed',
            ShedQuotation::query()
                ->when($like, fn ($query) => $query->where(function ($sub) use ($like) {
                    $sub->where('quote_number', 'like', $like)
                        ->orWhere('client_name', 'like', $like);
                }))
                ->orderByDesc('updated_at')
                ->limit(20)
                ->get(['id', 'quote_number', 'client_name', 'client_project_id', 'updated_at'])
        ));

        $out = array_merge($out, $this->mapQuotes(
            'millwork',
            MillworkQuotation::query()
                ->when($like, fn ($query) => $query->where(function ($sub) use ($like) {
                    $sub->where('quote_number', 'like', $like)
                        ->orWhere('client_name', 'like', $like);
                }))
                ->orderByDesc('updated_at')
                ->limit(20)
                ->get(['id', 'quote_number', 'client_name', 'client_project_id', 'updated_at'])
        ));

        $out = array_merge($out, $this->mapQuotes(
            'stairs',
            StairsQuotation::query()
                ->when($like, fn ($query) => $query->where(function ($sub) use ($like) {
                    $sub->where('quote_number', 'like', $like)
                        ->orWhere('client_name', 'like', $like);
                }))
                ->orderByDesc('updated_at')
                ->limit(20)
                ->get(['id', 'quote_number', 'client_name', 'client_project_id', 'updated_at'])
        ));

        return $out;
    }

    /**
     * @param  Collection<int, mixed>  $quotes
     * @return list<array<string, mixed>>
     */
    private function mapQuotes(string $type, Collection $quotes): array
    {
        $label = self::TYPES[$type] ?? $type;
        $out = [];
        foreach ($quotes as $quote) {
            $number = (string) ($quote->quote_number ?? '');
            $client = trim((string) ($quote->client_name ?? ''));
            $name = trim(($client !== '' ? $client.' — ' : '').$number);
            $out[] = [
                'id' => $type.':'.$quote->id,
                'name' => $name !== '' ? $name : $label.' #'.$quote->id,
                'label' => $name !== '' ? $name : $label.' #'.$quote->id,
                'type' => $type,
                'type_label' => $label,
                'quote_number' => $number,
                'client_name' => $client,
                'client_project_id' => $quote->client_project_id,
                'updated_at' => optional($quote->updated_at)->toIso8601String(),
            ];
        }

        return $out;
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function optionFromPayload(array $payload): array
    {
        return [
            'id' => $payload['id'],
            'name' => $payload['title'],
            'label' => $payload['title'],
            'type' => $payload['type'],
            'type_label' => $payload['type_label'],
            'quote_number' => $payload['quote_number'],
            'client_name' => $payload['client_name'],
            'client_project_id' => $payload['client_project_id'],
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    private function fromLowDeck(int $id): ?array
    {
        $quote = LowDeckQuotation::query()->find($id);
        if (! $quote) {
            return null;
        }
        $calc = is_array($quote->calculation) ? $quote->calculation : [];
        $packing = is_array($calc['packing'] ?? null) ? $calc['packing'] : [];
        $groups = [
            'posts' => ['name' => '4×4 Posts', 'material_key' => '4x4'],
            'joists' => ['name' => '2×6 Joists & beams', 'material_key' => '2x6'],
            'boards' => ['name' => 'Deck boards', 'material_key' => 'deck-board'],
        ];
        $lumber = [];
        foreach ($groups as $key => $meta) {
            $pack = is_array($packing[$key] ?? null) ? $packing[$key] : [];
            $board = $this->boardTypeFromPackingBins($pack['bins'] ?? [], $meta['name'], $meta['material_key']);
            if ($board !== null) {
                $lumber[] = $board;
            }
        }

        return $this->payload(
            'low_deck',
            $quote->id,
            (string) $quote->quote_number,
            (string) $quote->client_name,
            $quote->client_project_id,
            $lumber,
            []
        );
    }

    /**
     * @return array<string, mixed>|null
     */
    private function fromFence(int $id): ?array
    {
        $quote = FenceQuotation::query()->find($id);
        if (! $quote) {
            return null;
        }
        $calc = is_array($quote->calculation) ? $quote->calculation : [];
        $materials = is_array($calc['materials'] ?? null) ? $calc['materials'] : [];
        $height = (float) $quote->fence_height_ft;
        if ($height <= 0) {
            $height = 6.0;
        }
        $lumber = [];
        foreach ($materials as $row) {
            if (! is_array($row)) {
                continue;
            }
            $key = (string) ($row['key'] ?? '');
            $qty = (int) round((float) ($row['quantity'] ?? 0));
            $price = $row['unit_price'] ?? 0;
            $name = (string) ($row['name'] ?? $key);
            if ($qty < 1) {
                continue;
            }
            if (in_array($key, ['board_6', 'board_8'], true)) {
                $len = $key === 'board_8' ? 8.0 : 6.0;
                $pieceLen = min($height, $len);
                $lumber[] = $this->boardType(
                    $name,
                    $key === 'board_8' ? 'fence-board-8' : 'fence-board-6',
                    [['lengthVal' => $this->num($len), 'price' => $price]],
                    [['desc' => 'Picket / board', 'lengthVal' => $this->num($pieceLen), 'qty' => $qty]]
                );
            } elseif (str_starts_with($key, 'post_')) {
                $len = str_contains($key, '12') ? 12.0 : 10.0;
                $materialKey = str_contains($key, '6x6') ? '6x6' : '4x4';
                $lumber[] = $this->boardType(
                    $name,
                    $materialKey,
                    [['lengthVal' => $this->num($len), 'price' => $price]],
                    [['desc' => 'Post', 'lengthVal' => $this->num($len), 'qty' => $qty]]
                );
            } elseif ($key === 'rail') {
                $lumber[] = $this->boardType(
                    $name !== '' ? $name : 'Rails',
                    '2x4-rail',
                    [['lengthVal' => '8', 'price' => $price]],
                    [['desc' => 'Rail', 'lengthVal' => '8', 'qty' => $qty]]
                );
            }
        }

        return $this->payload(
            'fence',
            $quote->id,
            (string) $quote->quote_number,
            (string) $quote->client_name,
            $quote->client_project_id,
            $lumber,
            []
        );
    }

    /**
     * @return array<string, mixed>|null
     */
    private function fromShed(int $id): ?array
    {
        $quote = ShedQuotation::query()->find($id);
        if (! $quote) {
            return null;
        }
        $inputs = is_array($quote->inputs) ? $quote->inputs : [];
        $calc = is_array($quote->calculation) ? $quote->calculation : [];
        $scope = is_array($calc['scope'] ?? null) ? $calc['scope'] : [];
        $materials = is_array($calc['materials'] ?? null) ? $calc['materials'] : [];

        $width = (float) ($scope['width_ft'] ?? $inputs['width_ft'] ?? 0);
        $length = (float) ($scope['length_ft'] ?? $inputs['length_ft'] ?? 0);
        $height = (float) ($scope['wall_height_ft'] ?? $inputs['wall_height_ft'] ?? 8);
        $pitch = (float) ($scope['roof_pitch'] ?? $inputs['roof_pitch'] ?? 6);
        $overhangIn = (float) ($inputs['overhang_in'] ?? 12);
        $rafterFt = $this->shedRafterLengthFt($width, $pitch, $overhangIn);

        $lumber = [];
        $sheets = [];
        foreach ($materials as $row) {
            if (! is_array($row)) {
                continue;
            }
            $key = (string) ($row['key'] ?? '');
            $qty = (int) ceil((float) ($row['qty'] ?? 0));
            $price = $row['unit_price'] ?? 0;
            $label = (string) ($row['label'] ?? $key);
            if ($qty < 1) {
                continue;
            }
            if (str_contains($key, 'joist') || str_ends_with($key, '_floor') && str_contains($key, 'lumber')) {
                $span = $width > 0 ? $width : 8.0;
                $lumber[] = $this->boardType(
                    $label,
                    str_contains($key, '2x4') ? '2x4' : '2x6',
                    $this->commonStock($price),
                    [['desc' => 'Floor joist', 'lengthVal' => $this->num($span), 'qty' => $qty]]
                );
            } elseif (str_contains($key, '_walls') || (str_contains($key, 'lumber_2x') && str_contains($label, 'stud'))) {
                $lumber[] = $this->boardType(
                    $label,
                    str_contains($key, '2x6') ? '2x6' : '2x4',
                    $this->commonStock($price),
                    [['desc' => 'Wall stud', 'lengthVal' => $this->num($height), 'qty' => $qty]]
                );
            } elseif (str_contains($key, 'rafter')) {
                $lumber[] = $this->boardType(
                    $label,
                    '2x6',
                    $this->commonStock($price),
                    [['desc' => 'Rafter', 'lengthVal' => $this->num($rafterFt), 'qty' => $qty]]
                );
            } elseif (str_starts_with($key, 'plate_')) {
                $lumber[] = $this->boardType(
                    $label,
                    str_contains($key, '2x6') ? '2x6' : '2x4',
                    $this->commonStock($price),
                    [['desc' => 'Plate', 'lengthVal' => '8', 'qty' => max(1, (int) ceil($qty / 8))]]
                );
            } elseif ($this->isSheetGoodKey($key)) {
                $materialKey = $this->sheetMaterialKey($key);
                $sheets[] = [
                    'name' => $label,
                    'material_key' => $materialKey,
                    'thickness_in' => str_contains($key, '34') || str_contains($key, 'plywood_34') ? 0.75 : 0.4375,
                    'stock' => [[
                        'label' => '4×8',
                        'width' => 48,
                        'length' => 96,
                        'price' => $price,
                    ]],
                    'parts' => [[
                        'label' => $label,
                        'width' => 48,
                        'length' => 96,
                        'qty' => $qty,
                        'grain' => 'any',
                    ]],
                ];
            }
        }

        return $this->payload(
            'shed',
            $quote->id,
            (string) $quote->quote_number,
            (string) ($quote->client_name ?? ''),
            $quote->client_project_id,
            $lumber,
            $sheets,
            $length > 0 && $width > 0 ? sprintf('%s′ × %s′ shed', $this->num($length), $this->num($width)) : null
        );
    }

    /**
     * @return array<string, mixed>|null
     */
    private function fromMillwork(int $id): ?array
    {
        $quote = MillworkQuotation::query()->find($id);
        if (! $quote) {
            return null;
        }
        $inputs = is_array($quote->inputs) ? $quote->inputs : [];
        $calc = is_array($quote->calculation) ? $quote->calculation : [];
        $sheet = is_array($calc['sheet'] ?? null) ? $calc['sheet'] : [];
        $thickness = (float) ($sheet['thickness_in'] ?? 0.75);
        if ($thickness <= 0) {
            $thickness = 0.75;
        }
        $eb = 0.0;

        $grouped = [];
        $pieces = is_array($inputs['pieces'] ?? null) ? $inputs['pieces'] : [];
        foreach ($pieces as $idx => $piece) {
            if (! is_array($piece)) {
                continue;
            }
            $exploded = $this->partBuilder->explode($piece, $thickness, $eb);
            foreach ($exploded['parts'] as $part) {
                $mat = $part->materialKey !== '' ? $part->materialKey : 'sheet';
                $w = round($part->width, 3);
                $l = round($part->length, 3);
                $gKey = $mat.'|'.$w.'x'.$l.'|'.$part->grain;
                if (! isset($grouped[$gKey])) {
                    $grouped[$gKey] = [
                        'label' => $part->label,
                        'width' => $w,
                        'length' => $l,
                        'qty' => 0,
                        'grain' => $part->grain,
                        'material_key' => $mat,
                    ];
                }
                $grouped[$gKey]['qty'] += max(1, (int) $part->quantity);
            }
            unset($idx);
        }

        $byMaterial = [];
        foreach ($grouped as $row) {
            $mat = $row['material_key'];
            if (! isset($byMaterial[$mat])) {
                $byMaterial[$mat] = [];
            }
            $byMaterial[$mat][] = [
                'label' => $row['label'],
                'width' => $row['width'],
                'length' => $row['length'],
                'qty' => $row['qty'],
                'grain' => $row['grain'],
            ];
        }

        $stockW = (float) ($sheet['width_in'] ?? 48);
        $stockL = (float) ($sheet['length_in'] ?? 96);
        $stockPrice = (float) ($sheet['unit_price'] ?? 0);
        $stockLabel = (string) ($sheet['name'] ?? '4×8');
        $primaryKey = $this->normaliseMaterialKey((string) ($sheet['name'] ?? 'plywood'));

        $sheets = [];
        foreach ($byMaterial as $mat => $parts) {
            $key = $mat === 'back' ? $primaryKey.'-back' : ($mat === 'default' ? $primaryKey : $mat);
            $sheets[] = [
                'name' => $mat === 'back' ? 'Back panels' : ($stockLabel !== '' ? $stockLabel : 'Sheet goods'),
                'material_key' => $this->normaliseMaterialKey($key),
                'thickness_in' => $thickness,
                'stock' => [[
                    'label' => $stockLabel !== '' ? $stockLabel : '4×8',
                    'width' => $stockW > 0 ? $stockW : 48,
                    'length' => $stockL > 0 ? $stockL : 96,
                    'price' => $stockPrice,
                ]],
                'parts' => $parts,
            ];
        }

        $lumber = [];
        foreach ($grouped as $row) {
            $minDim = min($row['width'], $row['length']);
            $maxDim = max($row['width'], $row['length']);
            if ($minDim > 0 && $minDim <= 8 && $maxDim >= 12) {
                $lumber[] = $this->boardType(
                    $row['label'],
                    $this->normaliseMaterialKey($row['material_key']),
                    [['lengthVal' => '96', 'price' => $stockPrice]],
                    [[
                        'desc' => $row['label'],
                        'lengthVal' => $this->num($maxDim),
                        'qty' => $row['qty'],
                    ]],
                    'in',
                    'in'
                );
            }
        }

        return $this->payload(
            'millwork',
            $quote->id,
            (string) $quote->quote_number,
            (string) ($quote->client_name ?? ''),
            $quote->client_project_id,
            $lumber,
            $sheets
        );
    }

    /**
     * @return array<string, mixed>|null
     */
    private function fromStairs(int $id): ?array
    {
        $quote = StairsQuotation::query()->find($id);
        if (! $quote) {
            return null;
        }
        $inputs = is_array($quote->inputs) ? $quote->inputs : [];
        $steps = (int) ($inputs['total_steps'] ?? 0);
        $lumber = [];
        if ($steps > 0) {
            $width = (float) ($inputs['width_in'] ?? $inputs['tread_width_in'] ?? 36);
            $run = (float) ($inputs['run_in'] ?? 10);
            $rise = (float) ($inputs['rise_in'] ?? 7.75);
            if ($width <= 0) {
                $width = 36;
            }
            $stringer = $steps * hypot($run > 0 ? $run : 10, $rise > 0 ? $rise : 7.75) / 12.0;
            $lumber[] = $this->boardType(
                'Stringers',
                '2x12',
                $this->commonStock(0),
                [['desc' => 'Stringer', 'lengthVal' => $this->num(max($stringer, 8)), 'qty' => 2]]
            );
            $lumber[] = $this->boardType(
                'Treads',
                'tread',
                [['lengthVal' => $this->num(max(4, ceil($width / 12) + 1)), 'price' => 0]],
                [['desc' => 'Tread', 'lengthVal' => $this->num($width), 'qty' => $steps]],
                'ft',
                'in'
            );
        }

        return $this->payload(
            'stairs',
            $quote->id,
            (string) $quote->quote_number,
            (string) ($quote->client_name ?? ''),
            $quote->client_project_id,
            $lumber,
            []
        );
    }

    /**
     * @param  list<mixed>  $bins
     * @return array<string, mixed>|null
     */
    private function boardTypeFromPackingBins(array $bins, string $name, string $materialKey): ?array
    {
        $pieceCounts = [];
        $stocks = [];
        foreach ($bins as $bin) {
            if (! is_array($bin)) {
                continue;
            }
            $len = (float) ($bin['length_ft'] ?? 0);
            if ($len > 0) {
                $stockKey = $this->num($len);
                if (! isset($stocks[$stockKey])) {
                    $stocks[$stockKey] = [
                        'lengthVal' => $stockKey,
                        'price' => $bin['price'] ?? 0,
                    ];
                }
            }
            foreach ($bin['pieces_ft'] ?? [] as $piece) {
                $piece = round((float) $piece, 3);
                if ($piece <= 0) {
                    continue;
                }
                $pKey = $this->num($piece);
                $pieceCounts[$pKey] = ($pieceCounts[$pKey] ?? 0) + 1;
            }
        }
        if ($pieceCounts === []) {
            return null;
        }
        $pieces = [];
        foreach ($pieceCounts as $len => $qty) {
            $pieces[] = [
                'desc' => $name,
                'lengthVal' => $len,
                'qty' => $qty,
            ];
        }

        return $this->boardType($name, $materialKey, array_values($stocks), $pieces);
    }

    /**
     * @param  list<array<string, mixed>>  $stocks
     * @param  list<array<string, mixed>>  $pieces
     * @return array<string, mixed>
     */
    private function boardType(
        string $name,
        string $materialKey,
        array $stocks,
        array $pieces,
        string $stockUnit = 'ft',
        string $pieceUnit = 'ft'
    ): array {
        return [
            'name' => $name,
            'material_key' => $this->normaliseMaterialKey($materialKey),
            'stock_unit' => $stockUnit,
            'piece_unit' => $pieceUnit,
            'stocks' => $stocks !== [] ? $stocks : $this->commonStock(0),
            'pieces' => $pieces,
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $lumber
     * @param  list<array<string, mixed>>  $sheets
     * @return array<string, mixed>
     */
    private function payload(
        string $type,
        int $id,
        string $quoteNumber,
        string $clientName,
        mixed $projectId,
        array $lumber,
        array $sheets,
        ?string $subtitle = null
    ): array {
        $typeLabel = self::TYPES[$type] ?? $type;
        $title = trim(($clientName !== '' ? $clientName.' — ' : '').$quoteNumber);
        if ($title === '') {
            $title = $typeLabel.' #'.$id;
        }

        return [
            'id' => $type.':'.$id,
            'type' => $type,
            'type_label' => $typeLabel,
            'quote_number' => $quoteNumber,
            'client_name' => $clientName,
            'client_project_id' => $projectId ? (int) $projectId : null,
            'title' => $title,
            'subtitle' => $subtitle,
            'has_parts' => $lumber !== [] || $sheets !== [],
            'lumber' => array_values($lumber),
            'sheets' => array_values($sheets),
        ];
    }

    /**
     * @return list<array{lengthVal: string, price: float|int|string}>
     */
    private function commonStock(mixed $price): array
    {
        $p = is_numeric($price) ? $price : 0;

        return [
            ['lengthVal' => '8', 'price' => $p],
            ['lengthVal' => '10', 'price' => 0],
            ['lengthVal' => '12', 'price' => 0],
            ['lengthVal' => '16', 'price' => 0],
        ];
    }

    private function shedRafterLengthFt(float $widthFt, float $pitch, float $overhangIn): float
    {
        $half = max(0.5, $widthFt / 2);
        $rise = $half * ($pitch / 12);
        $run = $half + ($overhangIn / 12);

        return round(hypot($run, $rise), 2);
    }

    private function isSheetGoodKey(string $key): bool
    {
        foreach (['osb', 'plywood', 'wall_osb', 'drywall_sheet', 'loft_osb'] as $needle) {
            if (str_contains($key, $needle)) {
                return true;
            }
        }

        return false;
    }

    private function sheetMaterialKey(string $key): string
    {
        if (str_contains($key, 'plywood')) {
            return 'plywood';
        }
        if (str_contains($key, 'drywall')) {
            return 'drywall';
        }

        return 'osb';
    }

    private function normaliseMaterialKey(string $key): string
    {
        $key = trim($key);
        $key = preg_replace('/\s+/', '-', $key) ?? $key;

        return $key !== '' ? mb_substr($key, 0, 80) : 'material';
    }

    private function num(float $n): string
    {
        $formatted = number_format($n, 3, '.', '');

        return rtrim(rtrim($formatted, '0'), '.');
    }
}

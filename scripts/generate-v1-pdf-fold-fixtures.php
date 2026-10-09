<?php

/**
 * Run against main (bc380f47) classes to freeze v1 PDF output.
 *
 *   php scripts/generate-v1-pdf-fold-fixtures.php /tmp/main-v1-fold
 */

declare(strict_types=1);

$worktree = $argv[1] ?? '/tmp/main-v1-fold';
$worktree = rtrim($worktree, '/');
if (! is_file($worktree.'/app/Models/ClientProjectQuotation.php')) {
    fwrite(STDERR, "Worktree missing ClientProjectQuotation: {$worktree}\n");
    exit(1);
}

require __DIR__.'/../vendor/autoload.php';

// Prepend AFTER Composer so main's App\ classes win over this checkout.
spl_autoload_register(static function (string $class) use ($worktree): bool {
    if (! str_starts_with($class, 'App\\')) {
        return false;
    }
    $path = $worktree.'/app/'.str_replace('\\', '/', substr($class, 4)).'.php';
    if (! is_file($path)) {
        return false;
    }
    require_once $path;

    return true;
}, true, true);

$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$loadedFrom = (new \ReflectionClass(App\Models\ClientProjectQuotation::class))->getFileName();
if (! str_starts_with((string) $loadedFrom, $worktree)) {
    fwrite(STDERR, "ClientProjectQuotation loaded from {$loadedFrom}, not {$worktree}\n");
    exit(1);
}
fwrite(STDOUT, "Using {$loadedFrom}\n");

use App\Models\ClientProjectQuotation;
use App\Services\DebrisRemovalExpenseService;

$outDir = __DIR__.'/../tests/fixtures/pdf-fold-v1';
if (! is_dir($outDir) && ! mkdir($outDir, 0777, true) && ! is_dir($outDir)) {
    fwrite(STDERR, "Cannot create {$outDir}\n");
    exit(1);
}

function snapLine(array $row): array
{
    $out = [
        'description' => (string) ($row['description'] ?? ''),
        'amount' => round((float) ($row['amount'] ?? 0), 2),
        'apply_hst' => (bool) ($row['apply_hst'] ?? false),
    ];
    if (array_key_exists('hst_taxable_amount', $row)) {
        $out['hst_taxable_amount'] = round((float) $row['hst_taxable_amount'], 2);
    }
    if (! empty($row['hst_included'])) {
        $out['hst_included'] = true;
    }
    if (! empty($row['is_heading'])) {
        $out['is_heading'] = true;
    }

    return $out;
}

function snapshot(ClientProjectQuotation $quote): array
{
    $lines = array_values(array_map('snapLine', $quote->pdfLineItems()));

    return [
        'lines' => $lines,
        'hst' => $quote->hst(),
        'pdf_hst' => $quote->pdfHst(),
        'total' => $quote->grandTotal(),
        'pdf_total' => $quote->pdfGrandTotal(),
    ];
}

function makeQuote(string $source, array $items, ?float $override = null): ClientProjectQuotation
{
    $quote = new ClientProjectQuotation([
        'source_type' => $source,
        'items' => $items,
        'subtotal_override' => $override,
    ]);
    $quote->exists = true;

    return $quote;
}

$commute = static fn (float $amt): array => [
    'description' => 'Commute expense',
    'amount' => $amt,
    'apply_hst' => false,
    'hst_included' => true,
];
$debris = static fn (float $amt): array => [
    'description' => DebrisRemovalExpenseService::LINE_DESCRIPTION,
    'amount' => $amt,
    'apply_hst' => false,
    'hst_included' => true,
];
$extraMat = static fn (float $amt): array => [
    'description' => 'Additional cost — Dumpster',
    'amount' => $amt,
    'apply_hst' => false,
    'hst_included' => true,
];
$extraLab = static fn (float $amt): array => [
    'description' => 'Additional labour — Extra hours',
    'amount' => $amt,
    'apply_hst' => false,
    'hst_included' => true,
];

$named = [
    'sq_2x_33_33' => [
        'source' => ClientProjectQuotation::SOURCE_STANDARD,
        'items' => [
            ['description' => 'Material', 'amount' => 66.66, 'apply_hst' => true],
        ],
    ],
    'material_1000_debris_565' => [
        'source' => ClientProjectQuotation::SOURCE_STANDARD,
        'items' => [
            ['description' => 'Material', 'amount' => 1000.00, 'apply_hst' => true],
            $debris(565.00),
        ],
    ],
    'material_1000_commute_113' => [
        'source' => ClientProjectQuotation::SOURCE_STANDARD,
        'items' => [
            ['description' => 'Material', 'amount' => 1000.00, 'apply_hst' => true],
            $commute(113.00),
        ],
    ],
    'paving_1000_labour_613_commute' => [
        'source' => ClientProjectQuotation::SOURCE_PAVING,
        'items' => [
            ['description' => 'Paving Stones — Material', 'amount' => 1000.00, 'apply_hst' => false, 'hst_included' => true],
            ['description' => 'Paving Stones — Labour', 'amount' => 613.00, 'apply_hst' => true, 'hst_taxable_amount' => 500.00],
            $commute(113.00),
        ],
    ],
    'labour_and_electrical_labour_split' => [
        'source' => ClientProjectQuotation::SOURCE_STANDARD,
        'items' => [
            ['description' => 'Labour', 'amount' => 500.00, 'apply_hst' => true],
            ['description' => 'Electrical labour', 'amount' => 300.00, 'apply_hst' => true],
            $commute(113.00),
        ],
    ],
    'cash_fence_labour_plus_debris' => [
        'source' => ClientProjectQuotation::SOURCE_FENCE,
        'items' => [
            ['description' => 'Material', 'amount' => 1000.00, 'apply_hst' => true],
            ['description' => '6 ft fence — labour', 'amount' => 500.00, 'apply_hst' => false],
            $debris(113.00),
        ],
    ],
];

$namedOut = [];
foreach ($named as $key => $case) {
    $quote = makeQuote($case['source'], $case['items']);
    $namedOut[$key] = [
        'source' => $case['source'],
        'items' => $case['items'],
        'override' => null,
    ] + snapshot($quote);
}

file_put_contents($outDir.'/named.json', json_encode($namedOut, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)."\n");

$sources = [
    ClientProjectQuotation::SOURCE_STANDARD,
    ClientProjectQuotation::SOURCE_FENCE,
    ClientProjectQuotation::SOURCE_LOW_DECK,
    ClientProjectQuotation::SOURCE_BASEMENT,
    ClientProjectQuotation::SOURCE_FLOORING,
    ClientProjectQuotation::SOURCE_PAVING,
];

$descPool = [
    'Material',
    'Materials',
    'Labour',
    'Labor',
    'labour',
    'LABOUR',
    'Kitchen — labour',
    'Kitchen - Labour',
    'Electrical labour',
    'Demolition labour',
    'Kitchen cabinets supply',
    'Plumbing rough-in',
    'Panel upgrade 200A',
    'Building permit',
    'Custom millwork',
    '6 ft fence — labour',
    '6 ft standard board fence, 4×4 posts in concrete, 24 linear ft (1 run)',
    'Low deck, 12×16 ft pressure-treated',
    'Paving Stones — Material',
    'Paving Stones — Labour',
    'Flooring installation (labor)',
    'Material — HST exempt',
    'Labour and material',
];

mt_srand(20261009);
$perSource = 2000;

foreach ($sources as $source) {
    $rows = [];
    for ($i = 0; $i < $perSource; $i++) {
        $items = [];
        $n = 1 + ($i % 3);
        for ($r = 0; $r < $n; $r++) {
            $desc = $descPool[($i + $r * 7) % count($descPool)];
            $amount = round(((($i * 37 + $r * 91) % 25000) + 1) / 100, 2);
            $apply = ! str_contains(strtolower($desc), 'exempt') && ! str_contains(strtolower($desc), 'cash');
            $row = [
                'description' => $desc,
                'amount' => $amount,
                'apply_hst' => $apply && ! str_contains($desc, '— Material'),
            ];
            if ($source === ClientProjectQuotation::SOURCE_PAVING && str_contains($desc, '— Material')) {
                $row['apply_hst'] = false;
                $row['hst_included'] = true;
            }
            if ($source === ClientProjectQuotation::SOURCE_PAVING && str_contains($desc, '— Labour')) {
                $row['apply_hst'] = true;
                $row['hst_taxable_amount'] = round($amount * 0.8, 2);
            }
            $items[] = $row;
        }
        if ($i % 2 === 0) {
            $items[] = $commute(round((($i * 13) % 20000) / 100, 2) ?: 0.09);
        }
        if ($i % 3 === 0) {
            $items[] = $debris(round((($i * 17) % 15000) / 100, 2) ?: 0.50);
        }
        if ($i % 5 === 0) {
            $items[] = $extraMat(round((($i * 19) % 8000) / 100, 2) ?: 1.13);
        }
        if ($i % 7 === 0) {
            $items[] = $extraLab(round((($i * 23) % 6000) / 100, 2) ?: 2.26);
        }
        $override = ($i % 11 === 0) ? round(50 + ($i % 400), 2) : null;

        $quote = makeQuote($source, $items, $override);
        $rows[] = [
            'i' => $i,
            'items' => $items,
            'override' => $override,
        ] + snapshot($quote);
    }

    $path = $outDir.'/'.$source.'.json';
    file_put_contents($path, json_encode($rows, JSON_UNESCAPED_UNICODE)."\n");
    echo $source, ' ', count($rows), ' -> ', $path, PHP_EOL;
}

echo 'named ', count($namedOut), PHP_EOL;
foreach ($namedOut as $key => $row) {
    echo $key, ' total=', $row['total'], ' hst=', $row['hst'], PHP_EOL;
}

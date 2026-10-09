<?php

/**
 * SQ-linked v2 snapshots from main #60 (f6ed7f91).
 *
 *   php scripts/generate-v2-sq-linked-fixtures.php /tmp/main-v2-fold
 */

declare(strict_types=1);

$worktree = $argv[1] ?? '/tmp/main-v2-fold';
$worktree = rtrim($worktree, '/');
if (! is_file($worktree.'/app/Models/StandardQuotation.php')) {
    fwrite(STDERR, "Worktree missing StandardQuotation: {$worktree}\n");
    exit(1);
}

require __DIR__.'/../vendor/autoload.php';

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

$loadedFrom = (new \ReflectionClass(App\Models\StandardQuotation::class))->getFileName();
if (! str_starts_with((string) $loadedFrom, $worktree)) {
    fwrite(STDERR, "StandardQuotation loaded from {$loadedFrom}, not {$worktree}\n");
    exit(1);
}
fwrite(STDOUT, "Using {$loadedFrom}\n");

use App\Models\ClientProjectQuotation;
use App\Models\StandardQuotation;

$outDir = __DIR__.'/../tests/fixtures/pdf-fold-v2';

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
    if (array_key_exists('hst_amount', $row)) {
        $out['hst_amount'] = round((float) $row['hst_amount'], 2);
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

function baseSq(array $extra): array
{
    return array_merge([
        'client_name' => 'V2 SQ-linked',
        'labour_hourly_rate' => 0,
        'labour_mode' => 'hours',
        'labour_quantity' => 0,
        'labour_use_fixed' => false,
        'labour_fixed_amount' => 0,
        'contingency_enabled' => false,
        'include_commute' => false,
        'include_debris_removal' => false,
        'include_building' => false,
        'pdf_detail_mode' => StandardQuotation::PDF_DETAIL_SUMMARY,
        'pdf_fold_version' => 2,
        'includes_material' => true,
        'includes_labour' => true,
        'materials' => [],
    ], $extra);
}

$named = [
    'sq_2x_33_33_summary' => baseSq([
        'materials' => [
            ['short_name' => 'A', 'unit_price' => 33.33, 'quantity' => 1, 'apply_hst' => true],
            ['short_name' => 'B', 'unit_price' => 33.33, 'quantity' => 1, 'apply_hst' => true],
        ],
        'pdf_detail_mode' => StandardQuotation::PDF_DETAIL_SUMMARY,
    ]),
    'sq_2x_33_33_detailed' => baseSq([
        'materials' => [
            ['short_name' => 'A', 'unit_price' => 33.33, 'quantity' => 1, 'apply_hst' => true],
            ['short_name' => 'B', 'unit_price' => 33.33, 'quantity' => 1, 'apply_hst' => true],
        ],
        'pdf_detail_mode' => StandardQuotation::PDF_DETAIL_DETAILED,
    ]),
    'sq_labour_building_contingency' => baseSq([
        'materials' => [
            ['short_name' => 'Plywood', 'unit_price' => 100.00, 'quantity' => 1, 'apply_hst' => true],
        ],
        'labour_use_fixed' => true,
        'labour_fixed_amount' => 50.00,
        'include_labour_hst' => true,
        'include_building' => true,
        'building_labor_percent' => 30,
        'contingency_enabled' => true,
        'contingency_percent' => 10,
    ]),
    'sq_cash_labour' => baseSq([
        'materials' => [
            ['short_name' => 'Plywood', 'unit_price' => 1000.00, 'quantity' => 1, 'apply_hst' => true],
        ],
        'labour_use_fixed' => true,
        'labour_fixed_amount' => 500.00,
        'include_labour_hst' => false,
        'include_commute' => true,
        'commute_amount' => 113,
    ]),
    'sq_multi_job_remainder' => baseSq([
        'works' => [
            [
                'title' => 'Job one',
                'materials' => [],
                'labour_use_fixed' => true,
                'labour_fixed_amount' => 1.15,
            ],
            [
                'title' => 'Job two',
                'materials' => [],
                'labour_use_fixed' => true,
                'labour_fixed_amount' => 1.15,
            ],
            [
                'title' => 'Materials only',
                'materials' => [[
                    'short_name' => 'Tile',
                    'unit_price' => 10,
                    'quantity' => 1,
                    'apply_hst' => true,
                ]],
                'labour_use_fixed' => true,
                'labour_fixed_amount' => 0,
            ],
        ],
        'include_building' => true,
        'building_labor_percent' => 30,
        'pdf_detail_mode' => StandardQuotation::PDF_DETAIL_DETAILED,
    ]),
    'sq_multi_job_remainder_two_job' => baseSq([
        'works' => [
            [
                'title' => 'Labour job',
                'materials' => [],
                'labour_use_fixed' => true,
                'labour_fixed_amount' => 2.30,
            ],
            [
                'title' => 'Materials only',
                'materials' => [[
                    'short_name' => 'Tile',
                    'unit_price' => 10,
                    'quantity' => 1,
                    'apply_hst' => true,
                ]],
                'labour_use_fixed' => true,
                'labour_fixed_amount' => 0,
            ],
        ],
        'include_building' => true,
        'building_labor_percent' => 30,
        'pdf_detail_mode' => StandardQuotation::PDF_DETAIL_DETAILED,
    ]),
    'sq_multi_job_remainder_four_job' => baseSq([
        'works' => [
            [
                'title' => 'Job one',
                'materials' => [],
                'labour_use_fixed' => true,
                'labour_fixed_amount' => 3.45,
            ],
            [
                'title' => 'Job two',
                'materials' => [],
                'labour_use_fixed' => true,
                'labour_fixed_amount' => 1.15,
            ],
            [
                'title' => 'Job three',
                'materials' => [],
                'labour_use_fixed' => true,
                'labour_fixed_amount' => 4.60,
            ],
            [
                'title' => 'Materials only',
                'materials' => [[
                    'short_name' => 'Tile',
                    'unit_price' => 25,
                    'quantity' => 1,
                    'apply_hst' => true,
                ]],
                'labour_use_fixed' => true,
                'labour_fixed_amount' => 0,
            ],
        ],
        'include_building' => true,
        'building_labor_percent' => 25,
        'pdf_detail_mode' => StandardQuotation::PDF_DETAIL_DETAILED,
    ]),
    'sq_multi_job_remainder_pct20' => baseSq([
        'works' => [
            [
                'title' => 'Job one',
                'materials' => [],
                'labour_use_fixed' => true,
                'labour_fixed_amount' => 1.15,
            ],
            [
                'title' => 'Job two',
                'materials' => [],
                'labour_use_fixed' => true,
                'labour_fixed_amount' => 2.30,
            ],
            [
                'title' => 'Materials only',
                'materials' => [[
                    'short_name' => 'Tile',
                    'unit_price' => 7.77,
                    'quantity' => 1,
                    'apply_hst' => true,
                ]],
                'labour_use_fixed' => true,
                'labour_fixed_amount' => 0,
            ],
        ],
        'include_building' => true,
        'building_labor_percent' => 20,
        'pdf_detail_mode' => StandardQuotation::PDF_DETAIL_DETAILED,
    ]),
    'sq_multi_job_remainder_with_contingency' => baseSq([
        'works' => [
            [
                'title' => 'Job one',
                'materials' => [[
                    'short_name' => 'Plywood',
                    'unit_price' => 11.11,
                    'quantity' => 1,
                    'apply_hst' => true,
                ]],
                'labour_use_fixed' => true,
                'labour_fixed_amount' => 1.15,
            ],
            [
                'title' => 'Job two',
                'materials' => [],
                'labour_use_fixed' => true,
                'labour_fixed_amount' => 5.55,
            ],
            [
                'title' => 'Materials only',
                'materials' => [[
                    'short_name' => 'Tile',
                    'unit_price' => 33.33,
                    'quantity' => 1,
                    'apply_hst' => true,
                ]],
                'labour_use_fixed' => true,
                'labour_fixed_amount' => 0,
            ],
        ],
        'include_building' => true,
        'building_labor_percent' => 30,
        'contingency_enabled' => true,
        'contingency_percent' => 10,
        'pdf_detail_mode' => StandardQuotation::PDF_DETAIL_DETAILED,
    ]),
    'sq_two_job_summary' => baseSq([
        'works' => [
            [
                'title' => 'Kitchen',
                'materials' => [[
                    'short_name' => 'Plywood',
                    'unit_price' => 80.00,
                    'quantity' => 1,
                    'apply_hst' => true,
                ]],
                'labour_use_fixed' => true,
                'labour_fixed_amount' => 200.00,
            ],
            [
                'title' => 'Bath',
                'materials' => [[
                    'short_name' => 'Tile',
                    'unit_price' => 40.00,
                    'quantity' => 2,
                    'apply_hst' => true,
                ]],
                'labour_use_fixed' => true,
                'labour_fixed_amount' => 150.00,
            ],
        ],
        'include_labour_hst' => true,
        'pdf_detail_mode' => StandardQuotation::PDF_DETAIL_SUMMARY,
    ]),
    'sq_two_job_combined' => baseSq([
        'works' => [
            [
                'title' => 'Kitchen',
                'materials' => [[
                    'short_name' => 'Plywood',
                    'unit_price' => 80.00,
                    'quantity' => 1,
                    'apply_hst' => true,
                ]],
                'labour_use_fixed' => true,
                'labour_fixed_amount' => 200.00,
            ],
            [
                'title' => 'Bath',
                'materials' => [[
                    'short_name' => 'Tile',
                    'unit_price' => 40.00,
                    'quantity' => 2,
                    'apply_hst' => true,
                ]],
                'labour_use_fixed' => true,
                'labour_fixed_amount' => 150.00,
            ],
        ],
        'include_labour_hst' => true,
        'pdf_detail_mode' => StandardQuotation::PDF_DETAIL_COMBINED,
    ]),
    'sq_two_job_detailed_cash' => baseSq([
        'works' => [
            [
                'title' => 'Kitchen',
                'materials' => [[
                    'short_name' => 'Plywood',
                    'unit_price' => 120.00,
                    'quantity' => 1,
                    'apply_hst' => true,
                ]],
                'labour_use_fixed' => true,
                'labour_fixed_amount' => 300.00,
            ],
            [
                'title' => 'Hall',
                'materials' => [],
                'labour_use_fixed' => true,
                'labour_fixed_amount' => 80.00,
            ],
        ],
        'include_labour_hst' => false,
        'include_commute' => true,
        'commute_amount' => 113,
        'pdf_detail_mode' => StandardQuotation::PDF_DETAIL_DETAILED,
    ]),
    'sq_four_job_detailed' => baseSq([
        'works' => [
            [
                'title' => 'Job A',
                'materials' => [[
                    'short_name' => 'A',
                    'unit_price' => 33.33,
                    'quantity' => 1,
                    'apply_hst' => true,
                ]],
                'labour_use_fixed' => true,
                'labour_fixed_amount' => 10.00,
            ],
            [
                'title' => 'Job B',
                'materials' => [[
                    'short_name' => 'B',
                    'unit_price' => 12.50,
                    'quantity' => 2,
                    'apply_hst' => false,
                ]],
                'labour_use_fixed' => true,
                'labour_fixed_amount' => 20.00,
            ],
            [
                'title' => 'Job C',
                'materials' => [],
                'labour_use_fixed' => true,
                'labour_fixed_amount' => 15.00,
            ],
            [
                'title' => 'Job D',
                'materials' => [[
                    'short_name' => 'D',
                    'unit_price' => 5.00,
                    'quantity' => 1,
                    'apply_hst' => true,
                ]],
                'labour_use_fixed' => true,
                'labour_fixed_amount' => 0,
            ],
        ],
        'include_labour_hst' => true,
        'include_building' => true,
        'building_labor_percent' => 30,
        'contingency_enabled' => true,
        'contingency_percent' => 10,
        'pdf_detail_mode' => StandardQuotation::PDF_DETAIL_DETAILED,
    ]),
];

$modes = [
    StandardQuotation::PDF_DETAIL_SUMMARY,
    StandardQuotation::PDF_DETAIL_DETAILED,
    StandardQuotation::PDF_DETAIL_COMBINED,
];

mt_srand(20261009 + 4242);
$random = [];
for ($i = 0; $i < 200; $i++) {
    $n = 1 + ($i % 3);
    $materials = [];
    for ($r = 0; $r < $n; $r++) {
        $unit = round(((($i * 37 + $r * 91) % 25000) + 1) / 100, 2);
        $materials[] = [
            'short_name' => 'Row '.$r,
            'unit_price' => $unit,
            'quantity' => 1 + ($r % 2),
            'apply_hst' => ($i + $r) % 5 !== 0,
        ];
    }
    $random[] = baseSq([
        'materials' => $materials,
        'labour_use_fixed' => $i % 2 === 0,
        'labour_fixed_amount' => $i % 2 === 0 ? round((($i * 13) % 8000) / 100, 2) : 0,
        'labour_hourly_rate' => $i % 2 === 0 ? 0 : 45,
        'labour_quantity' => $i % 2 === 0 ? 0 : 2 + ($i % 4),
        'include_labour_hst' => $i % 7 !== 0,
        'contingency_enabled' => $i % 4 === 0,
        'contingency_percent' => $i % 4 === 0 ? 10 : 0,
        'include_building' => $i % 5 === 0,
        'building_labor_percent' => $i % 5 === 0 ? 30 : 0,
        'include_commute' => $i % 3 === 0,
        'commute_amount' => $i % 3 === 0 ? round((($i * 17) % 20000) / 100, 2) ?: 1.13 : 0,
        'pdf_detail_mode' => $modes[$i % 3],
    ]);
}

for ($i = 0; $i < 40; $i++) {
    $jobs = 2 + ($i % 3);
    $works = [];
    for ($j = 0; $j < $jobs; $j++) {
        $works[] = [
            'title' => 'Job '.($j + 1),
            'materials' => [[
                'short_name' => 'Row '.$j,
                'unit_price' => round(((($i * 41 + $j * 73) % 18000) + 1) / 100, 2),
                'quantity' => 1 + ($j % 2),
                'apply_hst' => ($i + $j) % 4 !== 0,
            ]],
            'labour_use_fixed' => true,
            'labour_fixed_amount' => round((($i * 11 + $j * 7) % 4000) / 100, 2),
        ];
    }
    $random[] = baseSq([
        'works' => $works,
        'materials' => [],
        'include_labour_hst' => $i % 6 !== 0,
        'contingency_enabled' => $i % 4 === 0,
        'contingency_percent' => $i % 4 === 0 ? 10 : 0,
        'include_building' => $i % 5 === 0,
        'building_labor_percent' => $i % 5 === 0 ? 30 : 0,
        'include_commute' => $i % 3 === 0,
        'commute_amount' => $i % 3 === 0 ? 113.00 : 0,
        'pdf_detail_mode' => $modes[$i % 3],
    ]);
}

$all = $named;
foreach ($random as $i => $attrs) {
    $all['rand_'.$i] = $attrs;
}

$out = [];
foreach ($all as $key => $attrs) {
    $attrs['quote_number'] = 'SQ-V2-FX-'.substr(md5($key), 0, 12);
    $sq = new StandardQuotation($attrs);
    $items = match ($sq->pdf_detail_mode) {
        StandardQuotation::PDF_DETAIL_DETAILED => $sq->detailedPdfLineItems(),
        StandardQuotation::PDF_DETAIL_COMBINED => $sq->combinedPdfLineItems(),
        default => $sq->summaryPdfLineItems(),
    };
    $quote = new ClientProjectQuotation([
        'source_type' => ClientProjectQuotation::SOURCE_STANDARD,
        'pdf_fold_version' => 2,
        'items' => $items,
        'source_snapshot' => [
            'pdf_fold_version' => 2,
            'include_labour_hst' => $attrs['include_labour_hst'] ?? true,
        ],
    ]);
    $quote->exists = true;
    $out[$key] = [
        'sq' => $attrs,
        'items' => $items,
    ] + snapshot($quote);
    echo $key, ' total=', $out[$key]['total'], ' hst=', $out[$key]['hst'], PHP_EOL;
}

file_put_contents($outDir.'/sq_linked.json', json_encode($out, JSON_UNESCAPED_UNICODE)."\n");
echo 'sq_linked ', count($out), PHP_EOL;

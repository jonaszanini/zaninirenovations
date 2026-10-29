<?php

/**
 * Build 3,000 v2-shaped quotes like Tester's b2_55/66 set.
 */

$types = ['shelf', 'floating_shelf', 'kallax', 'cabinet', 'vanity', 'closet', 'bench', 'wainscoting', 'mantel', 'trim', 'cabinet_door'];
$cases = [];

$mk = static function (array $piece, array $extra = []) {
    return array_merge([
        'pieces' => [$piece],
        'include_install' => false,
        'owner_hourly_rate' => 85,
        'helper_hourly_rate' => 45,
        'client_provides_all' => false,
        'waste_pct' => 10,
    ], $extra);
};

$i = 0;
$add = static function (array $inputs) use (&$cases, &$i) {
    $cases['c'.$i] = $inputs;
    $i++;
};

// R5-1: set-unit thickness fallbacks (form-shaped floating shelf).
$add($mk([
    'type' => 'floating_shelf',
    'unit' => 'in',
    'qty' => 1,
    'edge_banding' => false,
    'sets' => [['qty' => 1, 'length' => 3.9, 'depth' => 1.1, 'thickness' => '', 'unit' => 'ft']],
]));
$add($mk([
    'type' => 'floating_shelf',
    'unit' => 'ft',
    'qty' => 1,
    'edge_banding' => false,
    'sets' => [['qty' => 1, 'length' => 46.8, 'depth' => 13.2, 'thickness' => '', 'unit' => 'in']],
]));
$add($mk([
    'type' => 'cabinet_door',
    'unit' => 'ft',
    'qty' => 1,
    'edge_banding' => false,
    'sets' => [['qty' => 1, 'width' => 18, 'height' => 30]],
]));
// R5-2: PDF still lists delivery on saved v2 when client provides all.
$add($mk([
    'type' => 'closet',
    'unit' => 'in',
    'width' => 72,
    'height' => 84,
    'depth' => 14,
    'qty' => 1,
    'include_doors' => false,
    'edge_banding' => false,
], ['client_provides_all' => true, 'delivery_amount' => 80, 'include_install' => true]));
// R5-3: stored qty / spare sheets above the new caps.
$add($mk([
    'type' => 'shelf',
    'qty' => 1,
    'edge_banding' => false,
    'sets' => [['qty' => 250, 'length' => 36, 'depth' => 12, 'unit' => 'in']],
]));
$add($mk([
    'type' => 'shelf',
    'qty' => 1,
    'edge_banding' => false,
    'sets' => [['qty' => 1, 'length' => 36, 'depth' => 12, 'unit' => 'in']],
], ['spare_sheets' => 60]));

// Targeted R4 repros first.
$add($mk(['type' => 'wainscoting', 'unit' => 'in', 'length' => 300, 'height' => 36, 'qty' => 1, 'edge_banding' => false]));
$add($mk(['type' => 'wainscoting', 'unit' => 'in', 'linear_ft' => 25, 'height' => 36, 'qty' => 1, 'edge_banding' => true]));
$add($mk(['type' => 'trim', 'unit' => 'ft', 'linear_ft' => 40, 'openings' => 2, 'width' => 72, 'height' => 84, 'depth' => 24, 'qty' => 1, 'edge_banding' => false, 'profile' => 'casing']));
$add($mk(['type' => 'bench', 'unit' => 'in', 'length' => 48, 'depth' => 16, 'height' => '', 'qty' => 1, 'edge_banding' => false]));
$add($mk(['type' => 'bench', 'unit' => 'ft', 'length' => 4, 'depth' => 1.5, 'height' => '', 'qty' => 1, 'edge_banding' => false]));
$add($mk(['type' => 'mantel', 'unit' => 'ft', 'length' => '', 'depth' => '', 'height' => '', 'qty' => 1, 'edge_banding' => false]));
$add($mk(['type' => 'closet', 'unit' => 'ft', 'width' => 6, 'height' => '', 'depth' => '', 'qty' => 1, 'edge_banding' => false, 'include_doors' => false]));
$add($mk(['type' => 'cabinet', 'unit' => 'in', 'width' => 36, 'height' => 34.5, 'depth' => 24, 'qty' => 1, 'include_doors' => false, 'edge_banding' => false, 'shelf_count' => 0]));

mt_srand(64);
$limit = (int) (getenv('MILLWORK_FUZZ_CASES') ?: 3000);
if ($limit < 1) {
    $limit = 3000;
}

while ($i < $limit) {
    $type = $types[$i % count($types)];
    $unit = ($i % 3 === 0) ? 'ft' : 'in';
    $blank = ($i % 7 === 0);
    $over = ($i % 11 === 0);
    $hidden = ($i % 5 === 0);
    $piece = [
        'type' => $type,
        'qty' => 1,
        'unit' => $unit,
        'edge_banding' => ($i % 2 === 0),
        'include_doors' => false,
        'include_back' => false,
        'shelf_count' => $type === 'closet' ? 3 : 0,
        'profile' => 'casing',
    ];
    $n = static function (float $in) use ($unit) {
        return $unit === 'ft' ? round($in / 12, 3) : $in;
    };
    if (in_array($type, ['shelf', 'floating_shelf', 'cabinet_door'], true)) {
        $len = $over ? 300 : 36;
        $dep = 12;
        $setUnit = $unit;
        if ($type === 'floating_shelf' && ($i % 4 === 0)) {
            $setUnit = $unit === 'ft' ? 'in' : 'ft';
        }
        $piece['sets'] = [[
            'qty' => 1,
            'length' => $blank && ($i % 2 === 0) ? '' : $n($len),
            'depth' => $n($dep),
            'width' => $hidden ? $n(24) : '',
            'height' => $hidden ? $n(18) : '',
            'thickness' => $type === 'floating_shelf' ? ($blank ? '' : 3) : '.75',
            'unit' => $setUnit,
        ]];
    } elseif ($type === 'trim') {
        $piece['linear_ft'] = $over ? 25 : 20;
        $piece['openings'] = $i % 4;
        $piece['width'] = $hidden ? 72 : ($piece['openings'] ? 32 : '');
        $piece['height'] = $hidden ? 84 : ($piece['openings'] ? 80 : '');
        $piece['depth'] = $hidden ? 24 : '';
        $piece['length'] = $over ? 300 : '';
    } elseif ($type === 'wainscoting') {
        $piece['linear_ft'] = $over ? 25 : 12;
        $piece['length'] = $over ? 300 : '';
        $piece['height'] = $blank ? '' : 36;
        $piece['width'] = $hidden ? 300 : '';
        $piece['depth'] = $hidden ? 24 : '';
    } elseif ($type === 'bench') {
        $piece['length'] = $n(48);
        $piece['depth'] = $n(16);
        $piece['height'] = $blank ? '' : $n(18);
        $piece['width'] = $hidden ? $n(20) : '';
    } elseif ($type === 'mantel') {
        $piece['length'] = $blank ? '' : $n(60);
        $piece['depth'] = $blank ? '' : $n(10);
        $piece['height'] = $blank ? '' : $n(8);
        $piece['width'] = $hidden ? $n(12) : '';
    } elseif ($type === 'closet') {
        $piece['width'] = $n(72);
        $piece['height'] = $blank ? '' : $n(84);
        $piece['depth'] = $blank ? '' : $n(14);
    } else {
        $piece['width'] = $over ? 300 : $n(36);
        $piece['height'] = $blank ? '' : $n(34.5);
        $piece['depth'] = $n($type === 'vanity' ? 21 : 24);
    }
    $add($mk($piece));
}

echo json_encode($cases, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

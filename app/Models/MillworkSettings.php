<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MillworkSettings extends Model
{
    public const SLOT_WOOD_SHEETS = 'wood_sheets';

    public const SLOT_HINGES = 'hinges';

    public const SLOT_PAINTS = 'paints';

    public const SLOT_STAINS = 'stains';

    public const SLOT_EDGE_BANDING = 'edge_banding';

    public const SLOT_SCREWS = 'screws';

    public const SLOT_NAILS = 'nails';

    public const SLOT_DRAWER_SLIDES = 'drawer_slides';

    public const SLOT_GLUE = 'glue';

    public const SLOT_PULLS = 'pulls';

    public const SLOT_RODS = 'closet_rods';

    public const SLOT_SHELF_PINS = 'shelf_pins';

    public const SLOT_TRIM = 'trim_stock';

    protected $fillable = [
        'owner_hourly_rate',
        'helper_hourly_rate',
        'hours_per_day',
        'productivity',
        'catalog',
        'cut_settings',
    ];

    protected $casts = [
        'owner_hourly_rate' => 'decimal:2',
        'helper_hourly_rate' => 'decimal:2',
        'hours_per_day' => 'decimal:2',
        'productivity' => 'array',
        'catalog' => 'array',
        'cut_settings' => 'array',
    ];

    public static function current(): self
    {
        $row = static::query()->find(1);
        if ($row) {
            return $row;
        }

        return static::query()->create([
            'id' => 1,
            'owner_hourly_rate' => 85,
            'helper_hourly_rate' => 45,
            'hours_per_day' => 8,
            'productivity' => null,
            'catalog' => null,
            'cut_settings' => null,
        ]);
    }

    /**
     * @return array<string, string>
     */
    public static function catalogSlots(): array
    {
        return [
            self::SLOT_WOOD_SHEETS => 'Wood sheets',
            self::SLOT_HINGES => 'Hinges',
            self::SLOT_PAINTS => 'Paint',
            self::SLOT_STAINS => 'Stain',
            self::SLOT_EDGE_BANDING => 'Edge banding',
            self::SLOT_SCREWS => 'Screws',
            self::SLOT_NAILS => 'Nails',
            self::SLOT_DRAWER_SLIDES => 'Drawer slides',
            self::SLOT_GLUE => 'Wood glue',
            self::SLOT_PULLS => 'Pulls / handles',
            self::SLOT_RODS => 'Closet rods',
            self::SLOT_SHELF_PINS => 'Shelf pins',
            self::SLOT_TRIM => 'Trim stock',
        ];
    }

    /**
     * @return array<string, float>
     */
    public static function defaultProductivity(): array
    {
        return [
            'shelf_hours_each' => 0.35,
            'floating_shelf_hours_each' => 1.25,
            'kallax_hours_per_cube' => 0.85,
            'cabinet_sf_per_hr' => 4.0,
            'door_hours_each' => 1.0,
            'bench_sf_per_hr' => 3.0,
            'edge_band_lf_per_hr' => 30,
            'paint_sf_per_hr' => 40,
            'stain_sf_per_hr' => 35,
            'install_sf_per_hr' => 8,
            'cut_hours_per_sheet' => 0.45,
            'closet_sf_per_hr' => 3.5,
            'wainscot_lf_per_hr' => 8,
            'trim_lf_per_hr' => 20,
            'mantel_hours_each' => 2.5,
        ];
    }

    /**
     * @return array<string, array<string, string>>
     */
    public static function productivityGroups(): array
    {
        return [
            'Build hours' => [
                'shelf_hours_each' => 'Simple shelf (hours each)',
                'floating_shelf_hours_each' => 'Floating shelf (hours each)',
                'kallax_hours_per_cube' => 'Kallax (hours per cube)',
                'cabinet_sf_per_hr' => 'Cabinet box (sq ft / hour)',
                'door_hours_each' => 'Door (hours each)',
                'bench_sf_per_hr' => 'Bench seating (sq ft / hour)',
                'closet_sf_per_hr' => 'Closet system (sq ft / hour)',
                'mantel_hours_each' => 'Mantel (hours each)',
            ],
            'Finish & install' => [
                'edge_band_lf_per_hr' => 'Edge banding (linear ft / hour)',
                'paint_sf_per_hr' => 'Paint (sq ft / hour)',
                'stain_sf_per_hr' => 'Stain (sq ft / hour)',
                'install_sf_per_hr' => 'On-site install (sq ft / hour)',
                'cut_hours_per_sheet' => 'Cut / nest (hours per sheet)',
                'wainscot_lf_per_hr' => 'Wainscoting (linear ft / hour)',
                'trim_lf_per_hr' => 'Trim install (linear ft / hour)',
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function defaultCutSettings(): array
    {
        return [
            'kerf_in' => 0.125,
            'trim_in' => 0.125,
            'min_offcut_in' => 2.0,
            'edge_band_thickness_in' => 0.018,
        ];
    }

    /**
     * @return array<string, list<array<string, mixed>>>
     */
    public static function defaultCatalog(): array
    {
        $item = function (string $id, string $name, string $unit, float $price, array $extra = []): array {
            return array_merge([
                'id' => $id,
                'catalog_material_id' => null,
                'name' => $name,
                'unit' => $unit,
                'unit_price' => $price,
            ], $extra);
        };

        return [
            self::SLOT_WOOD_SHEETS => [
                $item('ws_ply_34_cab_48x96', '3/4" cabinet-grade plywood 4×8', 'sheet', 72.00, [
                    'thickness_in' => 0.75,
                    'width_in' => 48,
                    'length_in' => 96,
                    'material_kind' => 'plywood',
                    'grade' => 'cabinet',
                ]),
                $item('ws_ply_34_util_48x96', '3/4" utility plywood 4×8', 'sheet', 48.00, [
                    'thickness_in' => 0.75,
                    'width_in' => 48,
                    'length_in' => 96,
                    'material_kind' => 'plywood',
                    'grade' => 'utility',
                ]),
                $item('ws_mdf_34_48x96', '3/4" MDF 4×8', 'sheet', 42.00, [
                    'thickness_in' => 0.75,
                    'width_in' => 48,
                    'length_in' => 96,
                    'material_kind' => 'mdf',
                    'grade' => 'cabinet',
                ]),
                $item('ws_ply_14_48x96', '1/4" plywood 4×8 (backs)', 'sheet', 28.00, [
                    'thickness_in' => 0.25,
                    'width_in' => 48,
                    'length_in' => 96,
                    'material_kind' => 'plywood',
                    'grade' => 'utility',
                ]),
                $item('ws_ply_34_cab_48x48', '3/4" cabinet-grade plywood 4×4', 'sheet', 42.00, [
                    'thickness_in' => 0.75,
                    'width_in' => 48,
                    'length_in' => 48,
                    'material_kind' => 'plywood',
                    'grade' => 'cabinet',
                ]),
                $item('ws_ply_34_cab_24x48', '3/4" cabinet-grade plywood 2×4', 'sheet', 24.00, [
                    'thickness_in' => 0.75,
                    'width_in' => 24,
                    'length_in' => 48,
                    'material_kind' => 'plywood',
                    'grade' => 'cabinet',
                ]),
                $item('ws_birch_5x5', '3/4" Baltic birch 5×5', 'sheet', 89.00, [
                    'thickness_in' => 0.75,
                    'width_in' => 60,
                    'length_in' => 60,
                    'material_kind' => 'plywood',
                    'grade' => 'cabinet',
                ]),
                $item('ws_bb_bb_6mm_48x96', 'BB/BB 6mm / 1/4" Baltic Birch Plywood – 4’x8′', 'sheet', 56.00, [
                    'thickness_in' => 0.25,
                    'width_in' => 48,
                    'length_in' => 96,
                    'material_kind' => 'plywood',
                    'grade' => 'cabinet',
                ]),
                $item('ws_bb_bb_12mm_48x96', 'BB/BB 12mm / 1/2" Baltic Birch Plywood – 4’x8′', 'sheet', 95.67, [
                    'thickness_in' => 0.50,
                    'width_in' => 48,
                    'length_in' => 96,
                    'material_kind' => 'plywood',
                    'grade' => 'cabinet',
                ]),
                $item('ws_bb_bb_15mm_48x96', 'BB/BB 15mm / 5/8" Baltic Birch Plywood – 4’x8′', 'sheet', 109.38, [
                    'thickness_in' => 0.625,
                    'width_in' => 48,
                    'length_in' => 96,
                    'material_kind' => 'plywood',
                    'grade' => 'cabinet',
                ]),
                $item('ws_bb_bb_18mm_48x96', 'BB/BB 18mm / 3/4" Baltic Birch Plywood – 4’x8′', 'sheet', 129.00, [
                    'thickness_in' => 0.75,
                    'width_in' => 48,
                    'length_in' => 96,
                    'material_kind' => 'plywood',
                    'grade' => 'cabinet',
                ]),
                $item('ws_bb_bb_24mm_48x96', 'BB/BB 24mm / 1" Baltic Birch Plywood – 4’x8′', 'sheet', 183.75, [
                    'thickness_in' => 1.00,
                    'width_in' => 48,
                    'length_in' => 96,
                    'material_kind' => 'plywood',
                    'grade' => 'cabinet',
                ]),
                $item('ws_b_bb_6mm_48x96', 'B/BB 6mm / 1/4" Baltic Birch Plywood – 4’x8′', 'sheet', 61.60, [
                    'thickness_in' => 0.25,
                    'width_in' => 48,
                    'length_in' => 96,
                    'material_kind' => 'plywood',
                    'grade' => 'cabinet',
                ]),
                $item('ws_b_bb_12mm_48x96', 'B/BB 12mm / 1/2" Baltic Birch Plywood – 4’x8′', 'sheet', 115.00, [
                    'thickness_in' => 0.50,
                    'width_in' => 48,
                    'length_in' => 96,
                    'material_kind' => 'plywood',
                    'grade' => 'cabinet',
                ]),
                $item('ws_b_bb_18mm_48x96', 'B/BB 18mm / 3/4" Baltic Birch Plywood – 4’x8′', 'sheet', 159.95, [
                    'thickness_in' => 0.75,
                    'width_in' => 48,
                    'length_in' => 96,
                    'material_kind' => 'plywood',
                    'grade' => 'cabinet',
                ]),
            ],
            self::SLOT_HINGES => [
                $item('hg_overlay', 'Concealed overlay hinge (each)', 'each', 3.80, [
                    'hinge_style' => 'overlay',
                ]),
                $item('hg_inset', 'Concealed inset hinge (each)', 'each', 4.20, [
                    'hinge_style' => 'inset',
                ]),
                $item('hg_soft', 'Soft-close overlay hinge (each)', 'each', 6.50, [
                    'hinge_style' => 'soft_close',
                ]),
            ],
            self::SLOT_PAINTS => [
                $item('pt_quart', 'Cabinet paint, quart', 'quart', 28.00, [
                    'size' => 'quart',
                    'coverage_sf' => 87.5,
                ]),
                $item('pt_half', 'Cabinet paint, half gallon', 'half_gallon', 48.00, [
                    'size' => 'half_gallon',
                    'coverage_sf' => 175,
                ]),
            ],
            self::SLOT_STAINS => [
                $item('st_237', 'Wood stain, 237 ml', '237ml', 12.50, [
                    'size' => '237ml',
                    'coverage_sf' => 40,
                ]),
                $item('st_946', 'Wood stain, 946 ml', '946ml', 24.00, [
                    'size' => '946ml',
                    'coverage_sf' => 150,
                ]),
            ],
            self::SLOT_EDGE_BANDING => [
                $item('eb_pvc', 'Iron-on edge banding (50 ft roll)', 'roll', 12.48, [
                    'thickness_in' => 0.018,
                    'coverage_lf' => 50.0,
                ]),
            ],
            self::SLOT_SCREWS => [
                $item('sc_box', '1-1/4" cabinet screws (box of 100)', 'box', 8.50, [
                    'qty_per_pack' => 100,
                ]),
            ],
            self::SLOT_NAILS => [
                $item('nl_box', '18 ga brad nails (box of 1000)', 'box', 9.80, [
                    'qty_per_pack' => 1000,
                ]),
            ],
            self::SLOT_DRAWER_SLIDES => [
                $item('ds_pair', 'Side-mount drawer slides (pair)', 'pair', 14.50),
            ],
            self::SLOT_GLUE => [
                $item('gl_bottle', 'Wood glue, 16 oz', 'each', 8.90),
            ],
            self::SLOT_PULLS => [
                $item('pl_bar', 'Bar pull / handle (each)', 'each', 6.50),
            ],
            self::SLOT_RODS => [
                $item('rd_oval', 'Oval closet rod (each)', 'each', 18.00),
            ],
            self::SLOT_SHELF_PINS => [
                $item('sp_pack', 'Shelf pins (pack of 20)', 'pack', 4.50, [
                    'qty_per_pack' => 20,
                ]),
            ],
            self::SLOT_TRIM => [
                $item('tr_base', 'MDF baseboard', 'lf', 2.40, [
                    'profile' => 'baseboard',
                    'stock_length_ft' => 16,
                ]),
                $item('tr_case', 'MDF casing', 'lf', 2.10, [
                    'profile' => 'casing',
                    'stock_length_ft' => 16,
                ]),
                $item('tr_crown', 'MDF crown', 'lf', 3.80, [
                    'profile' => 'crown',
                    'stock_length_ft' => 12,
                ]),
                $item('tr_chair', 'MDF chair rail', 'lf', 2.60, [
                    'profile' => 'chair_rail',
                    'stock_length_ft' => 16,
                ]),
            ],
        ];
    }

    /**
     * @return array<string, float>
     */
    public function resolvedProductivity(): array
    {
        $defaults = self::defaultProductivity();
        $stored = is_array($this->productivity) ? $this->productivity : [];
        foreach ($defaults as $key => $value) {
            if (isset($stored[$key]) && is_numeric($stored[$key])) {
                $defaults[$key] = (float) $stored[$key];
            }
        }

        return \App\Services\SharedWorkCatalog::overlayProductivity('millwork', $defaults, (float) $this->owner_hourly_rate);
    }

    /**
     * @return array<string, mixed>
     */
    public function resolvedCutSettings(): array
    {
        $defaults = self::defaultCutSettings();
        $stored = is_array($this->cut_settings) ? $this->cut_settings : [];
        foreach ($defaults as $key => $value) {
            if (isset($stored[$key]) && is_numeric($stored[$key])) {
                $defaults[$key] = (float) $stored[$key];
            }
        }

        return $defaults;
    }

    /**
     * @return array<string, list<array<string, mixed>>>
     */
    public function resolvedCatalog(): array
    {
        $defaults = self::defaultCatalog();
        $stored = is_array($this->catalog) ? $this->catalog : [];
        $out = [];
        foreach (self::catalogSlots() as $slot => $_label) {
            $rows = $stored[$slot] ?? null;
            if (! is_array($rows) || $rows === []) {
                $out[$slot] = $defaults[$slot] ?? [];
                continue;
            }
            $normalized = [];
            foreach ($rows as $row) {
                if (! is_array($row)) {
                    continue;
                }
                $item = self::normalizeCatalogItem($slot, $row);
                if ($item !== null) {
                    $normalized[] = $item;
                }
            }
            $out[$slot] = $normalized !== [] ? $normalized : ($defaults[$slot] ?? []);
        }

        return $out;
    }

    /**
     * @param  array<string, mixed>  $row
     * @return array<string, mixed>|null
     */
    public static function normalizeCatalogItem(string $slot, array $row): ?array
    {
        $name = trim((string) ($row['name'] ?? ''));
        $id = trim((string) ($row['id'] ?? ''));
        if ($name === '' && $id === '' && (int) ($row['catalog_material_id'] ?? 0) <= 0) {
            return null;
        }
        if ($id === '') {
            $id = $slot.'_'.substr(sha1($name.microtime(true).mt_rand()), 0, 8);
        }
        $catalogId = (int) ($row['catalog_material_id'] ?? 0);
        $item = [
            'id' => $id,
            'catalog_material_id' => $catalogId > 0 ? $catalogId : null,
            'name' => $name !== '' ? $name : 'Untitled',
            'unit' => trim((string) ($row['unit'] ?? 'each')) ?: 'each',
            'unit_price' => round(max(0, (float) ($row['unit_price'] ?? 0)), 4),
        ];

        if ($slot === self::SLOT_WOOD_SHEETS) {
            $item['thickness_in'] = round(max(0.125, (float) ($row['thickness_in'] ?? 0.75)), 4);
            $item['width_in'] = round(max(1, (float) ($row['width_in'] ?? 48)), 4);
            $item['length_in'] = round(max(1, (float) ($row['length_in'] ?? 96)), 4);
            $kind = strtolower(trim((string) ($row['material_kind'] ?? 'plywood')));
            $item['material_kind'] = in_array($kind, ['plywood', 'mdf'], true) ? $kind : 'plywood';
            $grade = strtolower(trim((string) ($row['grade'] ?? 'cabinet')));
            $item['grade'] = in_array($grade, ['cabinet', 'utility'], true) ? $grade : 'cabinet';
            $item['species'] = self::sheetSpecies(array_merge($row, $item));
        }
        if ($slot === self::SLOT_PAINTS || $slot === self::SLOT_STAINS) {
            $item['size'] = trim((string) ($row['size'] ?? ''));
            $item['coverage_sf'] = round(max(0, (float) ($row['coverage_sf'] ?? 0)), 2);
        }
        if ($slot === self::SLOT_HINGES) {
            $style = strtolower(trim((string) ($row['hinge_style'] ?? 'overlay')));
            $item['hinge_style'] = in_array($style, ['overlay', 'inset', 'soft_close'], true) ? $style : 'overlay';
        }
        if ($slot === self::SLOT_EDGE_BANDING) {
            $item['thickness_in'] = round(max(0.001, (float) ($row['thickness_in'] ?? 0.018)), 4);
            $cov = isset($row['coverage_lf']) && is_numeric($row['coverage_lf']) ? (float) $row['coverage_lf'] : null;
            if ($cov === null || $cov <= 0) {
                if (preg_match('/(\d+(?:\.\d+)?)\s*(?:feet|foot|ft|lf)\b/i', $item['name'], $m)) {
                    $cov = (float) $m[1];
                } elseif ((float) $item['unit_price'] >= 5.0) {
                    $cov = 50.0;
                } else {
                    $cov = 50.0;
                }
            }
            $item['coverage_lf'] = round(max(0.1, $cov), 2);
            $item['unit'] = ! empty($row['unit']) && $row['unit'] !== 'lf' ? trim((string) $row['unit']) : 'roll';
        }
        if (in_array($slot, [self::SLOT_SCREWS, self::SLOT_NAILS, self::SLOT_SHELF_PINS], true)) {
            $item['qty_per_pack'] = max(1, (int) ($row['qty_per_pack'] ?? 1));
        }
        if ($slot === self::SLOT_TRIM) {
            $profile = strtolower(trim((string) ($row['profile'] ?? 'baseboard')));
            $item['profile'] = array_key_exists($profile, \App\Services\Millwork\MillworkPartBuilder::TRIM_PROFILES)
                ? $profile
                : 'baseboard';
            $item['stock_length_ft'] = round(max(1, (float) ($row['stock_length_ft'] ?? 12)), 2);
            $item['unit'] = 'lf';
        }

        return $item;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function itemsFor(string $slot): array
    {
        $catalog = $this->resolvedCatalog();

        return is_array($catalog[$slot] ?? null) ? $catalog[$slot] : [];
    }

    /**
     * @return array<string, mixed>|null
     */
    public function itemById(string $slot, ?string $id): ?array
    {
        if ($id === null || $id === '') {
            return null;
        }
        foreach ($this->itemsFor($slot) as $row) {
            if ((string) ($row['id'] ?? '') === $id) {
                return $row;
            }
        }

        return null;
    }

    /**
     * Exact material/species key for sheet swaps. Grade "cabinet" is too
     * broad (cabinet ply, Baltic 5×5, and 18mm Baltic all share it).
     *
     * @param  array<string, mixed>  $row
     */
    public static function sheetSpecies(array $row): string
    {
        $explicit = strtolower(trim((string) ($row['species'] ?? '')));
        if ($explicit !== '') {
            return $explicit;
        }
        $catalogId = (int) ($row['catalog_material_id'] ?? 0);
        $sku = strtolower(trim((string) ($row['sku'] ?? '')));
        $id = strtolower((string) ($row['id'] ?? ''));
        $name = strtolower((string) ($row['name'] ?? ''));

        if ($catalogId > 0) {
            return 'material:'.$catalogId;
        }
        if ($sku !== '') {
            return 'sku:'.$sku;
        }

        if (self::isDefaultCatalogueSheetId($id)) {
            return self::defaultCatalogueSpecies($id, $name, $row);
        }

        if ($id !== '') {
            return 'id:'.$id;
        }
        if ($name !== '') {
            return 'name:'.$name;
        }

        return 'unknown';
    }

    private static function isDefaultCatalogueSheetId(string $id): bool
    {
        if ($id === '') {
            return false;
        }

        return str_starts_with($id, 'ws_ply_34_cab_')
            || str_starts_with($id, 'ws_ply_34_util_')
            || str_starts_with($id, 'ws_ply_14_')
            || str_starts_with($id, 'ws_mdf_')
            || str_starts_with($id, 'ws_bb_bb_')
            || str_starts_with($id, 'ws_b_bb_')
            || $id === 'ws_birch_5x5'
            || str_contains($id, 'birch_5x5');
    }

    /**
     * @param  array<string, mixed>  $row
     */
    private static function defaultCatalogueSpecies(string $id, string $name, array $row): string
    {
        if (str_starts_with($id, 'ws_bb_bb_') || str_contains($name, 'bb/bb')) {
            return 'baltic_bb_bb';
        }
        if (str_starts_with($id, 'ws_b_bb_') || str_contains($name, 'b/bb')) {
            return 'baltic_b_bb';
        }
        if (str_contains($id, 'birch_5x5') || str_contains($name, '5×5') || str_contains($name, '5x5')) {
            return 'baltic_5x5';
        }
        if (($row['material_kind'] ?? '') === 'mdf' || str_starts_with($id, 'ws_mdf_') || str_contains($name, 'mdf')) {
            return 'mdf';
        }
        if (str_starts_with($id, 'ws_ply_34_util_') || str_contains($name, 'utility')) {
            return 'utility_ply';
        }
        if (str_contains($name, 'baltic')) {
            return 'baltic_birch';
        }
        if (str_starts_with($id, 'ws_ply_34_cab_')
            || (str_contains($name, 'cabinet-grade') && str_contains($name, 'plywood'))) {
            return 'cabinet_ply';
        }
        if ($id !== '') {
            return 'id:'.$id;
        }

        return $name !== '' ? 'name:'.$name : 'unknown';
    }

    /**
     * Synthetic 4×4 / 2×4 leftovers are only for known stocked families.
     */
    public static function allowsSyntheticSheetSwap(array $row): bool
    {
        $key = self::sheetSpecies($row);

        return in_array($key, [
            'cabinet_ply',
            'utility_ply',
            'mdf',
            'baltic_birch',
            'baltic_bb_bb',
            'baltic_b_bb',
            'baltic_5x5',
        ], true);
    }
}

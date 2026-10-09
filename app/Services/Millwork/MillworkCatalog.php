<?php

namespace App\Services\Millwork;

use App\Models\ClientProject;
use App\Models\MillworkQuotation;
use App\Models\MillworkSettings;

class MillworkCatalog
{
    /**
     * Quote input keys that pick a catalog row, keyed by slot.
     *
     * @return array<string, list<string>>
     */
    public static function inputKeysBySlot(): array
    {
        return [
            MillworkSettings::SLOT_WOOD_SHEETS => ['sheet_id', 'back_sheet_id'],
            MillworkSettings::SLOT_EDGE_BANDING => ['edge_banding_id'],
            MillworkSettings::SLOT_PAINTS => ['paint_id'],
            MillworkSettings::SLOT_STAINS => ['stain_id'],
            MillworkSettings::SLOT_TRIM => ['trim_id'],
            MillworkSettings::SLOT_HINGES => ['hinge_id'],
            MillworkSettings::SLOT_PULLS => ['pull_id'],
            MillworkSettings::SLOT_SCREWS => ['screw_id'],
            MillworkSettings::SLOT_NAILS => ['nail_id'],
            MillworkSettings::SLOT_DRAWER_SLIDES => ['slide_id'],
            MillworkSettings::SLOT_GLUE => ['glue_id'],
            MillworkSettings::SLOT_RODS => ['rod_id'],
            MillworkSettings::SLOT_SHELF_PINS => ['pin_id'],
        ];
    }

    /**
     * @return list<string>
     */
    public static function inputKeysForSlot(string $slot): array
    {
        return self::inputKeysBySlot()[$slot] ?? [];
    }

    public static function isHidden(array $row): bool
    {
        return filled($row['deleted_at'] ?? null);
    }

    /**
     * Overlay a quote's catalog snapshot on the live list. Snapshot rows win
     * for matching ids so a saved quote keeps the prices it was saved with.
     *
     * @param  array<string, list<array<string, mixed>>>  $live
     * @param  array<string, list<array<string, mixed>>>|null  $snapshot
     * @return array<string, list<array<string, mixed>>>
     */
    public static function mergeSnapshot(array $live, ?array $snapshot): array
    {
        $out = [];
        foreach (MillworkSettings::catalogSlots() as $slot => $_label) {
            $byId = [];
            foreach ($live[$slot] ?? [] as $row) {
                if (! is_array($row) || self::isHidden($row)) {
                    continue;
                }
                $id = trim((string) ($row['id'] ?? ''));
                if ($id === '') {
                    continue;
                }
                $byId[$id] = $row;
            }
            foreach ($snapshot[$slot] ?? [] as $row) {
                if (! is_array($row)) {
                    continue;
                }
                $id = trim((string) ($row['id'] ?? ''));
                if ($id === '') {
                    continue;
                }
                $item = MillworkSettings::normalizeCatalogItem($slot, $row);
                if ($item !== null) {
                    $byId[$id] = $item;
                }
            }
            $out[$slot] = array_values($byId);
        }

        return $out;
    }

    /**
     * Live catalog for dropdowns, plus any currently selected snapshot rows
     * so a soft-deleted item stays visible on the quote that uses it.
     *
     * @param  array<string, list<array<string, mixed>>>|null  $snapshot
     * @param  array<string, mixed>  $inputs
     * @return array<string, list<array<string, mixed>>>
     */
    public static function dropdownCatalog(MillworkSettings $settings, ?array $snapshot = null, array $inputs = []): array
    {
        $live = $settings->resolvedCatalog();
        $out = [];
        foreach (MillworkSettings::catalogSlots() as $slot => $_label) {
            $rows = is_array($live[$slot] ?? null) ? $live[$slot] : [];
            $selected = [];
            foreach (self::inputKeysForSlot($slot) as $key) {
                $id = trim((string) ($inputs[$key] ?? ''));
                if ($id !== '') {
                    $selected[$id] = true;
                }
            }
            $have = [];
            foreach ($rows as $row) {
                $have[(string) ($row['id'] ?? '')] = true;
            }
            foreach ($snapshot[$slot] ?? [] as $row) {
                if (! is_array($row)) {
                    continue;
                }
                $id = (string) ($row['id'] ?? '');
                if ($id === '' || isset($have[$id]) || ! isset($selected[$id])) {
                    continue;
                }
                $item = MillworkSettings::normalizeCatalogItem($slot, $row);
                if ($item !== null) {
                    $rows[] = $item;
                    $have[$id] = true;
                }
            }
            $out[$slot] = $rows;
        }

        return $out;
    }

    /**
     * Snapshot of every catalog row referenced by the posted quote inputs.
     *
     * @param  array<string, mixed>  $inputs
     * @param  array<string, list<array<string, mixed>>>  $catalog
     * @return array<string, list<array<string, mixed>>>
     */
    public static function snapshotFromInputs(array $inputs, array $catalog): array
    {
        $out = [];
        foreach (self::inputKeysBySlot() as $slot => $keys) {
            $ids = [];
            foreach ($keys as $key) {
                $id = trim((string) ($inputs[$key] ?? ''));
                if ($id !== '') {
                    $ids[$id] = true;
                }
            }
            $picked = [];
            foreach ($catalog[$slot] ?? [] as $row) {
                if (! is_array($row)) {
                    continue;
                }
                $id = (string) ($row['id'] ?? '');
                if ($id !== '' && isset($ids[$id])) {
                    $item = MillworkSettings::normalizeCatalogItem($slot, $row);
                    if ($item !== null) {
                        unset($item['deleted_at']);
                        $picked[] = $item;
                    }
                }
            }
            if ($picked !== []) {
                $out[$slot] = $picked;
            }
        }

        return $out;
    }

    /**
     * @return array<string, mixed>|null
     */
    public static function findItem(array $catalog, string $slot, string $id): ?array
    {
        if ($id === '') {
            return null;
        }
        foreach ($catalog[$slot] ?? [] as $row) {
            if (is_array($row) && (string) ($row['id'] ?? '') === $id) {
                return $row;
            }
        }

        return null;
    }

    public static function itemIsUsedOnQuotes(string $slot, string $id): bool
    {
        if ($id === '') {
            return false;
        }
        $keys = self::inputKeysForSlot($slot);
        foreach (MillworkQuotation::query()->get(['inputs', 'catalog_snapshot']) as $quote) {
            $inputs = is_array($quote->inputs) ? $quote->inputs : [];
            foreach ($keys as $key) {
                if ((string) ($inputs[$key] ?? '') === $id) {
                    return true;
                }
            }
            $snap = is_array($quote->catalog_snapshot) ? $quote->catalog_snapshot : [];
            foreach ($snap[$slot] ?? [] as $row) {
                if (is_array($row) && (string) ($row['id'] ?? '') === $id) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Replace a slot's visible rows. Used items that disappear are soft-deleted.
     *
     * @param  list<array<string, mixed>>  $posted
     * @return array{items: list<array<string, mixed>>, soft_deleted: list<string>, removed: list<string>}
     */
    public static function replaceSlot(MillworkSettings $settings, string $slot, array $posted): array
    {
        $full = $settings->resolvedCatalog(true);
        $previous = is_array($full[$slot] ?? null) ? $full[$slot] : [];
        $kept = [];
        $postedIds = [];
        foreach ($posted as $row) {
            if (! is_array($row)) {
                continue;
            }
            $item = MillworkSettings::normalizeCatalogItem($slot, $row);
            if ($item === null) {
                continue;
            }
            unset($item['deleted_at']);
            $kept[] = $item;
            $postedIds[(string) $item['id']] = true;
        }

        $softDeleted = [];
        $removed = [];
        foreach ($previous as $row) {
            $id = (string) ($row['id'] ?? '');
            if ($id === '' || isset($postedIds[$id])) {
                continue;
            }
            if (self::isHidden($row)) {
                $kept[] = $row;
                continue;
            }
            if (self::itemIsUsedOnQuotes($slot, $id)) {
                $row['deleted_at'] = now()->toIso8601String();
                $kept[] = $row;
                $softDeleted[] = $id;
            } else {
                $removed[] = $id;
            }
        }

        $catalog = $settings->resolvedCatalog(true);
        $catalog[$slot] = $kept;
        $settings->catalog = $catalog;
        $settings->save();

        return [
            'items' => array_values(array_filter($kept, fn ($row) => ! self::isHidden($row))),
            'soft_deleted' => $softDeleted,
            'removed' => $removed,
        ];
    }

    /**
     * @param  array<string, mixed>  $row
     */
    public static function optionLabel(string $slot, array $row): string
    {
        $name = trim((string) ($row['name'] ?? 'Untitled')) ?: 'Untitled';
        if ($slot === MillworkSettings::SLOT_EDGE_BANDING) {
            $ebCov = (float) ($row['coverage_lf'] ?? 0);
            $hasCovInName = preg_match('/\b\d+(?:\.\d+)?\s*(?:feet|foot|ft|lf)\b/i', $name);
            if ($ebCov > 0 && ! $hasCovInName) {
                $name .= ' ('.rtrim(rtrim(number_format($ebCov, 1, '.', ''), '0'), '.').' LF roll)';
            }

            return $name;
        }
        if (in_array($slot, [MillworkSettings::SLOT_HINGES, MillworkSettings::SLOT_PULLS], true)) {
            $price = (float) ($row['unit_price'] ?? 0);
            if ($price > 0) {
                return $name.' — '.ClientProject::formatMoney($price);
            }
        }

        return $name;
    }

    /**
     * @param  array<string, mixed>  $row
     * @return array<string, mixed>
     */
    public static function optionPayload(string $slot, array $row): array
    {
        $id = (string) ($row['id'] ?? '');
        $payload = [
            'id' => $id,
            'label' => self::optionLabel($slot, $row),
            'name' => (string) ($row['name'] ?? ''),
            'unit_price' => number_format((float) ($row['unit_price'] ?? 0), 2, '.', ''),
        ];
        if (in_array($slot, [MillworkSettings::SLOT_HINGES, MillworkSettings::SLOT_PULLS], true)) {
            $payload['price'] = $payload['unit_price'];
        }

        return $payload;
    }

    /**
     * Field schema for the manage modal.
     *
     * @return list<array{key: string, label: string, type: string, step?: string, min?: string, options?: array<string, string>}>
     */
    public static function fieldSchema(string $slot): array
    {
        $fields = [
            ['key' => 'name', 'label' => 'Name', 'type' => 'text'],
            ['key' => 'unit_price', 'label' => 'Price', 'type' => 'money'],
        ];
        if ($slot === MillworkSettings::SLOT_WOOD_SHEETS) {
            $fields[] = ['key' => 'thickness_in', 'label' => 'Thickness (in)', 'type' => 'number', 'step' => '0.001', 'min' => '0'];
            $fields[] = ['key' => 'width_in', 'label' => 'Width (in)', 'type' => 'number', 'step' => '0.1', 'min' => '1'];
            $fields[] = ['key' => 'length_in', 'label' => 'Length (in)', 'type' => 'number', 'step' => '0.1', 'min' => '1'];
            $fields[] = ['key' => 'material_kind', 'label' => 'Kind', 'type' => 'select', 'options' => ['plywood' => 'Plywood', 'mdf' => 'MDF']];
            $fields[] = ['key' => 'grade', 'label' => 'Grade', 'type' => 'select', 'options' => ['cabinet' => 'Cabinet', 'utility' => 'Utility']];
        } elseif ($slot === MillworkSettings::SLOT_PAINTS || $slot === MillworkSettings::SLOT_STAINS) {
            $fields[] = ['key' => 'size', 'label' => 'Size', 'type' => 'text'];
            $fields[] = ['key' => 'coverage_sf', 'label' => 'Coverage (sq ft)', 'type' => 'number', 'step' => '0.1', 'min' => '0'];
        } elseif ($slot === MillworkSettings::SLOT_HINGES) {
            $fields[] = ['key' => 'hinge_style', 'label' => 'Style', 'type' => 'select', 'options' => [
                'overlay' => 'Overlay',
                'inset' => 'Inset',
                'soft_close' => 'Soft-close',
            ]];
        } elseif ($slot === MillworkSettings::SLOT_EDGE_BANDING) {
            $fields[] = ['key' => 'thickness_in', 'label' => 'Thickness (in)', 'type' => 'number', 'step' => '0.001', 'min' => '0'];
            $fields[] = ['key' => 'coverage_lf', 'label' => 'Coverage (LF)', 'type' => 'number', 'step' => '0.1', 'min' => '0.1'];
        } elseif (in_array($slot, [MillworkSettings::SLOT_SCREWS, MillworkSettings::SLOT_NAILS, MillworkSettings::SLOT_SHELF_PINS], true)) {
            $fields[] = ['key' => 'qty_per_pack', 'label' => 'Qty / pack', 'type' => 'number', 'step' => '1', 'min' => '1'];
        } elseif ($slot === MillworkSettings::SLOT_TRIM) {
            $fields[] = ['key' => 'profile', 'label' => 'Profile', 'type' => 'select', 'options' => MillworkPartBuilder::TRIM_PROFILES];
            $fields[] = ['key' => 'stock_length_ft', 'label' => 'Stock length (ft)', 'type' => 'number', 'step' => '0.1', 'min' => '1'];
        }

        return $fields;
    }

    /**
     * @param  list<array<string, mixed>>  $items
     * @return array<string, mixed>
     */
    public static function slotPayload(string $slot, array $items): array
    {
        $visible = [];
        foreach ($items as $row) {
            if (! is_array($row) || self::isHidden($row)) {
                continue;
            }
            $visible[] = $row;
        }

        return [
            'slot' => $slot,
            'label' => MillworkSettings::catalogSlots()[$slot] ?? $slot,
            'fields' => self::fieldSchema($slot),
            'items' => $visible,
            'options' => array_map(fn (array $row) => self::optionPayload($slot, $row), $visible),
        ];
    }
}

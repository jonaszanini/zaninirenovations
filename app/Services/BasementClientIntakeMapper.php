<?php

namespace App\Services;

use InvalidArgumentException;

/**
 * Maps a constrained public client form onto existing basement quotation inputs.
 * Prices, extras, and labor overrides are never accepted from the client.
 */
class BasementClientIntakeMapper
{
    public const PRESETS = ['full', 'bathroom'];

    /**
     * @param  array<string, mixed>  $answers
     * @param  array<string, mixed>  $existing
     * @return array{inputs: array<string, mixed>, filled_keys: list<string>}
     */
    public function apply(array $answers, array $existing): array
    {
        $existing = array_merge(BasementQuotationCalculator::defaultInputs(), $existing);
        $preset = BasementQuotationCalculator::normalizePreset($existing['scope_preset'] ?? 'full');
        if (! in_array($preset, self::PRESETS, true)) {
            throw new InvalidArgumentException('Client intake is only available for full basement and bathroom-only quotations.');
        }

        $out = $existing;
        $filled = [];
        $mark = function (string $key) use (&$filled): void {
            if (! in_array($key, $filled, true)) {
                $filled[] = $key;
            }
        };

        $this->applySize($answers, $out, $mark);

        $includeBathroom = $preset === 'bathroom' ? true : $this->bool($answers, 'include_bathroom', false);
        $out['include_bathroom'] = $includeBathroom;
        $mark('include_bathroom');

        if ($preset === 'full') {
            $this->applyFullBasement($answers, $out, $mark);
        }

        if ($includeBathroom) {
            $this->applyBathroom($answers, $out, $mark);
        }

        $out['scope_preset'] = $preset;
        $out = BasementQuotationCalculator::applyAreaEntry($out);
        $out['client_provides_bath_items'] = BasementQuotationCalculator::normalizeClientProvidesBathItems($out);
        $mark('area_sqft');

        return [
            'inputs' => $out,
            'filled_keys' => $filled,
        ];
    }

    /**
     * Values for the public form, derived from saved quotation inputs.
     *
     * @param  array<string, mixed>  $inputs
     * @return array<string, mixed>
     */
    public function formDefaults(array $inputs): array
    {
        $inputs = array_merge(BasementQuotationCalculator::defaultInputs(), $inputs);
        $preset = BasementQuotationCalculator::normalizePreset($inputs['scope_preset'] ?? 'full');
        $provides = is_array($inputs['client_provides_bath_items'] ?? null)
            ? $inputs['client_provides_bath_items']
            : [];
        $sectionProvides = is_array($inputs['client_provides_sections'] ?? null)
            ? $inputs['client_provides_sections']
            : [];

        $sink = 'vanity';
        if (! empty($inputs['include_pedestal_sink'])) {
            $sink = 'pedestal';
        } elseif (empty($inputs['include_vanity'])) {
            $sink = 'none';
        }

        $areas = is_array($inputs['bath_tile_areas'] ?? null) ? $inputs['bath_tile_areas'] : [];
        $floor = $this->namedTileArea($areas, 'Floor');
        $shower = $this->namedTileArea($areas, 'Shower');

        return [
            'scope_preset' => $preset,
            'area_entry_mode' => BasementQuotationCalculator::normalizeAreaMode($inputs['area_entry_mode'] ?? 'sqft'),
            'area_sqft' => $inputs['area_sqft'] ?? '',
            'area_length_ft' => $inputs['area_length_ft'] ?? '',
            'area_width_ft' => $inputs['area_width_ft'] ?? '',
            'ceiling_height_ft' => $inputs['ceiling_height_ft'] ?? 8,
            'room_count' => $inputs['room_count'] ?? 1,
            'include_bathroom' => ! empty($inputs['include_bathroom']) || $preset === 'bathroom',
            'include_demolition' => ! empty($inputs['include_demolition']),
            'partition_lf' => $inputs['partition_lf'] ?? 0,
            'outlet_qty' => $inputs['outlet_qty'] ?? 0,
            'switch_qty' => $inputs['switch_qty'] ?? 0,
            'lighting_type' => $inputs['lighting_type'] ?? 'spot',
            'light_qty' => $inputs['light_qty'] ?? 0,
            'fixture_qty' => $inputs['fixture_qty'] ?? 0,
            'panel_upgrade' => ! empty($inputs['panel_upgrade']),
            'bath_rough_in' => ! empty($inputs['bath_rough_in']),
            'sink_laundry' => ! empty($inputs['sink_laundry']),
            'wet_bar' => ! empty($inputs['wet_bar']),
            'floor_drain' => ! empty($inputs['floor_drain']),
            'wh_reloc' => ! empty($inputs['wh_reloc']),
            'insulate_walls' => ! empty($inputs['insulate_walls']),
            'insulate_ceiling' => ! empty($inputs['insulate_ceiling']),
            'underslab' => ! empty($inputs['underslab']),
            'ceiling_type' => $inputs['ceiling_type'] ?? 'drywall',
            'flooring_type' => $inputs['flooring_type'] ?? 'lvp',
            'flooring_provide' => in_array('flooring', $sectionProvides, true) ? 'client' : 'we',
            'underlayment' => ! empty($inputs['underlayment']),
            'subfloor_prep' => ! empty($inputs['subfloor_prep']),
            'door_qty' => $inputs['door_qty'] ?? 0,
            'paint_colors' => ((int) ($inputs['paint_colors'] ?? 1) === 2) ? 2 : 1,
            'bath_type' => ($inputs['bath_type'] ?? 'full') === 'half' ? 'half' : 'full',
            'sink' => $sink,
            'sink_provide' => $this->provideFlag($provides, $sink === 'pedestal' ? 'pedestal_sink' : 'vanity'),
            'faucet_provide' => $this->provideFlag($provides, 'faucet'),
            'include_toilet' => ! empty($inputs['include_toilet']),
            'toilet_provide' => $this->provideFlag($provides, 'toilet'),
            'include_shower' => ! empty($inputs['include_shower']),
            'shower_provide' => $this->provideFlag($provides, 'shower_frame'),
            'showerhead_provide' => $this->provideFlag($provides, 'showerhead'),
            'include_tub' => ! empty($inputs['include_tub']),
            'tub_provide' => $this->provideFlag($provides, 'tub'),
            'include_exhaust_fan' => ! empty($inputs['include_exhaust_fan']),
            'include_bath_fixture' => ! empty($inputs['include_bath_fixture']),
            'bath_fixture_qty' => $inputs['bath_fixture_qty'] ?? 1,
            'include_bath_spot' => ! empty($inputs['include_bath_spot']),
            'bath_spot_qty' => $inputs['bath_spot_qty'] ?? 0,
            'tile_floor_mode' => $floor['mode'],
            'tile_floor_sqft' => $floor['sqft'],
            'tile_floor_length_ft' => $floor['length_ft'],
            'tile_floor_width_ft' => $floor['width_ft'],
            'tile_floor_finish' => $floor['finish'],
            'tile_shower_mode' => $shower['mode'],
            'tile_shower_sqft' => $shower['sqft'],
            'tile_shower_length_ft' => $shower['length_ft'],
            'tile_shower_width_ft' => $shower['width_ft'],
            'tile_shower_finish' => $shower['finish'],
            'include_cleaning' => ! empty($inputs['include_cleaning']),
            'include_touchups' => ! empty($inputs['include_touchups']),
        ];
    }

    /**
     * @param  array<string, mixed>  $answers
     * @param  array<string, mixed>  $out
     * @param  callable(string): void  $mark
     */
    private function applySize(array $answers, array &$out, callable $mark): void
    {
        $mode = BasementQuotationCalculator::normalizeAreaMode($answers['area_entry_mode'] ?? 'sqft');
        $out['area_entry_mode'] = $mode;
        $out['area_sqft'] = $this->float($answers, 'area_sqft');
        $out['area_length_ft'] = $this->optionalFloat($answers, 'area_length_ft');
        $out['area_width_ft'] = $this->optionalFloat($answers, 'area_width_ft');
        $mark('area_entry_mode');
        $mark('area_length_ft');
        $mark('area_width_ft');
    }

    /**
     * @param  array<string, mixed>  $answers
     * @param  array<string, mixed>  $out
     * @param  callable(string): void  $mark
     */
    private function applyFullBasement(array $answers, array &$out, callable $mark): void
    {
        $out['ceiling_height_ft'] = max(0, $this->float($answers, 'ceiling_height_ft', 8));
        $out['room_count'] = max(0, $this->int($answers, 'room_count', 1));
        $out['include_demolition'] = $this->bool($answers, 'include_demolition');
        $out['partition_lf'] = max(0, $this->float($answers, 'partition_lf'));
        $out['outlet_qty'] = max(0, $this->int($answers, 'outlet_qty'));
        $out['switch_qty'] = max(0, $this->int($answers, 'switch_qty'));
        $lighting = strtolower(trim((string) ($answers['lighting_type'] ?? 'spot')));
        $out['lighting_type'] = in_array($lighting, ['fixture', 'spot', 'both'], true) ? $lighting : 'spot';
        $out['light_qty'] = max(0, $this->int($answers, 'light_qty'));
        $out['fixture_qty'] = max(0, $this->int($answers, 'fixture_qty'));
        if ($out['lighting_type'] === 'fixture') {
            $out['fixture_qty'] = $out['light_qty'];
            $out['light_qty'] = 0;
        } elseif ($out['lighting_type'] === 'spot') {
            $out['fixture_qty'] = 0;
        }
        $out['panel_upgrade'] = $this->bool($answers, 'panel_upgrade');

        $out['bath_rough_in'] = $this->bool($answers, 'bath_rough_in');
        $out['sink_laundry'] = $this->bool($answers, 'sink_laundry');
        $out['wet_bar'] = $this->bool($answers, 'wet_bar');
        $out['floor_drain'] = $this->bool($answers, 'floor_drain');
        $out['wh_reloc'] = $this->bool($answers, 'wh_reloc');
        $out['include_plumbing'] = $out['bath_rough_in'] || $out['sink_laundry'] || $out['wet_bar']
            || $out['floor_drain'] || $out['wh_reloc'];

        $out['insulate_walls'] = $this->bool($answers, 'insulate_walls', true);
        $out['insulate_ceiling'] = $this->bool($answers, 'insulate_ceiling');
        $out['underslab'] = $this->bool($answers, 'underslab');

        $ceiling = strtolower(trim((string) ($answers['ceiling_type'] ?? 'drywall')));
        $out['ceiling_type'] = in_array($ceiling, ['drywall', 'drop', 'none', 'paint'], true) ? $ceiling : 'drywall';

        $flooring = strtolower(trim((string) ($answers['flooring_type'] ?? 'lvp')));
        $out['flooring_type'] = in_array($flooring, ['lvp', 'laminate', 'tile', 'carpet', 'epoxy', 'none'], true)
            ? $flooring
            : 'lvp';
        $out['underlayment'] = $this->bool($answers, 'underlayment', true);
        $out['subfloor_prep'] = $this->bool($answers, 'subfloor_prep');
        $out['door_qty'] = max(0, $this->int($answers, 'door_qty'));
        $out['paint_colors'] = $this->int($answers, 'paint_colors', 1) === 2 ? 2 : 1;

        $sections = is_array($out['client_provides_sections'] ?? null) ? $out['client_provides_sections'] : [];
        $sections = array_values(array_filter($sections, function ($key) {
            return $key !== 'flooring';
        }));
        if ($out['flooring_type'] !== 'none' && $this->provide($answers, 'flooring_provide') === 'client') {
            $sections[] = 'flooring';
        }
        $out['client_provides_sections'] = array_values(array_unique($sections));

        foreach ([
            'ceiling_height_ft', 'room_count', 'include_demolition', 'partition_lf',
            'outlet_qty', 'switch_qty', 'lighting_type', 'light_qty', 'fixture_qty', 'panel_upgrade',
            'include_plumbing', 'bath_rough_in', 'sink_laundry', 'wet_bar', 'floor_drain', 'wh_reloc',
            'insulate_walls', 'insulate_ceiling', 'underslab', 'ceiling_type',
            'flooring_type', 'underlayment', 'subfloor_prep', 'door_qty', 'paint_colors',
            'client_provides_sections',
        ] as $key) {
            $mark($key);
        }
    }

    /**
     * @param  array<string, mixed>  $answers
     * @param  array<string, mixed>  $out
     * @param  callable(string): void  $mark
     */
    private function applyBathroom(array $answers, array &$out, callable $mark): void
    {
        $half = strtolower(trim((string) ($answers['bath_type'] ?? 'full'))) === 'half';
        $out['bath_type'] = $half ? 'half' : 'full';
        $mark('bath_type');

        $sink = strtolower(trim((string) ($answers['sink'] ?? '')));
        if (in_array($sink, ['vanity', 'pedestal', 'none'], true)) {
            $out['include_vanity'] = $sink === 'vanity';
            $out['include_pedestal_sink'] = $sink === 'pedestal';
        } else {
            $out['include_vanity'] = $this->bool($answers, 'include_vanity');
            $out['include_pedestal_sink'] = $this->bool($answers, 'include_pedestal_sink');
        }
        $out = BasementQuotationCalculator::normalizeBathSinkChoice($out);
        if (! empty($out['include_pedestal_sink'])) {
            $sink = 'pedestal';
        } elseif (! empty($out['include_vanity'])) {
            $sink = 'vanity';
        } else {
            $sink = 'none';
        }
        $mark('include_vanity');
        $mark('include_pedestal_sink');

        $out['include_toilet'] = $this->bool($answers, 'include_toilet', true);
        $out['include_shower'] = $half ? false : $this->bool($answers, 'include_shower', true);
        $out['include_tub'] = $half ? false : $this->bool($answers, 'include_tub');
        $out['include_exhaust_fan'] = $this->bool($answers, 'include_exhaust_fan', true);
        $out['include_bath_fixture'] = $this->bool($answers, 'include_bath_fixture');
        $out['bath_fixture_qty'] = $out['include_bath_fixture'] ? max(1, $this->int($answers, 'bath_fixture_qty', 1)) : 0;
        $out['include_bath_spot'] = $this->bool($answers, 'include_bath_spot');
        $out['bath_spot_qty'] = $out['include_bath_spot'] ? max(1, $this->int($answers, 'bath_spot_qty', 1)) : 0;
        $out['include_cleaning'] = $this->bool($answers, 'include_cleaning', true);
        $out['include_touchups'] = $this->bool($answers, 'include_touchups', true);

        foreach ([
            'include_toilet', 'include_shower', 'include_tub', 'include_exhaust_fan',
            'include_bath_fixture', 'bath_fixture_qty', 'include_bath_spot', 'bath_spot_qty',
            'include_cleaning', 'include_touchups',
        ] as $key) {
            $mark($key);
        }

        $provides = [];
        if ($sink === 'vanity' && $this->provide($answers, 'sink_provide') === 'client') {
            $provides[] = 'vanity';
        }
        if ($sink === 'pedestal' && $this->provide($answers, 'sink_provide') === 'client') {
            $provides[] = 'pedestal_sink';
        }
        if ($sink !== 'none' && $this->provide($answers, 'faucet_provide') === 'client') {
            $provides[] = 'faucet';
        }
        if ($out['include_toilet'] && $this->provide($answers, 'toilet_provide') === 'client') {
            $provides[] = 'toilet';
        }
        if ($out['include_shower'] && $this->provide($answers, 'shower_provide') === 'client') {
            $provides[] = 'shower_frame';
        }
        if ($out['include_shower'] && $this->provide($answers, 'showerhead_provide') === 'client') {
            $provides[] = 'showerhead';
        }
        if ($out['include_tub'] && $this->provide($answers, 'tub_provide') === 'client') {
            $provides[] = 'tub';
        }
        $out['client_provides_bath_items'] = $provides;
        $mark('client_provides_bath_items');

        $areas = [];
        $areas[] = $this->tileAreaFromAnswers($answers, 'tile_floor', 'Floor');
        if (! $half && $out['include_shower']) {
            $areas[] = $this->tileAreaFromAnswers($answers, 'tile_shower', 'Shower');
        }
        $out['bath_tile_areas'] = $areas;
        $mark('bath_tile_areas');

        $preset = BasementQuotationCalculator::normalizePreset($out['scope_preset'] ?? 'full');
        if ($preset === 'bathroom') {
            $out['bath_rough_in'] = $this->bool($answers, 'bath_rough_in', true);
            $out['include_plumbing'] = ! empty($out['bath_rough_in']);
            $mark('bath_rough_in');
            $mark('include_plumbing');
        }
    }

    /**
     * @param  array<string, mixed>  $answers
     * @return array{label: string, sqft: float, mode: string, length_ft: float|null, width_ft: float|null, finish: string}
     */
    private function tileAreaFromAnswers(array $answers, string $prefix, string $label): array
    {
        $mode = BasementQuotationCalculator::normalizeAreaMode($answers[$prefix.'_mode'] ?? 'sqft');
        $finish = BasementQuotationCalculator::normalizeBathTileFinish($answers[$prefix.'_finish'] ?? 'tile');
        $len = $this->optionalFloat($answers, $prefix.'_length_ft');
        $wid = $this->optionalFloat($answers, $prefix.'_width_ft');
        $sqft = $this->float($answers, $prefix.'_sqft');
        if ($mode === 'dimensions' && $len !== null && $wid !== null && $len > 0 && $wid > 0) {
            $sqft = round($len * $wid, 2);
        }

        return [
            'label' => $label,
            'sqft' => $sqft,
            'mode' => $mode,
            'length_ft' => $len,
            'width_ft' => $wid,
            'finish' => $finish,
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $areas
     * @return array{sqft: float|string, mode: string, length_ft: float|string, width_ft: float|string, finish: string}
     */
    private function namedTileArea(array $areas, string $label): array
    {
        $blank = [
            'sqft' => '',
            'mode' => 'sqft',
            'length_ft' => '',
            'width_ft' => '',
            'finish' => $label === 'Floor' ? 'tile' : 'tile',
        ];
        foreach ($areas as $row) {
            if (! is_array($row)) {
                continue;
            }
            if (strcasecmp(trim((string) ($row['label'] ?? '')), $label) !== 0) {
                continue;
            }

            return [
                'sqft' => $row['sqft'] ?? '',
                'mode' => BasementQuotationCalculator::normalizeAreaMode($row['mode'] ?? 'sqft'),
                'length_ft' => $row['length_ft'] ?? '',
                'width_ft' => $row['width_ft'] ?? '',
                'finish' => BasementQuotationCalculator::normalizeBathTileFinish($row['finish'] ?? 'tile'),
            ];
        }

        return $blank;
    }

    /**
     * @param  list<string>  $provides
     */
    private function provideFlag(array $provides, string $item): string
    {
        return in_array($item, $provides, true) ? 'client' : 'we';
    }

    /**
     * @param  array<string, mixed>  $answers
     */
    private function provide(array $answers, string $key): string
    {
        return strtolower(trim((string) ($answers[$key] ?? 'we'))) === 'client' ? 'client' : 'we';
    }

    /**
     * @param  array<string, mixed>  $answers
     */
    private function bool(array $answers, string $key, bool $default = false): bool
    {
        if (! array_key_exists($key, $answers)) {
            return $default;
        }
        $value = $answers[$key];
        if (is_bool($value)) {
            return $value;
        }

        return in_array(strtolower(trim((string) $value)), ['1', 'true', 'yes', 'on'], true);
    }

    /**
     * @param  array<string, mixed>  $answers
     */
    private function int(array $answers, string $key, int $default = 0): int
    {
        if (! array_key_exists($key, $answers) || $answers[$key] === '' || $answers[$key] === null) {
            return $default;
        }

        return (int) $answers[$key];
    }

    /**
     * @param  array<string, mixed>  $answers
     */
    private function float(array $answers, string $key, float $default = 0.0): float
    {
        if (! array_key_exists($key, $answers) || $answers[$key] === '' || $answers[$key] === null) {
            return $default;
        }

        return round(max(0, (float) $answers[$key]), 4);
    }

    /**
     * @param  array<string, mixed>  $answers
     */
    private function optionalFloat(array $answers, string $key): ?float
    {
        if (! array_key_exists($key, $answers) || $answers[$key] === '' || $answers[$key] === null) {
            return null;
        }

        return round(max(0, (float) $answers[$key]), 4);
    }
}

<?php

namespace App\Services;

use App\Models\ClientProject;
use App\Models\StairsMaterialType;
use App\Models\StairsQuotation;
use App\Models\StairsSettings;
use Illuminate\Support\Collection;

class StairsQuotationCalculator
{
    public const OPEN_NONE = 'none';

    public const OPEN_ONE = 'one';

    public const OPEN_TWO = 'two';

    private static function moneyPerStep(float $n): string
    {
        return ClientProject::formatMoney($n).'/step';
    }

    /** @return list<string> */
    public static function allowedStairTypes(string $openSides): array
    {
        return $openSides === self::OPEN_TWO
            ? ['standard']
            : ['standard', 'winder', 'double_landing', 'single_landing'];
    }

    public static function openSidesPricingKey(string $openSides): string
    {
        return match ($openSides) {
            self::OPEN_ONE => 'one_open',
            self::OPEN_TWO => 'two_open',
            default => 'no_open',
        };
    }

    /** @return list<string> */
    public static function allowedOpenSidesForMaterial(?StairsMaterialType $material): array
    {
        if ($material && ! $material->supportsOpenSidePricing()) {
            return [self::OPEN_NONE];
        }

        return [self::OPEN_NONE, self::OPEN_ONE, self::OPEN_TWO];
    }

    public static function openSidesPricingKeyForMaterial(string $openSides, ?StairsMaterialType $material): string
    {
        if ($material && ! $material->supportsOpenSidePricing()) {
            return 'no_open';
        }

        return self::openSidesPricingKey($openSides);
    }

    /**
     * Per-segment open layout; missing segment value falls back to legacy top-level inputs (old quotes).
     *
     * @param  array<string, mixed>  $segment
     */
    public static function normalizeSegmentOpenSides(array $segment, ?string $legacyTopLevelOpenSides = null): string
    {
        $raw = $segment['open_sides'] ?? null;
        if (is_string($raw) && in_array($raw, [self::OPEN_NONE, self::OPEN_ONE, self::OPEN_TWO], true)) {
            return $raw;
        }
        if (is_string($legacyTopLevelOpenSides) && in_array($legacyTopLevelOpenSides, [self::OPEN_NONE, self::OPEN_ONE, self::OPEN_TWO], true)) {
            return $legacyTopLevelOpenSides;
        }

        return self::OPEN_NONE;
    }

    public static function normalizeSegmentOpenSidesForMaterial(
        array $segment,
        ?StairsMaterialType $material,
        ?string $legacyTopLevelOpenSides = null
    ): string {
        $open = self::normalizeSegmentOpenSides($segment, $legacyTopLevelOpenSides);
        $allowed = self::allowedOpenSidesForMaterial($material);

        return in_array($open, $allowed, true) ? $open : self::OPEN_NONE;
    }

    public static function openSidesLabel(string $openSides): string
    {
        return match ($openSides) {
            self::OPEN_ONE => 'One open side',
            self::OPEN_TWO => 'Two open sides',
            default => 'No open sides',
        };
    }

    public static function stairTypeLabel(string $type): string
    {
        return match ($type) {
            'winder' => 'Winder step',
            'double_landing' => 'Double landing',
            'single_landing' => 'Single landing',
            default => 'Standard stairs',
        };
    }

    /**
     * @param  array<string, mixed>  $inputs
     * @return array<string, mixed>
     */
    public function calculate(array $inputs, ?Collection $materials = null, ?StairsSettings $settings = null): array
    {
        $materials = $materials ?? StairsMaterialType::query()->orderBy('sort_order')->get();
        $settings = $settings ?? StairsSettings::current();

        $totalSteps = max(0, (int) ($inputs['total_steps'] ?? 0));
        $carpetRemoval = filter_var($inputs['carpet_removal'] ?? false, FILTER_VALIDATE_BOOLEAN);
        $legacyOpenSides = $inputs['open_sides'] ?? null;
        $legacyOpenSides = is_string($legacyOpenSides) && in_array($legacyOpenSides, [self::OPEN_NONE, self::OPEN_ONE, self::OPEN_TWO], true)
            ? $legacyOpenSides
            : null;
        $materialId = (int) ($inputs['material_type_id'] ?? 0);
        /** @var StairsMaterialType|null $material */
        $material = $materialId > 0 ? $materials->firstWhere('id', $materialId) : null;
        $nosingChoice = trim((string) ($inputs['nosing_choice'] ?? ''));
        $requiresStain = filter_var($inputs['requires_stain'] ?? false, FILTER_VALIDATE_BOOLEAN);
        $requiresClearCoat = filter_var($inputs['requires_clear_coat'] ?? false, FILTER_VALIDATE_BOOLEAN);

        $segments = $inputs['segments'] ?? [];
        if (! is_array($segments)) {
            $segments = [];
        }

        $pgrid = $material ? $material->mergedPricing() : StairsMaterialType::defaultPricingArray();

        $breakdown = [];
        $subtotalPreHst = 0.0;

        if ($carpetRemoval && $totalSteps > 0) {
            $rate = (float) $settings->carpet_removal_per_step;
            $amt = round($rate * $totalSteps, 2);
            $subtotalPreHst += $amt;
            $breakdown[] = [
                'key' => 'carpet_removal',
                'label' => 'Carpet removal',
                'detail' => $totalSteps.' steps × '.self::moneyPerStep($rate),
                'amount' => $amt,
            ];
        }

        $configuredSteps = 0;
        foreach ($segments as $idx => $seg) {
            if (! is_array($seg)) {
                continue;
            }
            $segOpen = self::normalizeSegmentOpenSidesForMaterial($seg, $material, $legacyOpenSides);
            $allowed = self::allowedStairTypes($segOpen);
            $stype = (string) ($seg['stair_type'] ?? 'standard');
            if (! in_array($stype, $allowed, true)) {
                continue;
            }
            $qty = max(0, (int) ($seg['qty'] ?? 0));
            if ($qty <= 0) {
                continue;
            }
            if (! $material) {
                continue;
            }
            $pricingKey = self::openSidesPricingKeyForMaterial($segOpen, $material);
            $sidePrices = $pgrid[$pricingKey] ?? [];
            $unit = (float) ($sidePrices[$stype] ?? 0);
            $amt = round($unit * $qty, 2);
            $subtotalPreHst += $amt;
            $configuredSteps += $qty;
            $detail = $qty.' steps × '.self::moneyPerStep($unit);
            if ($material->supportsOpenSidePricing()) {
                $detail = self::openSidesLabel($segOpen).' — '.$detail;
            }
            $breakdown[] = [
                'key' => 'stairs_'.$idx,
                'label' => self::stairTypeLabel($stype),
                'detail' => $detail,
                'amount' => $amt,
            ];
        }

        if ($material && $material->nosing_mode === StairsMaterialType::NOSING_CHOICE && $nosingChoice !== '') {
            $valid = array_keys(StairsMaterialType::nosingChoiceLabels());
            if (in_array($nosingChoice, $valid, true)) {
                $nosingPrices = $pgrid['nosing_prices'] ?? [];
                $unit = (float) ($nosingPrices[$nosingChoice] ?? 0);
                if ($unit > 0 && $totalSteps > 0) {
                    $nosingAmount = round($unit * $totalSteps, 2);
                    $subtotalPreHst += $nosingAmount;
                    $nlabel = StairsMaterialType::nosingChoiceLabels()[$nosingChoice] ?? $nosingChoice;
                    $breakdown[] = [
                        'key' => 'nosing_extra',
                        'label' => 'Stair nosing: '.$nlabel,
                        'detail' => $totalSteps.' steps × '.self::moneyPerStep($unit),
                        'amount' => $nosingAmount,
                    ];
                }
            }
        }

        $stainRate = (float) $settings->stain_per_step;
        if ($requiresStain && $stainRate > 0 && $totalSteps > 0) {
            $amt = round($stainRate * $totalSteps, 2);
            $subtotalPreHst += $amt;
            $breakdown[] = [
                'key' => 'stain',
                'label' => 'Stain',
                'detail' => $totalSteps.' steps × '.self::moneyPerStep($stainRate),
                'amount' => $amt,
            ];
        }

        $clearRate = (float) $settings->clear_coat_per_step;
        if ($requiresClearCoat && $clearRate > 0 && $totalSteps > 0) {
            $amt = round($clearRate * $totalSteps, 2);
            $subtotalPreHst += $amt;
            $breakdown[] = [
                'key' => 'clear_coat',
                'label' => 'Clear coat protection',
                'detail' => $totalSteps.' steps × '.self::moneyPerStep($clearRate),
                'amount' => $amt,
            ];
        }

        $labourSubtotalPreHst = round($subtotalPreHst, 2);
        $materialPrice = max(0, round((float) ($inputs['material_price'] ?? 0), 2));
        if ($materialPrice > 0) {
            $subtotalPreHst += $materialPrice;
            $breakdown[] = [
                'key' => 'materials',
                'label' => 'Materials',
                'detail' => 'Materials supplied',
                'amount' => $materialPrice,
            ];
        }

        $subtotalPreHst = round($subtotalPreHst, 2);
        $hst = round($subtotalPreHst * StairsQuotation::HST_RATE, 2);
        $grand = round($subtotalPreHst + $hst, 2);

        return [
            'summary' => [
                'subtotal_pre_hst' => $subtotalPreHst,
                'labour_subtotal_pre_hst' => $labourSubtotalPreHst,
                'material_price' => $materialPrice,
                'hst' => $hst,
                'hst_rate' => StairsQuotation::HST_RATE,
                'grand_total' => $grand,
                'final_quote_total' => $grand,
                'total_steps' => $totalSteps,
                'configured_steps' => $configuredSteps,
            ],
            'breakdown' => $breakdown,
        ];
    }

    /**
     * @param  array<string, mixed>  $inputs
     * @return list<string>
     */
    public function validationHints(array $inputs, ?Collection $materials = null, bool $draft = false): array
    {
        $materials = $materials ?? StairsMaterialType::query()->orderBy('sort_order')->get();
        $errors = [];
        $totalSteps = (int) ($inputs['total_steps'] ?? 0);
        if (! $draft && $totalSteps < 1) {
            $errors[] = 'Enter at least one total step.';
        }
        $legacyOpenSides = $inputs['open_sides'] ?? null;
        $legacyOpenSides = is_string($legacyOpenSides) && in_array($legacyOpenSides, [self::OPEN_NONE, self::OPEN_ONE, self::OPEN_TWO], true)
            ? $legacyOpenSides
            : null;

        $materialId = (int) ($inputs['material_type_id'] ?? 0);
        $material = $materialId > 0 ? $materials->firstWhere('id', $materialId) : null;
        if (! $draft && ! $material) {
            $errors[] = 'Select a stair material.';
        }

        if ($material && $material->nosing_mode === StairsMaterialType::NOSING_CHOICE && ! $draft) {
            $nosing = trim((string) ($inputs['nosing_choice'] ?? ''));
            if ($nosing === '') {
                $errors[] = 'Select a stair nosing option for this material.';
            }
        }

        $segments = $inputs['segments'] ?? [];
        if (! is_array($segments)) {
            $segments = [];
        }
        $sumQty = 0;
        $hasRow = false;
        foreach ($segments as $seg) {
            if (! is_array($seg)) {
                continue;
            }
            $segOpen = self::normalizeSegmentOpenSidesForMaterial($seg, $material, $legacyOpenSides);
            $allowed = self::allowedStairTypes($segOpen);
            $stype = (string) ($seg['stair_type'] ?? 'standard');
            $qty = max(0, (int) ($seg['qty'] ?? 0));
            if ($qty < 1) {
                continue;
            }
            $hasRow = true;
            if (! in_array($stype, $allowed, true)) {
                $errors[] = $material && ! $material->supportsOpenSidePricing()
                    ? 'One or more stair types are not valid for that section.'
                    : 'One or more stair types are not valid for their open sides on that section.';

                break;
            }
            $sumQty += $qty;
        }
        if (! $draft && ! $hasRow) {
            $errors[] = 'Add at least one stair configuration with a step quantity.';
        }
        if (! $draft && $hasRow && $sumQty !== $totalSteps) {
            $errors[] = 'The sum of step quantities ('.$sumQty.') must equal total steps ('.$totalSteps.').';
        }

        return $errors;
    }

    /** @return array<string, mixed> */
    public static function defaultInputs(): array
    {
        return [
            'total_steps' => 1,
            'carpet_removal' => false,
            'segments' => [
                ['stair_type' => 'standard', 'qty' => 1, 'open_sides' => self::OPEN_NONE],
            ],
            'material_type_id' => null,
            'nosing_choice' => '',
            'requires_stain' => false,
            'requires_clear_coat' => false,
            'material_price' => 0.0,
        ];
    }
}

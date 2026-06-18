<?php

namespace App\Services;

use App\Models\ClientProject;
use App\Models\FlooringQuotation;
use App\Models\FlooringSettings;
use App\Models\FlooringType;
use Illuminate\Support\Collection;

class FlooringQuotationCalculator
{
    /**
     * @return array<string, mixed>
     */
    public static function defaultInputs(): array
    {
        $defaultTypeId = FlooringType::query()->activeOrdered()->value('id');

        return [
            'flooring_type_id' => $defaultTypeId ? (int) $defaultTypeId : null,
            'square_footage' => 0,
            'remove_carpet' => false,
            'rescrew_subfloor' => false,
            'install_shoe_moulding' => false,
            'shoe_moulding_linear_ft' => 0,
            'caulk_paint_shoe_moulding' => false,
            'closet_doors' => false,
            'closet_door_qty' => 0,
            'include_material_details' => false,
            'material_cost_per_sqft' => 0,
            'material_waste_percent' => 5,
            'material_delivery_fee' => 60,
            'material_name' => '',
            'material_color' => '',
            'material_url' => '',
        ];
    }

    /**
     * @param  array<string, mixed>  $inputs
     * @return list<string>
     */
    public function validationHints(array $inputs, bool $draft = false): array
    {
        if ($draft) {
            return [];
        }

        $hints = [];
        $sqft = max(0, (float) ($inputs['square_footage'] ?? 0));
        if ($sqft <= 0) {
            $hints[] = 'Enter the total square footage.';
        }

        $typeId = (int) ($inputs['flooring_type_id'] ?? 0);
        if ($typeId <= 0 || ! FlooringType::query()->whereKey($typeId)->exists()) {
            $hints[] = 'Select a flooring type.';
        }

        if (
            (! empty($inputs['install_shoe_moulding']) || ! empty($inputs['caulk_paint_shoe_moulding']))
            && $this->shoeMouldingLinearFeet($inputs) <= 0
        ) {
            $hints[] = 'Enter shoe moulding linear feet in Core measurements when install or caulk & paint is selected.';
        }

        if (! empty($inputs['closet_doors']) && max(0, (int) ($inputs['closet_door_qty'] ?? 0)) <= 0) {
            $hints[] = 'Enter the number of closet doors to reinstall.';
        }

        if (! empty($inputs['include_material_details'])) {
            $materialCost = max(0, (float) ($inputs['material_cost_per_sqft'] ?? 0));
            if ($materialCost <= 0) {
                $hints[] = 'Enter the client material cost per sq ft, or turn off material details.';
            }
        }

        return $hints;
    }

    /**
     * @param  array<string, mixed>  $inputs
     * @return array<string, mixed>
     */
    public function calculate(
        array $inputs,
        ?FlooringSettings $settings = null,
        ?Collection $flooringTypes = null,
    ): array {
        $settings = $settings ?? FlooringSettings::current();
        $flooringTypes = $flooringTypes ?? FlooringType::query()->activeOrdered()->get();

        $sqft = round(max(0, (float) ($inputs['square_footage'] ?? 0)), 2);
        $typeId = (int) ($inputs['flooring_type_id'] ?? 0);
        /** @var FlooringType|null $flooringType */
        $flooringType = $typeId > 0 ? $flooringTypes->firstWhere('id', $typeId) : null;
        $typeName = $flooringType?->name ?? 'Flooring';
        $installRate = max(0, (float) ($flooringType?->installation_price_per_sqft ?? 0));

        $laborBreakdown = [];
        $laborTotal = 0.0;

        if ($sqft > 0 && $installRate > 0) {
            $installAmount = round($sqft * $installRate, 2);
            $laborBreakdown[] = [
                'key' => 'installation',
                'section' => 'labor',
                'label' => "Flooring installation — {$typeName}",
                'detail' => number_format($sqft, 0).' sq ft × '.ClientProject::formatMoney($installRate).'/sq ft',
                'amount' => $installAmount,
            ];
            $laborTotal += $installAmount;
        }

        if ($sqft > 0 && ! empty($inputs['remove_carpet'])) {
            $rate = max(0, (float) $settings->remove_carpet_dispose_rate);
            $amount = round($sqft * $rate, 2);
            $laborBreakdown[] = [
                'key' => 'remove_carpet',
                'section' => 'labor',
                'label' => 'Remove carpet & dispose off site',
                'detail' => number_format($sqft, 0).' sq ft × '.ClientProject::formatMoney($rate).'/sq ft',
                'amount' => $amount,
            ];
            $laborTotal += $amount;
        }

        if (! empty($inputs['rescrew_subfloor'])) {
            $amount = round(max(0, (float) $settings->rescrew_subfloor_rate), 2);
            $laborBreakdown[] = [
                'key' => 'rescrew_subfloor',
                'section' => 'labor',
                'label' => 'Re-screw subfloor to joists',
                'detail' => 'Flat project fee',
                'amount' => $amount,
            ];
            $laborTotal += $amount;
        }

        if (! empty($inputs['install_shoe_moulding'])) {
            $lf = $this->shoeMouldingLinearFeet($inputs);
            $rate = max(0, (float) $settings->install_shoe_moulding_rate);
            $amount = round($lf * $rate, 2);
            $laborBreakdown[] = [
                'key' => 'shoe_moulding',
                'section' => 'labor',
                'label' => 'Install primed white shoe moulding',
                'detail' => number_format($lf, 0).' lin ft × '.ClientProject::formatMoney($rate).'/lin ft',
                'amount' => $amount,
            ];
            $laborTotal += $amount;
        }

        if (! empty($inputs['caulk_paint_shoe_moulding'])) {
            $lf = $this->shoeMouldingLinearFeet($inputs);
            $rate = max(0, (float) $settings->caulk_paint_shoe_moulding_rate);
            $amount = round($lf * $rate, 2);
            $laborBreakdown[] = [
                'key' => 'caulk_paint_shoe',
                'section' => 'labor',
                'label' => 'Caulking and painting shoe moulding',
                'detail' => number_format($lf, 0).' lin ft × '.ClientProject::formatMoney($rate).'/lin ft',
                'amount' => $amount,
            ];
            $laborTotal += $amount;
        }

        if (! empty($inputs['closet_doors'])) {
            $qty = max(0, (int) ($inputs['closet_door_qty'] ?? 0));
            $rate = max(0, (float) $settings->closet_door_reinstall_rate);
            $amount = round($qty * $rate, 2);
            $laborBreakdown[] = [
                'key' => 'closet_doors',
                'section' => 'labor',
                'label' => 'Remove and reinstall closet doors',
                'detail' => $qty.' door'.($qty === 1 ? '' : 's').' × '.ClientProject::formatMoney($rate).'/door',
                'amount' => $amount,
            ];
            $laborTotal += $amount;
        }

        $laborTotal = round($laborTotal, 2);

        $materialBreakdown = [];
        $materialsTotal = 0.0;
        $materialSelection = null;
        if ($sqft > 0 && ! empty($inputs['include_material_details'])) {
            $materialRate = max(0, (float) ($inputs['material_cost_per_sqft'] ?? 0));
            $wastePercent = max(0, (float) ($inputs['material_waste_percent'] ?? 5));
            $deliveryFee = round(max(0, (float) ($inputs['material_delivery_fee'] ?? 60)), 2);
            if ($materialRate > 0) {
                $baseAmount = round($sqft * $materialRate, 2);
                $wasteAmount = round($baseAmount * ($wastePercent / 100), 2);
                $materialAmount = round($baseAmount + $wasteAmount + $deliveryFee, 2);
                $materialName = trim((string) ($inputs['material_name'] ?? ''));
                $label = $materialName !== '' ? "Client material — {$materialName}" : 'Client-selected flooring material';
                $materialBreakdown[] = [
                    'key' => 'client_material',
                    'section' => 'materials',
                    'label' => $label,
                    'detail' => number_format($sqft, 0).' sq ft × '.ClientProject::formatMoney($materialRate).'/sq ft',
                    'amount' => $baseAmount,
                ];
                if ($wasteAmount > 0) {
                    $materialBreakdown[] = [
                        'key' => 'client_material_waste',
                        'section' => 'materials',
                        'label' => 'Material waste allowance',
                        'detail' => number_format($wastePercent, 1).'% of material subtotal',
                        'amount' => $wasteAmount,
                    ];
                }
                if ($deliveryFee > 0) {
                    $materialBreakdown[] = [
                        'key' => 'client_material_delivery',
                        'section' => 'materials',
                        'label' => 'Material delivery',
                        'detail' => 'Flat delivery fee',
                        'amount' => $deliveryFee,
                    ];
                }
                $materialsTotal = $materialAmount;
                $materialSelection = [
                    'square_footage' => $sqft,
                    'cost_per_sqft' => $materialRate,
                    'waste_percent' => $wastePercent,
                    'base_amount' => $baseAmount,
                    'waste_amount' => $wasteAmount,
                    'delivery_fee' => $deliveryFee,
                    'total_pre_tax' => $materialAmount,
                ];
            }
        }

        $subtotalPreHst = round($laborTotal + $materialsTotal, 2);
        $hst = round($subtotalPreHst * FlooringQuotation::HST_RATE, 2);
        $grandTotal = round($subtotalPreHst + $hst, 2);

        return [
            'scope' => [
                'square_footage' => $sqft,
                'flooring_type_id' => $typeId ?: null,
                'flooring_type_name' => $typeName,
                'installation_rate_per_sqft' => $installRate,
            ],
            'breakdown' => array_merge($laborBreakdown, $materialBreakdown),
            'material_selection' => $materialSelection,
            'summary' => [
                'labor_pre_hst' => $laborTotal,
                'materials_pre_hst' => $materialsTotal,
                'subtotal_pre_hst' => $subtotalPreHst,
                'hst' => $hst,
                'grand_total' => $grandTotal,
                'final_quote_total' => $grandTotal,
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $inputs
     */
    private function shoeMouldingLinearFeet(array $inputs): float
    {
        $lf = round(max(0, (float) ($inputs['shoe_moulding_linear_ft'] ?? 0)), 2);
        if ($lf <= 0) {
            $lf = round(max(0, (float) ($inputs['caulk_paint_linear_ft'] ?? 0)), 2);
        }

        return $lf;
    }
}

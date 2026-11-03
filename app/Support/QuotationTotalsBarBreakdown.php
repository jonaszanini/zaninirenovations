<?php

namespace App\Support;

use App\Services\AdditionalCostsService;

/**
 * Single canonical calculation for the quotation sticky totals bar across all 12 quotation types.
 * Mirrors window.QuotationTotalsBar.calculateBreakdown() in public/js/quotation-totals-bar.js so
 * server-rendered initial totals, live client-side previews, and client PDFs always agree to the cent.
 */
final class QuotationTotalsBarBreakdown
{
    public const DEFAULT_HST_RATE = 0.13;

    public static function normalizeRate(mixed $rate): float
    {
        $num = is_numeric($rate) ? (float) $rate : self::DEFAULT_HST_RATE;
        if (! is_finite($num) || $num < 0) {
            return self::DEFAULT_HST_RATE;
        }

        return $num > 1.0 ? ($num / 100.0) : $num;
    }

    /**
     * @param  array<string, mixed>  $opts
     * @return array{materials: float, labour: float, extras: float, hasExtras: bool, hst: float, hstRate: int|float, grand: float}
     */
    public static function calculate(array $opts): array
    {
        $rate = self::normalizeRate($opts['hst_rate'] ?? self::DEFAULT_HST_RATE);
        $ratePct = round($rate * 100, 2);
        $rateLabelNum = abs($ratePct - round($ratePct)) < 1e-6 ? (int) round($ratePct) : $ratePct;
        $hasExtrasFlag = ! empty($opts['has_extras']);

        if (($opts['mode'] ?? '') === 'paving') {
            $baseQuoteTotal = round((float) ($opts['base_quote_total'] ?? 0), 2);
            $labourBase = round((float) ($opts['labour_pre_tax'] ?? 0), 2);
            $labourHst = round($labourBase * $rate, 2);
            $commuteTaxIn = max(0.0, round((float) ($opts['commute_tax_in'] ?? 0), 2));
            $debrisTaxIn = max(0.0, round((float) ($opts['debris_tax_in'] ?? 0), 2));
            $addMatTaxIn = max(0.0, round((float) ($opts['additional_materials_tax_in'] ?? 0), 2));
            $addLabPre = max(0.0, round((float) ($opts['additional_labor_pre_tax'] ?? 0), 2));
            $addLabTaxIn = max(0.0, round((float) ($opts['additional_labor_tax_in'] ?? 0), 2));

            $grand = array_key_exists('grand_total', $opts) && $opts['grand_total'] !== null
                ? round((float) $opts['grand_total'], 2)
                : round($baseQuoteTotal + $commuteTaxIn + $debrisTaxIn + $addMatTaxIn + $addLabTaxIn, 2);

            if ($grand <= 0.0) {
                return [
                    'materials' => 0.0,
                    'labour' => 0.0,
                    'extras' => 0.0,
                    'hasExtras' => $hasExtrasFlag,
                    'hst' => 0.0,
                    'hstRate' => $rateLabelNum,
                    'grand' => 0.0,
                ];
            }

            $exemptTaxIn = max(
                0.0,
                round($grand - $labourBase - $labourHst - $commuteTaxIn - $debrisTaxIn - $addLabTaxIn, 2)
            );
            $materials = round($exemptTaxIn / (1.0 + $rate), 2);
            $labour = round($labourBase + $addLabPre, 2);
            $commutePre = $commuteTaxIn > 0 ? round($commuteTaxIn / (1.0 + $rate), 2) : 0.0;
            $debrisPre = $debrisTaxIn > 0 ? round($debrisTaxIn / (1.0 + $rate), 2) : 0.0;
            $extras = round($commutePre + $debrisPre, 2);
            $hst = round($grand - $materials - $labour - $extras, 2);

            return [
                'materials' => $materials,
                'labour' => $labour,
                'extras' => $extras,
                'hasExtras' => $hasExtrasFlag || $extras > 0,
                'hst' => $hst,
                'hstRate' => $rateLabelNum,
                'grand' => $grand,
            ];
        }

        $matBase = round((float) ($opts['materials_pre_tax'] ?? 0), 2);
        $addMatPre = round((float) ($opts['additional_materials_pre_tax'] ?? 0), 2);
        $addMatTaxIn = array_key_exists('additional_materials_tax_in', $opts) && $opts['additional_materials_tax_in'] !== null
            ? round((float) $opts['additional_materials_tax_in'], 2)
            : null;
        $addMatHst = array_key_exists('additional_materials_hst', $opts) && $opts['additional_materials_hst'] !== null
            ? round((float) $opts['additional_materials_hst'], 2)
            : ($addMatTaxIn !== null ? round($addMatTaxIn - $addMatPre, 2) : round($addMatPre * $rate, 2));

        $labBase = round((float) ($opts['labour_pre_tax'] ?? 0), 2);
        $addLabPre = round((float) ($opts['additional_labor_pre_tax'] ?? 0), 2);
        $addLabTaxIn = array_key_exists('additional_labor_tax_in', $opts) && $opts['additional_labor_tax_in'] !== null
            ? round((float) $opts['additional_labor_tax_in'], 2)
            : null;
        $addLabHst = array_key_exists('additional_labor_hst', $opts) && $opts['additional_labor_hst'] !== null
            ? round((float) $opts['additional_labor_hst'], 2)
            : ($addLabTaxIn !== null ? round($addLabTaxIn - $addLabPre, 2) : round($addLabPre * $rate, 2));

        $extrasPre = round((float) ($opts['extras_pre_tax'] ?? 0), 2);
        $commuteTaxIn = max(0.0, round((float) ($opts['commute_tax_in'] ?? 0), 2));
        $commutePre = $commuteTaxIn > 0 ? round($commuteTaxIn / (1.0 + $rate), 2) : 0.0;
        $commuteHst = round($commuteTaxIn - $commutePre, 2);

        $debrisTaxIn = max(0.0, round((float) ($opts['debris_tax_in'] ?? 0), 2));
        $debrisPre = $debrisTaxIn > 0 ? round($debrisTaxIn / (1.0 + $rate), 2) : 0.0;
        $debrisHst = round($debrisTaxIn - $debrisPre, 2);

        $materials = round($matBase + $addMatPre, 2);
        $labour = round($labBase + $addLabPre, 2);
        $extras = round($extrasPre + $commutePre + $debrisPre, 2);

        $baseHst = array_key_exists('base_hst', $opts) && $opts['base_hst'] !== null
            ? round((float) $opts['base_hst'], 2)
            : round(($matBase + $labBase + $extrasPre) * $rate, 2);

        $hst = round($baseHst + $addMatHst + $addLabHst + $commuteHst + $debrisHst, 2);

        if (array_key_exists('grand_total', $opts) && $opts['grand_total'] !== null) {
            $grand = round((float) $opts['grand_total'], 2);
            if ($grand <= 0.0) {
                return [
                    'materials' => 0.0,
                    'labour' => 0.0,
                    'extras' => 0.0,
                    'hasExtras' => $hasExtrasFlag,
                    'hst' => 0.0,
                    'hstRate' => $rateLabelNum,
                    'grand' => 0.0,
                ];
            }
            if ($materials == 0.0 && $labour == 0.0 && $extras == 0.0) {
                $labour = round($grand / (1.0 + $rate), 2);
                $hst = round($grand - $labour, 2);
            } else {
                $hst = round($grand - $materials - $labour - $extras, 2);
            }
        } else {
            $grand = round($materials + $labour + $extras + $hst, 2);
        }

        return [
            'materials' => $materials,
            'labour' => $labour,
            'extras' => $extras,
            'hasExtras' => $hasExtrasFlag || $extras > 0,
            'hst' => $hst,
            'hstRate' => $rateLabelNum,
            'grand' => $grand,
        ];
    }

    /**
     * @return array{materials_pre_tax: float, materials_tax_in: float, materials_hst: float, labor_pre_tax: float, labor_tax_in: float, labor_hst: float, tax_in_total: float}
     */
    protected static function extractAdditionalCosts(?object $quotation): array
    {
        $rows = ($quotation && is_array($quotation->additional_costs ?? null))
            ? $quotation->additional_costs
            : [];
        $matPre = AdditionalCostsService::materialsPreTaxTotal($rows);
        $matTaxIn = AdditionalCostsService::taxInclusiveTotalForCategory($rows, AdditionalCostsService::CATEGORY_MATERIALS);
        $labPre = AdditionalCostsService::laborPreTaxTotal($rows);
        $labTaxIn = AdditionalCostsService::taxInclusiveTotalForCategory($rows, AdditionalCostsService::CATEGORY_LABOUR);

        return [
            'materials_pre_tax' => $matPre,
            'materials_tax_in' => $matTaxIn,
            'materials_hst' => round($matTaxIn - $matPre, 2),
            'labor_pre_tax' => $labPre,
            'labor_tax_in' => $labTaxIn,
            'labor_hst' => round($labTaxIn - $labPre, 2),
            'tax_in_total' => round($matTaxIn + $labTaxIn, 2),
        ];
    }

    protected static function extractCommuteTaxIn(?object $quotation): float
    {
        if (! $quotation || empty($quotation->include_commute)) {
            return 0.0;
        }

        return round((float) ($quotation->commute_amount ?? $quotation->commute_expense_amount ?? 0), 2);
    }

    protected static function extractDebrisTaxIn(?object $quotation): float
    {
        if (! $quotation || empty($quotation->include_debris_removal)) {
            return 0.0;
        }

        return round((float) ($quotation->debris_removal_amount ?? 0), 2);
    }

    public static function forBasement(array $summary = [], ?object $quotation = null, float $fallbackGrand = 0.0): array
    {
        $add = self::extractAdditionalCosts($quotation);
        $commute = self::extractCommuteTaxIn($quotation);
        $debris = self::extractDebrisTaxIn($quotation);
        $mat = (float) ($summary['materials_pre_hst'] ?? 0);
        $lab = (float) ($summary['labor_pre_hst'] ?? 0);
        $permit = (float) ($summary['permit_pre_hst'] ?? 0);
        $baseGrand = (float) ($summary['grand_total'] ?? 0);
        $grand = $baseGrand > 0
            ? round($baseGrand + $commute + $debris + $add['tax_in_total'], 2)
            : round($fallbackGrand, 2);

        return self::calculate([
            'materials_pre_tax' => $mat,
            'labour_pre_tax' => $lab,
            'extras_pre_tax' => $permit,
            'has_extras' => $permit > 0 || $commute > 0 || $debris > 0,
            'base_hst' => $summary['hst'] ?? null,
            'additional_materials_pre_tax' => $add['materials_pre_tax'],
            'additional_materials_tax_in' => $add['materials_tax_in'],
            'additional_materials_hst' => $add['materials_hst'],
            'additional_labor_pre_tax' => $add['labor_pre_tax'],
            'additional_labor_tax_in' => $add['labor_tax_in'],
            'additional_labor_hst' => $add['labor_hst'],
            'commute_tax_in' => $commute,
            'debris_tax_in' => $debris,
            'grand_total' => $grand,
        ]);
    }

    public static function forFence(array $totals = [], ?object $quotation = null): array
    {
        $add = self::extractAdditionalCosts($quotation);
        $commute = self::extractCommuteTaxIn($quotation);
        $debris = self::extractDebrisTaxIn($quotation);
        $mat = (float) ($totals['materials_subtotal'] ?? ($quotation->materials_subtotal ?? 0));
        $lab = (float) ($totals['labour_subtotal'] ?? ($quotation->labour_total ?? 0));
        $matHst = (float) ($totals['hst'] ?? $totals['materials_hst'] ?? ($quotation->materials_hst ?? round($mat * self::DEFAULT_HST_RATE, 2)));
        $labHst = (float) ($totals['labour_hst'] ?? ($quotation->labour_hst ?? 0));
        $baseGrand = (float) ($totals['grand_total'] ?? 0);
        $grand = $baseGrand > 0
            ? round($baseGrand + $commute + $debris + $add['tax_in_total'], 2)
            : (float) ($quotation->grand_total ?? 0);

        return self::calculate([
            'materials_pre_tax' => $mat,
            'labour_pre_tax' => $lab,
            'extras_pre_tax' => 0.0,
            'base_hst' => round($matHst + $labHst, 2),
            'additional_materials_pre_tax' => $add['materials_pre_tax'],
            'additional_materials_tax_in' => $add['materials_tax_in'],
            'additional_materials_hst' => $add['materials_hst'],
            'additional_labor_pre_tax' => $add['labor_pre_tax'],
            'additional_labor_tax_in' => $add['labor_tax_in'],
            'additional_labor_hst' => $add['labor_hst'],
            'commute_tax_in' => $commute,
            'debris_tax_in' => $debris,
            'grand_total' => $grand,
        ]);
    }

    public static function forFlooring(array $summary = [], ?object $quotation = null): array
    {
        $add = self::extractAdditionalCosts($quotation);
        $debris = self::extractDebrisTaxIn($quotation);
        $mat = (float) ($summary['materials_pre_hst'] ?? 0);
        $lab = (float) ($summary['labor_pre_hst'] ?? 0);
        $baseGrand = (float) ($summary['grand_total'] ?? 0);
        $grand = $baseGrand > 0
            ? round($baseGrand + $debris + $add['tax_in_total'], 2)
            : (float) ($quotation->final_quote_total ?? 0);

        return self::calculate([
            'materials_pre_tax' => $mat,
            'labour_pre_tax' => $lab,
            'extras_pre_tax' => 0.0,
            'base_hst' => $summary['hst'] ?? null,
            'additional_materials_pre_tax' => $add['materials_pre_tax'],
            'additional_materials_tax_in' => $add['materials_tax_in'],
            'additional_materials_hst' => $add['materials_hst'],
            'additional_labor_pre_tax' => $add['labor_pre_tax'],
            'additional_labor_tax_in' => $add['labor_tax_in'],
            'additional_labor_hst' => $add['labor_hst'],
            'debris_tax_in' => $debris,
            'grand_total' => $grand,
        ]);
    }

    public static function forLowDeck(array $totals = [], ?object $quotation = null): array
    {
        $add = self::extractAdditionalCosts($quotation);
        $commute = self::extractCommuteTaxIn($quotation);
        $debris = self::extractDebrisTaxIn($quotation);
        $mat = (float) ($totals['materials_subtotal'] ?? ($quotation->materials_subtotal ?? 0));
        $lab = (float) ($totals['labour_subtotal'] ?? ($quotation->labour_subtotal ?? 0));
        $matHst = (float) ($totals['hst'] ?? $totals['materials_hst'] ?? ($quotation->hst ?? round($mat * self::DEFAULT_HST_RATE, 2)));
        $labHst = (float) ($totals['labour_hst'] ?? ($quotation->labour_hst ?? 0));
        $baseGrand = (float) ($totals['grand_total'] ?? 0);
        $grand = $baseGrand > 0
            ? round($baseGrand + $commute + $debris + $add['tax_in_total'], 2)
            : (float) ($quotation->grand_total ?? 0);

        return self::calculate([
            'materials_pre_tax' => $mat,
            'labour_pre_tax' => $lab,
            'extras_pre_tax' => 0.0,
            'base_hst' => round($matHst + $labHst, 2),
            'additional_materials_pre_tax' => $add['materials_pre_tax'],
            'additional_materials_tax_in' => $add['materials_tax_in'],
            'additional_materials_hst' => $add['materials_hst'],
            'additional_labor_pre_tax' => $add['labor_pre_tax'],
            'additional_labor_tax_in' => $add['labor_tax_in'],
            'additional_labor_hst' => $add['labor_hst'],
            'commute_tax_in' => $commute,
            'debris_tax_in' => $debris,
            'grand_total' => $grand,
        ]);
    }

    public static function forMillwork(array $summary = [], ?object $quotation = null): array
    {
        $add = self::extractAdditionalCosts($quotation);
        $commute = self::extractCommuteTaxIn($quotation);
        $debris = self::extractDebrisTaxIn($quotation);
        $delivery = (float) ($summary['delivery_amount'] ?? 0);
        $markup = (float) ($summary['markup_amount'] ?? 0);
        $goods = array_key_exists('materials_goods_pre_hst', $summary)
            ? (float) $summary['materials_goods_pre_hst']
            : max(0.0, (float) ($summary['materials_pre_hst'] ?? 0) - $delivery);
        $lab = (float) ($summary['labor_pre_hst'] ?? 0);
        $extrasPre = round($delivery + $markup, 2);
        $baseGrand = (float) ($summary['grand_total'] ?? 0);
        $grand = $baseGrand > 0
            ? round($baseGrand + $commute + $debris + $add['tax_in_total'], 2)
            : (float) ($quotation->final_quote_total ?? 0);

        return self::calculate([
            'materials_pre_tax' => $goods,
            'labour_pre_tax' => $lab,
            'extras_pre_tax' => $extrasPre,
            'has_extras' => $extrasPre > 0 || $commute > 0 || $debris > 0,
            'base_hst' => $summary['hst'] ?? null,
            'additional_materials_pre_tax' => $add['materials_pre_tax'],
            'additional_materials_tax_in' => $add['materials_tax_in'],
            'additional_materials_hst' => $add['materials_hst'],
            'additional_labor_pre_tax' => $add['labor_pre_tax'],
            'additional_labor_tax_in' => $add['labor_tax_in'],
            'additional_labor_hst' => $add['labor_hst'],
            'commute_tax_in' => $commute,
            'debris_tax_in' => $debris,
            'grand_total' => $grand,
        ]);
    }

    public static function forPainting(?object $quotation = null, array $summary = []): array
    {
        $commute = self::extractCommuteTaxIn($quotation);
        $debris = self::extractDebrisTaxIn($quotation);
        $mat = (float) ($summary['materials_pre_hst'] ?? ($quotation->materials_amount ?? 0));
        $lab = (float) ($summary['labor_pre_hst'] ?? ($quotation->labor_amount ?? 0));
        $baseHst = $summary['hst'] ?? ($quotation->hst ?? null);
        $grand = isset($summary['grand_total']) && (float) $summary['grand_total'] > 0
            ? round((float) $summary['grand_total'] + $commute + $debris, 2)
            : (float) ($quotation->grand_total ?? 0);

        return self::calculate([
            'materials_pre_tax' => $mat,
            'labour_pre_tax' => $lab,
            'extras_pre_tax' => 0.0,
            'has_extras' => true,
            'base_hst' => $baseHst,
            'commute_tax_in' => $commute,
            'debris_tax_in' => $debris,
            'grand_total' => $grand,
        ]);
    }

    public static function forPaving(?object $quotation = null, array $calc = []): array
    {
        $calc = $calc !== [] ? $calc : (is_array($quotation?->calculation ?? null) ? $quotation->calculation : []);
        $summary = is_array($calc['summary'] ?? null) ? $calc['summary'] : [];
        $labour = (float) ($summary['labour_total'] ?? ($calc['labour']['total'] ?? 0));
        $baseQuoteTotal = (float) ($summary['final_quote_total'] ?? 0);
        $add = self::extractAdditionalCosts($quotation);
        $commute = self::extractCommuteTaxIn($quotation);
        $debris = self::extractDebrisTaxIn($quotation);
        $storedGrand = $quotation && isset($quotation->final_quote_total)
            ? (float) $quotation->final_quote_total
            : null;

        return self::calculate([
            'mode' => 'paving',
            'base_quote_total' => $baseQuoteTotal,
            'labour_pre_tax' => $labour,
            'commute_tax_in' => $commute,
            'debris_tax_in' => $debris,
            'additional_materials_tax_in' => $add['materials_tax_in'],
            'additional_labor_pre_tax' => $add['labor_pre_tax'],
            'additional_labor_tax_in' => $add['labor_tax_in'],
            'has_extras' => true,
            'grand_total' => $storedGrand !== null && $storedGrand > 0 ? $storedGrand : null,
        ]);
    }

    public static function forShed(array $summary = [], ?object $quotation = null): array
    {
        $add = self::extractAdditionalCosts($quotation);
        $commute = self::extractCommuteTaxIn($quotation);
        $debris = self::extractDebrisTaxIn($quotation);
        $mat = (float) ($summary['materials_pre_hst'] ?? 0);
        $lab = (float) ($summary['labor_pre_hst'] ?? 0);
        $baseGrand = (float) ($summary['grand_total'] ?? 0);
        $grand = $baseGrand > 0
            ? round($baseGrand + $commute + $debris + $add['tax_in_total'], 2)
            : (float) ($quotation->final_quote_total ?? 0);

        return self::calculate([
            'materials_pre_tax' => $mat,
            'labour_pre_tax' => $lab,
            'extras_pre_tax' => 0.0,
            'base_hst' => $summary['hst'] ?? null,
            'additional_materials_pre_tax' => $add['materials_pre_tax'],
            'additional_materials_tax_in' => $add['materials_tax_in'],
            'additional_materials_hst' => $add['materials_hst'],
            'additional_labor_pre_tax' => $add['labor_pre_tax'],
            'additional_labor_tax_in' => $add['labor_tax_in'],
            'additional_labor_hst' => $add['labor_hst'],
            'commute_tax_in' => $commute,
            'debris_tax_in' => $debris,
            'grand_total' => $grand,
        ]);
    }

    public static function forStairs(array $summary = [], ?object $quotation = null): array
    {
        $add = self::extractAdditionalCosts($quotation);
        $commute = self::extractCommuteTaxIn($quotation);
        $debris = self::extractDebrisTaxIn($quotation);
        $mat = (float) ($summary['material_price'] ?? 0);
        $lab = (float) ($summary['labour_subtotal_pre_hst'] ?? 0);
        $baseGrand = (float) ($summary['grand_total'] ?? 0);
        $grand = $baseGrand > 0
            ? round($baseGrand + $commute + $debris + $add['tax_in_total'], 2)
            : (float) ($quotation->final_quote_total ?? 0);

        return self::calculate([
            'materials_pre_tax' => $mat,
            'labour_pre_tax' => $lab,
            'extras_pre_tax' => 0.0,
            'has_extras' => true,
            'base_hst' => $summary['hst'] ?? null,
            'additional_materials_pre_tax' => $add['materials_pre_tax'],
            'additional_materials_tax_in' => $add['materials_tax_in'],
            'additional_materials_hst' => $add['materials_hst'],
            'additional_labor_pre_tax' => $add['labor_pre_tax'],
            'additional_labor_tax_in' => $add['labor_tax_in'],
            'additional_labor_hst' => $add['labor_hst'],
            'commute_tax_in' => $commute,
            'debris_tax_in' => $debris,
            'grand_total' => $grand,
        ]);
    }

    public static function forStandard(?object $quotation = null, float $toolbarGrand = 0.0): array
    {
        if (! $quotation) {
            return self::calculate(['grand_total' => $toolbarGrand]);
        }

        $commute = self::extractCommuteTaxIn($quotation);
        $debris = self::extractDebrisTaxIn($quotation);
        $mat = (float) ($quotation->materials_subtotal_before_hst ?? 0);
        $matHst = (float) ($quotation->materials_hst ?? 0);
        $lab = (float) ($quotation->labour_total ?? 0);
        $labHst = (float) ($quotation->labour_hst ?? 0);
        $contAmt = ! empty($quotation->contingency_enabled) ? (float) ($quotation->contingency_amount ?? 0) : 0.0;
        $contHst = ! empty($quotation->contingency_enabled) ? (float) ($quotation->contingency_hst ?? 0) : 0.0;
        $grand = (float) ($quotation->grand_total ?? $toolbarGrand);

        return self::calculate([
            'materials_pre_tax' => $mat,
            'labour_pre_tax' => $lab,
            'extras_pre_tax' => $contAmt,
            'has_extras' => $contAmt > 0 || $commute > 0 || $debris > 0,
            'base_hst' => round($matHst + $labHst + $contHst, 2),
            'commute_tax_in' => $commute,
            'debris_tax_in' => $debris,
            'grand_total' => $grand,
        ]);
    }

    public static function forTile(?object $quotation = null, array $calc = []): array
    {
        $calc = $calc !== [] ? $calc : (is_array($quotation?->calculation ?? null) ? $quotation->calculation : []);
        $add = self::extractAdditionalCosts($quotation);
        $commute = self::extractCommuteTaxIn($quotation);
        $debris = self::extractDebrisTaxIn($quotation);
        $mat = (float) ($calc['materials_subtotal'] ?? 0);
        $lab = (float) ($calc['labor_total'] ?? 0) + (float) ($calc['demolition_total'] ?? 0);
        $baseHst = $calc['hst'] ?? null;
        $baseGrand = (float) ($calc['grand_total'] ?? 0);
        $grand = $quotation && isset($quotation->final_quote_total) && (float) $quotation->final_quote_total > 0
            ? (float) $quotation->final_quote_total
            : ($baseGrand > 0 ? round($baseGrand + $commute + $debris + $add['tax_in_total'], 2) : 0.0);

        return self::calculate([
            'materials_pre_tax' => $mat,
            'labour_pre_tax' => $lab,
            'extras_pre_tax' => 0.0,
            'has_extras' => true,
            'base_hst' => $baseHst,
            'additional_materials_pre_tax' => $add['materials_pre_tax'],
            'additional_materials_tax_in' => $add['materials_tax_in'],
            'additional_materials_hst' => $add['materials_hst'],
            'additional_labor_pre_tax' => $add['labor_pre_tax'],
            'additional_labor_tax_in' => $add['labor_tax_in'],
            'additional_labor_hst' => $add['labor_hst'],
            'commute_tax_in' => $commute,
            'debris_tax_in' => $debris,
            'grand_total' => $grand,
        ]);
    }

    public static function forTrimming(array $summary = [], ?object $quotation = null): array
    {
        $add = self::extractAdditionalCosts($quotation);
        $commute = self::extractCommuteTaxIn($quotation);
        $debris = self::extractDebrisTaxIn($quotation);
        $mat = (float) ($summary['materials_pre_hst'] ?? 0);
        $lab = (float) ($summary['labor_pre_hst'] ?? 0);
        $baseGrand = (float) ($summary['grand_total'] ?? 0);
        $grand = $baseGrand > 0
            ? round($baseGrand + $commute + $debris + $add['tax_in_total'], 2)
            : (float) ($quotation->final_quote_total ?? 0);

        return self::calculate([
            'materials_pre_tax' => $mat,
            'labour_pre_tax' => $lab,
            'extras_pre_tax' => 0.0,
            'base_hst' => $summary['hst'] ?? null,
            'additional_materials_pre_tax' => $add['materials_pre_tax'],
            'additional_materials_tax_in' => $add['materials_tax_in'],
            'additional_materials_hst' => $add['materials_hst'],
            'additional_labor_pre_tax' => $add['labor_pre_tax'],
            'additional_labor_tax_in' => $add['labor_tax_in'],
            'additional_labor_hst' => $add['labor_hst'],
            'commute_tax_in' => $commute,
            'debris_tax_in' => $debris,
            'grand_total' => $grand,
        ]);
    }
}


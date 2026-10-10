<?php

namespace App\Services;

use App\Models\StandardQuotation;

/**
 * Read-only detector for Standard quotes likely damaged by the Room Area
 * kit-add rewrite (#65): persistMaterial() forced empty-string URLs,
 * electrical rows to kind=material, and a grand total that omitted
 * commute / debris.
 */
class StandardQuotationKitRewriteAuditor
{
    /**
     * @return list<array{
     *   id: int,
     *   quote_number: string,
     *   grand_total: float,
     *   expected_total: float,
     *   issues: list<string>
     * }>
     */
    public function findings(): array
    {
        $out = [];

        StandardQuotation::query()
            ->orderBy('id')
            ->each(function (StandardQuotation $quote) use (&$out) {
                $issues = $this->issuesFor($quote);
                if ($issues === []) {
                    return;
                }

                $out[] = [
                    'id' => (int) $quote->id,
                    'quote_number' => (string) $quote->quote_number,
                    'grand_total' => round((float) ($quote->grand_total ?? 0), 2),
                    'expected_total' => $quote->editorGrandTotal(),
                    'issues' => $issues,
                ];
            });

        return $out;
    }

    /**
     * @return list<string>
     */
    public function issuesFor(StandardQuotation $quote): array
    {
        $issues = [];

        foreach ($quote->flattenedMaterials() as $row) {
            if (! is_array($row)) {
                continue;
            }
            if (array_key_exists('url', $row) && $row['url'] === '') {
                $issues['empty_string_url'] = true;
            }
            if (array_key_exists('image_url', $row) && $row['image_url'] === '') {
                $issues['empty_string_image_url'] = true;
            }
            if ($this->electricalTextStoredAsMaterial($row)) {
                $issues['electrical_text_as_material'] = true;
            }
        }

        $expected = $quote->editorGrandTotal();
        $saved = round((float) ($quote->grand_total ?? 0), 2);
        if ($saved !== $expected) {
            $issues['saved_total_mismatch'] = true;
        }

        return array_keys($issues);
    }

    /**
     * @param  array<string, mixed>  $row
     */
    public function electricalTextStoredAsMaterial(array $row): bool
    {
        $desc = trim((string) ($row['description'] ?? ''));
        if ($desc === '') {
            return false;
        }

        $looksElectrical = strcasecmp($desc, StandardQuotation::ELECTRICAL_LINE_DESCRIPTION) === 0
            || str_starts_with(strtolower($desc), 'electrical work');

        if (! $looksElectrical) {
            return false;
        }

        return ! StandardQuotation::isElectricalMaterialRow($row);
    }
}

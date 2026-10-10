<?php

namespace App\Services;

use App\Models\ClientProjectQuotation;
use App\Models\StandardQuotation;
use Carbon\Carbon;
use DateTimeInterface;

/**
 * Read-only detector for Standard quotes likely damaged by the Room Area
 * kit-add rewrite (#65): persistMaterial() forced empty-string URLs,
 * electrical rows to kind=material, a grand total that omitted
 * commute / debris, or a linked project hub that no longer matches.
 */
class StandardQuotationKitRewriteAuditor
{
    public const DEFAULT_SINCE = '2026-10-10 02:07';

    public const DEFAULT_SINCE_TZ = 'America/Toronto';

    public static function defaultSince(): Carbon
    {
        return Carbon::parse(self::DEFAULT_SINCE, self::DEFAULT_SINCE_TZ);
    }

    public static function parseSince(?string $raw): Carbon
    {
        $raw = trim((string) $raw);

        return $raw === ''
            ? self::defaultSince()
            : Carbon::parse($raw, self::DEFAULT_SINCE_TZ);
    }

    /**
     * @return list<array{
     *   id: int,
     *   quote_number: string,
     *   is_draft: bool,
     *   linked_project: string,
     *   updated_at: string,
     *   grand_total: float,
     *   expected_total: float,
     *   hub_total: float|null,
     *   issues: list<string>
     * }>
     */
    public function findings(?DateTimeInterface $since = null): array
    {
        $since ??= self::defaultSince();
        $out = [];

        StandardQuotation::query()
            ->with('project')
            ->where('updated_at', '>=', $since)
            ->orderBy('id')
            ->each(function (StandardQuotation $quote) use (&$out) {
                $issues = $this->issuesFor($quote);
                if ($issues === []) {
                    return;
                }

                $out[] = $this->findingRow($quote, $issues);
            });

        return $out;
    }

    /**
     * @param  list<string>  $issues
     * @return array{
     *   id: int,
     *   quote_number: string,
     *   is_draft: bool,
     *   linked_project: string,
     *   updated_at: string,
     *   grand_total: float,
     *   expected_total: float,
     *   hub_total: float|null,
     *   issues: list<string>
     * }
     */
    private function findingRow(StandardQuotation $quote, array $issues): array
    {
        $hubTotal = $this->linkedHubTotal($quote);

        return [
            'id' => (int) $quote->id,
            'quote_number' => (string) $quote->quote_number,
            'is_draft' => (bool) ($quote->is_draft ?? false),
            'linked_project' => trim((string) ($quote->project?->name ?? ''))
                ?: ($quote->client_project_id ? '#'.$quote->client_project_id : '—'),
            'updated_at' => optional($quote->updated_at)?->timezone(self::DEFAULT_SINCE_TZ)->toDateTimeString()
                ?? '',
            'grand_total' => round((float) ($quote->grand_total ?? 0), 2),
            'expected_total' => $quote->editorGrandTotal(),
            'hub_total' => $hubTotal,
            'issues' => $issues,
        ];
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

        $hubTotal = $this->linkedHubTotal($quote);
        if ($hubTotal !== null && $hubTotal !== $saved) {
            $issues['hub_amount_mismatch'] = true;
        }

        return array_keys($issues);
    }

    public function linkedHubTotal(StandardQuotation $quote): ?float
    {
        if (! $quote->id) {
            return null;
        }

        $hub = ClientProjectQuotation::query()
            ->where('source_type', ClientProjectQuotation::SOURCE_STANDARD)
            ->where('source_id', $quote->id)
            ->first();

        if (! $hub) {
            return null;
        }

        return $hub->editorGrandTotal();
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

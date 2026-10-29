<?php

namespace Tests\Unit\Services;

use App\Models\MillworkQuotation;
use App\Models\MillworkSettings;
use App\Services\Millwork\Frozen\V2MillworkQuotationCalculator;
use App\Services\MillworkQuotationCalculator;
use Tests\TestCase;

class MillworkV2FuzzParityTest extends TestCase
{
    public function test_v2_quotes_match_frozen_copy_and_main_snapshot(): void
    {
        $limit = (int) (getenv('MILLWORK_FUZZ_CASES') ?: 3000);
        if ($limit < 1) {
            $limit = 3000;
        }

        $cases = json_decode(
            (string) shell_exec('php '.escapeshellarg(base_path('tests/bin/millwork-v2-fuzz-generate.php'))),
            true
        );
        $snapshot = json_decode(
            (string) file_get_contents(base_path('tests/fixtures/millwork-v2-main-578b5081/snapshot.json')),
            true
        );
        $this->assertIsArray($cases);
        $this->assertIsArray($snapshot);
        $this->assertCount($limit, $cases);

        $ids = array_slice(array_keys($cases), 0, $limit);
        $cases = array_intersect_key($cases, array_flip($ids));
        $snapshot = array_intersect_key($snapshot, array_flip($ids));
        $this->assertCount($limit, $snapshot);

        $settings = $this->settings();
        $frozenCalc = new V2MillworkQuotationCalculator;
        $liveCalc = new MillworkQuotationCalculator;
        $diffs = [];

        foreach ($cases as $id => $inputs) {
            $this->assertArrayHasKey($id, $snapshot, $id.' missing from main snapshot');
            $frozen = $this->snap($frozenCalc->calculate($inputs, $settings, 2), $inputs);
            $live = $this->snap($liveCalc->calculate($inputs, $settings, 2), $inputs);
            $expected = $this->normalizeSnap($snapshot[$id]);
            if ($frozen !== $expected) {
                $diffs[] = $id.' frozen';
            }
            if ($live !== $expected) {
                $diffs[] = $id.' routed';
            }
            if ($diffs !== []) {
                $this->assertSame($expected, $frozen, $id.' frozen vs main snapshot');
                $this->assertSame($expected, $live, $id.' routed v2 vs main snapshot');
            }
        }

        $this->assertSame([], $diffs);
        $this->assertSame('delivery', $this->deliveryWord($cases, $snapshot), 'R5-2 fixture must keep delivery wording');
    }

    /**
     * @param  array<string, mixed>  $calc
     * @param  array<string, mixed>  $inputs
     * @return array<string, mixed>
     */
    private function snap(array $calc, array $inputs): array
    {
        $quote = new MillworkQuotation([
            'cut_plan_version' => 2,
            'inputs' => $inputs,
            'calculation' => $calc,
        ]);

        return $this->normalizeSnap([
            'grand_total' => round((float) ($calc['summary']['grand_total'] ?? 0), 2),
            'labor_pre_hst' => round((float) ($calc['summary']['labor_pre_hst'] ?? 0), 2),
            'materials_pre_hst' => round((float) ($calc['summary']['materials_pre_hst'] ?? 0), 2),
            'preview_skipped' => (bool) ($calc['cut_plan']['preview_skipped'] ?? false),
            'labels' => $calc['scope']['labels'] ?? [],
            'pdf' => $quote->scopeDescriptionForPdf(),
        ]);
    }

    /**
     * @param  array<string, mixed>  $row
     * @return array<string, mixed>
     */
    private function normalizeSnap(array $row): array
    {
        return [
            'grand_total' => round((float) ($row['grand_total'] ?? 0), 2),
            'labor_pre_hst' => round((float) ($row['labor_pre_hst'] ?? 0), 2),
            'materials_pre_hst' => round((float) ($row['materials_pre_hst'] ?? 0), 2),
            'preview_skipped' => (bool) ($row['preview_skipped'] ?? false),
            'labels' => array_values($row['labels'] ?? []),
            'pdf' => (string) ($row['pdf'] ?? ''),
        ];
    }

    /**
     * @param  array<string, mixed>  $cases
     * @param  array<string, mixed>  $snapshot
     */
    private function deliveryWord(array $cases, array $snapshot): string
    {
        foreach ($cases as $id => $inputs) {
            if (! empty($inputs['client_provides_all']) && (float) ($inputs['delivery_amount'] ?? 0) > 0) {
                $pdf = strtolower((string) ($snapshot[$id]['pdf'] ?? ''));

                return str_contains($pdf, 'delivery') ? 'delivery' : 'missing';
            }
        }

        return 'missing-case';
    }

    private function settings(): MillworkSettings
    {
        $s = new MillworkSettings();
        $s->owner_hourly_rate = 85;
        $s->helper_hourly_rate = 45;
        $s->hours_per_day = 8;
        $s->productivity = MillworkSettings::defaultProductivity();
        $s->catalog = MillworkSettings::defaultCatalog();
        $s->cut_settings = MillworkSettings::defaultCutSettings();

        return $s;
    }
}

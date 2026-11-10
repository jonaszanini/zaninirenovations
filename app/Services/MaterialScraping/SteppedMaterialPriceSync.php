<?php

namespace App\Services\MaterialScraping;

use App\Models\Material;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * Session-backed one-material-per-request price sync (avoids shared-host timeouts).
 */
class SteppedMaterialPriceSync
{
    public function __construct(
        private MaterialPriceSyncService $syncService,
        private HomeDepotCaLocalizedPriceFetcher $localizedPriceFetcher,
    ) {}

    /**
     * @param  list<int>  $materialIds
     */
    public function start(
        Request $request,
        array $materialIds,
        string $sessionPrefix,
        string $emptyMessage,
        bool $trackPriceChangeStats = false,
    ): JsonResponse {
        $materialIds = array_values(array_unique(array_map('intval', $materialIds)));

        if ($materialIds === []) {
            return response()->json([
                'message' => $emptyMessage,
            ], 422);
        }

        $token = Str::random(40);
        $total = count($materialIds);

        session()->put($sessionPrefix.$token, [
            'user_id' => $request->user()->id,
            'material_ids' => $materialIds,
            'index' => 0,
            'ok' => 0,
            'updated' => 0,
            'unchanged' => 0,
            'failed' => 0,
            'skipped' => 0,
            'track_price_changes' => $trackPriceChangeStats,
            'log' => [],
        ]);

        $via = $this->localizedPriceFetcher->usesSerpApi() ? 'SerpApi' : 'Home Depot store API';

        return response()->json([
            'token' => $token,
            'phase' => 'sync',
            'phase_label' => 'Syncing prices',
            'current' => 0,
            'phase_total' => $total,
            'remaining' => $total,
            'message' => 'Starting sync of '.$total.' material(s) via '.$via.'.',
            'stats' => $this->stats([
                'ok' => 0,
                'updated' => 0,
                'unchanged' => 0,
                'failed' => 0,
                'skipped' => 0,
            ]),
            'log' => [],
            'done' => false,
        ]);
    }

    public function step(Request $request, string $sessionPrefix): JsonResponse
    {
        // Keep each admin step short on shared hosting — browser paces requests.
        if (function_exists('set_time_limit')) {
            @set_time_limit(120);
        }

        $data = $request->validate([
            'token' => ['required', 'string', 'max:64'],
        ]);

        $sessionKey = $sessionPrefix.$data['token'];
        $state = session($sessionKey);

        if (! is_array($state) || (int) ($state['user_id'] ?? 0) !== (int) $request->user()->id) {
            return response()->json([
                'message' => 'Sync session expired or invalid. Please start again.',
            ], 404);
        }

        $total = count($state['material_ids']);

        if ($state['index'] >= $total) {
            session()->forget($sessionKey);

            return $this->progressResponse($state, 'Sync complete.', true);
        }

        $materialId = (int) $state['material_ids'][$state['index']];
        $material = Material::query()->find($materialId);

        if ($material === null) {
            $state['failed']++;
            $message = 'Material #'.$materialId.' not found.';
        } else {
            $result = $this->syncService->syncOne($material);
            $material->refresh();

            if ($result['status'] === Material::SCRAPE_OK) {
                $state['ok']++;
                if ($this->tracksPriceChanges($state)) {
                    $oldPrice = (float) ($result['old_price'] ?? 0);
                    $newPrice = (float) $material->price_cad;
                    if ($this->priceUnchanged($oldPrice, $newPrice)) {
                        $state['unchanged'] = (int) ($state['unchanged'] ?? 0) + 1;
                        $message = 'Unchanged '.$material->name.' — '.$material->formattedPrice();
                    } else {
                        $state['updated'] = (int) ($state['updated'] ?? 0) + 1;
                        $message = 'Updated '.$material->name.' — '.$material->formattedPrice();
                    }
                } else {
                    $message = 'Synced '.$material->name.' — '.$material->formattedPrice();
                }
            } elseif ($result['status'] === Material::SCRAPE_SKIPPED) {
                $state['skipped']++;
                $message = 'Skipped '.$material->name.': '.($result['error'] ?? 'no product URL');
            } else {
                $state['failed']++;
                $message = 'Failed '.$material->name.': '.($result['error'] ?? 'unknown error');
            }
        }

        $state['index']++;
        $state = $this->appendLog($state, $message);

        $done = $state['index'] >= $total;

        if ($done) {
            session()->forget($sessionKey);
        } else {
            session()->put($sessionKey, $state);
        }

        return $this->progressResponse($state, $message, $done);
    }

    /**
     * @param  array<string, mixed>  $state
     * @return array<string, mixed>
     */
    private function appendLog(array $state, string $message): array
    {
        $log = is_array($state['log'] ?? null) ? $state['log'] : [];
        $log[] = $message;
        $state['log'] = array_slice($log, -30);

        return $state;
    }

    /**
     * @param  array<string, mixed>  $state
     * @return array<string, int>
     */
    private function stats(array $state): array
    {
        $stats = [
            'ok' => (int) ($state['ok'] ?? 0),
            'failed' => (int) ($state['failed'] ?? 0),
            'skipped' => (int) ($state['skipped'] ?? 0),
        ];

        if ($this->tracksPriceChanges($state)) {
            $stats['updated'] = (int) ($state['updated'] ?? 0);
            $stats['unchanged'] = (int) ($state['unchanged'] ?? 0);
        }

        return $stats;
    }

    private function tracksPriceChanges(array $state): bool
    {
        return (bool) ($state['track_price_changes'] ?? false);
    }

    private function priceUnchanged(float $oldPrice, float $newPrice): bool
    {
        return abs($oldPrice - $newPrice) < 0.005;
    }

    /**
     * @param  array<string, mixed>  $state
     */
    private function doneSummary(array $state, int $current, int $total): string
    {
        if ($this->tracksPriceChanges($state)) {
            return sprintf(
                '%d updated, %d unchanged, %d failed, %d skipped. %d of %d complete.',
                (int) ($state['updated'] ?? 0),
                (int) ($state['unchanged'] ?? 0),
                (int) ($state['failed'] ?? 0),
                (int) ($state['skipped'] ?? 0),
                $current,
                $total,
            );
        }

        return sprintf(
            '%d synced, %d failed, %d skipped. %d of %d complete.',
            (int) ($state['ok'] ?? 0),
            (int) ($state['failed'] ?? 0),
            (int) ($state['skipped'] ?? 0),
            $current,
            $total,
        );
    }

    private function progressResponse(array $state, string $message, bool $done): JsonResponse
    {
        $total = count($state['material_ids'] ?? []);
        $current = (int) ($state['index'] ?? 0);
        $remaining = max(0, $total - $current);

        return response()->json([
            'done' => $done,
            'phase' => $done ? 'done' : 'sync',
            'phase_label' => $done ? 'Complete' : 'Syncing prices',
            'current' => $current,
            'phase_total' => $total,
            'remaining' => $remaining,
            'message' => $message,
            'stats' => $this->stats($state),
            'log' => $state['log'] ?? [],
            'summary' => $done ? $this->doneSummary($state, $current, $total) : null,
        ]);
    }
}

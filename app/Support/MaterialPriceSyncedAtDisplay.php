<?php

namespace App\Support;

use App\Models\Material;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;

final class MaterialPriceSyncedAtDisplay
{
    public static function relativeLabel(?CarbonInterface $syncedAt, ?CarbonInterface $now = null): string
    {
        if ($syncedAt === null) {
            return 'never';
        }

        $now = ($now ?? now())->copy()->timezone(EasternStandardTimeDisplay::easternTimezone())->startOfDay();
        $syncDay = $syncedAt->copy()->timezone(EasternStandardTimeDisplay::easternTimezone())->startOfDay();
        $days = (int) $syncDay->diffInDays($now, false);

        if ($days <= 0) {
            return 'today';
        }
        if ($days === 1) {
            return '1 day ago';
        }

        return $days.' days ago';
    }

    public static function isStale(?CarbonInterface $syncedAt, ?CarbonInterface $now = null): bool
    {
        if ($syncedAt === null) {
            return false;
        }

        $now = ($now ?? now())->copy()->timezone(EasternStandardTimeDisplay::easternTimezone())->startOfDay();
        $syncDay = $syncedAt->copy()->timezone(EasternStandardTimeDisplay::easternTimezone())->startOfDay();
        $days = (int) $syncDay->diffInDays($now, false);

        return $days > 30;
    }

    public static function tooltip(?CarbonInterface $syncedAt): string
    {
        if ($syncedAt === null) {
            return 'No successful catalog price sync yet';
        }

        return 'Last price update: '.EasternStandardTimeDisplay::formatDateTime($syncedAt);
    }

    public static function forMaterial(?Material $material, ?CarbonInterface $now = null): ?array
    {
        if ($material === null) {
            return null;
        }

        $at = $material->last_price_synced_at;

        return [
            'label' => self::relativeLabel($at, $now),
            'stale' => self::isStale($at, $now),
            'tooltip' => self::tooltip($at),
        ];
    }
}

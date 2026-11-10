<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\BasementMaterialPriceSyncScope;
use App\Services\MaterialScraping\SteppedMaterialPriceSync;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BasementMaterialPriceSyncController extends Controller
{
    private const SYNC_SESSION_PREFIX = 'basement_material_sync.';

    public function start(Request $request, SteppedMaterialPriceSync $steppedSync, BasementMaterialPriceSyncScope $scope): JsonResponse
    {
        $materialIds = $scope->linkedCatalogMaterialIds();

        return $steppedSync->start(
            $request,
            $materialIds,
            self::SYNC_SESSION_PREFIX,
            'No basement catalog materials are linked for price sync. Link materials on Basement price configuration or Shared prices (Used by: Basement), then try again.',
            trackPriceChangeStats: true,
        );
    }

    public function step(Request $request, SteppedMaterialPriceSync $steppedSync): JsonResponse
    {
        return $steppedSync->step($request, self::SYNC_SESSION_PREFIX);
    }
}

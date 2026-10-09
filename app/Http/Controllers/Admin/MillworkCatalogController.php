<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\MillworkSettings;
use App\Services\Millwork\MillworkCatalog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MillworkCatalogController extends Controller
{
    public function show(string $slot): JsonResponse
    {
        $slot = $this->validatedSlot($slot);
        $settings = MillworkSettings::current();

        return response()->json(MillworkCatalog::slotPayload($slot, $settings->itemsFor($slot)));
    }

    public function update(Request $request, string $slot): JsonResponse
    {
        $slot = $this->validatedSlot($slot);
        $request->validate([
            'items' => ['required', 'array', 'max:80'],
            'items.*.id' => ['nullable', 'string', 'max:80'],
            'items.*.name' => ['nullable', 'string', 'max:160'],
            'items.*.unit_price' => ['nullable', 'numeric', 'min:0'],
        ]);

        $settings = MillworkSettings::current();
        $result = MillworkCatalog::replaceSlot($settings, $slot, $request->input('items', []));
        $payload = MillworkCatalog::slotPayload($slot, $result['items']);
        $payload['soft_deleted'] = $result['soft_deleted'];
        $payload['removed'] = $result['removed'];

        return response()->json($payload);
    }

    private function validatedSlot(string $slot): string
    {
        abort_unless(array_key_exists($slot, MillworkSettings::catalogSlots()), 404);

        return $slot;
    }
}

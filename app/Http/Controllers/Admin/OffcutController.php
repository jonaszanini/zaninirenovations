<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ClientProject;
use App\Models\Offcut;
use App\Services\OffcutInventoryService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class OffcutController extends Controller
{
    public function __construct(
        private OffcutInventoryService $inventory,
    ) {
    }

    public function index(Request $request): View
    {
        $material = trim((string) $request->query('material', ''));
        $status = (string) $request->query('status', 'available');

        $query = Offcut::query()
            ->with(['createdFromProject:id,name,client_name', 'usedByProject:id,name,client_name'])
            ->orderByDesc('id');

        if ($status === 'available') {
            $query->available();
        } elseif ($status === 'used') {
            $query->whereNotNull('used_at');
        }

        if ($material !== '') {
            $query->where('material_key', 'like', '%'.$material.'%');
        }

        $offcuts = $query->get();
        $prices = $this->inventory->catalogPrices($offcuts->pluck('material_key')->unique()->all());
        $materials = Offcut::query()
            ->select('material_key')
            ->distinct()
            ->orderBy('material_key')
            ->pluck('material_key');

        $clientProjects = ClientProject::query()
            ->whereNotIn('status', [ClientProject::STATUS_REJECTED, ClientProject::STATUS_CANCELLED])
            ->orderBy('client_name')
            ->orderBy('name')
            ->get(['id', 'name', 'client_name']);

        return view('admin.utilities.offcuts', [
            'offcuts' => $offcuts,
            'prices' => $prices,
            'materials' => $materials,
            'material' => $material,
            'status' => $status,
            'clientProjects' => $clientProjects,
            'editing' => null,
        ]);
    }

    public function create(Request $request): View
    {
        return $this->formView($request, null);
    }

    public function store(Request $request): RedirectResponse
    {
        Offcut::query()->create($this->validated($request));

        return redirect()
            ->route('admin.utilities.offcuts.index')
            ->with('success', 'Offcut saved to inventory.');
    }

    public function edit(Request $request, Offcut $offcut): View
    {
        return $this->formView($request, $offcut);
    }

    public function update(Request $request, Offcut $offcut): RedirectResponse
    {
        $offcut->update($this->validated($request, $offcut));

        return redirect()
            ->route('admin.utilities.offcuts.index')
            ->with('success', 'Offcut updated.');
    }

    public function markUsed(Request $request, Offcut $offcut): RedirectResponse
    {
        $data = $request->validate([
            'used_by_project_id' => ['nullable', 'integer', 'exists:client_projects,id'],
        ]);
        $offcut->markUsed(isset($data['used_by_project_id']) ? (int) $data['used_by_project_id'] : null);

        return redirect()
            ->route('admin.utilities.offcuts.index')
            ->with('success', 'Offcut marked as used.');
    }

    public function destroy(Offcut $offcut): RedirectResponse
    {
        $offcut->delete();

        return redirect()
            ->route('admin.utilities.offcuts.index')
            ->with('success', 'Offcut deleted.');
    }

    public function availableJson(Request $request): JsonResponse
    {
        $key = trim((string) $request->query('material', ''));

        return response()->json($this->inventory->sharedPayload($key !== '' ? $key : null));
    }

    public function saveLeftovers(Request $request): JsonResponse
    {
        $data = $request->validate([
            'client_project_id' => ['nullable', 'integer', 'exists:client_projects,id'],
            'min_offcut_in' => ['nullable', 'numeric', 'min:0'],
            'leftovers' => ['required', 'array', 'min:1'],
            'leftovers.*.material_key' => ['required', 'string', 'max:80'],
            'leftovers.*.thickness_in' => ['nullable', 'numeric', 'min:0'],
            'leftovers.*.width_in' => ['nullable', 'numeric', 'min:0'],
            'leftovers.*.length_in' => ['required', 'numeric', 'min:0.01'],
            'leftovers.*.qty' => ['nullable', 'integer', 'min:1'],
            'leftovers.*.location' => ['nullable', 'string', 'max:120'],
            'leftovers.*.notes' => ['nullable', 'string', 'max:255'],
        ]);

        $created = $this->inventory->saveLeftovers(
            $data['leftovers'],
            (float) ($data['min_offcut_in'] ?? 0),
            isset($data['client_project_id']) ? (int) $data['client_project_id'] : null
        );

        return response()->json([
            'ok' => true,
            'saved' => count($created),
            'offcuts' => array_map(
                fn (Offcut $row) => $this->inventory->rowPayload($row),
                $created
            ),
        ]);
    }

    public function consume(Request $request): JsonResponse
    {
        $data = $request->validate([
            'ids' => ['required', 'array', 'min:1'],
            'ids.*' => ['integer', 'exists:offcuts,id'],
            'client_project_id' => ['nullable', 'integer', 'exists:client_projects,id'],
            'qty' => ['nullable', 'integer', 'min:1'],
        ]);

        $used = $this->inventory->consume(
            $data['ids'],
            isset($data['client_project_id']) ? (int) $data['client_project_id'] : null,
            (int) ($data['qty'] ?? 1)
        );

        return response()->json([
            'ok' => true,
            'used_ids' => $used,
        ]);
    }

    private function formView(Request $request, ?Offcut $offcut): View
    {
        $material = trim((string) $request->query('material', ''));
        $status = (string) $request->query('status', 'available');

        $query = Offcut::query()
            ->with(['createdFromProject:id,name,client_name', 'usedByProject:id,name,client_name'])
            ->orderByDesc('id');
        if ($status === 'available') {
            $query->available();
        } elseif ($status === 'used') {
            $query->whereNotNull('used_at');
        }
        if ($material !== '') {
            $query->where('material_key', 'like', '%'.$material.'%');
        }

        $offcuts = $query->get();
        $prices = $this->inventory->catalogPrices($offcuts->pluck('material_key')->unique()->all());
        $materials = Offcut::query()->select('material_key')->distinct()->orderBy('material_key')->pluck('material_key');
        $clientProjects = ClientProject::query()
            ->whereNotIn('status', [ClientProject::STATUS_REJECTED, ClientProject::STATUS_CANCELLED])
            ->orderBy('client_name')
            ->orderBy('name')
            ->get(['id', 'name', 'client_name']);

        return view('admin.utilities.offcuts', [
            'offcuts' => $offcuts,
            'prices' => $prices,
            'materials' => $materials,
            'material' => $material,
            'status' => $status,
            'clientProjects' => $clientProjects,
            'editing' => $offcut,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request, ?Offcut $existing = null): array
    {
        $data = $request->validate([
            'material_key' => ['required', 'string', 'max:80'],
            'thickness_in' => ['nullable', 'numeric', 'min:0'],
            'width_in' => ['nullable', 'numeric', 'min:0'],
            'length_in' => ['required', 'numeric', 'min:0.01'],
            'qty' => ['required', 'integer', 'min:1'],
            'location' => ['nullable', 'string', 'max:120'],
            'notes' => ['nullable', 'string', 'max:255'],
            'created_from_project_id' => ['nullable', 'integer', 'exists:client_projects,id'],
        ]);

        $data['material_key'] = $this->inventory->normaliseKey($data['material_key']);
        $data['thickness_in'] = $this->emptyToNull($data['thickness_in'] ?? null);
        $data['width_in'] = $this->emptyToNull($data['width_in'] ?? null);
        $data['created_from_project_id'] = $this->emptyToNull($data['created_from_project_id'] ?? null);
        $data['location'] = $this->emptyToNull($data['location'] ?? null);
        $data['notes'] = $this->emptyToNull($data['notes'] ?? null);

        if ($existing && $existing->used_at) {
            unset($data['qty']);
        }

        return $data;
    }

    private function emptyToNull(mixed $value): mixed
    {
        if ($value === '' || $value === null) {
            return null;
        }

        return $value;
    }
}

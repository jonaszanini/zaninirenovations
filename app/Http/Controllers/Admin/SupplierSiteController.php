<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\SupplierSite;
use App\Models\SupplierTag;
use App\Services\SupplierSiteDistanceService;
use App\Services\SupplierWebsite\SupplierWebsiteImportService;
use App\Support\AdminPaginatedList;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class SupplierSiteController extends Controller
{
    public function index(Request $request)
    {
        $q = trim((string) $request->query('q', ''));
        $tagSlug = trim((string) $request->query('tag', ''));
        $perPage = AdminPaginatedList::perPage($request);
        $sort = trim((string) $request->query('sort', ''));
        $sortDir = strtolower(trim((string) $request->query('dir', 'asc'))) === 'desc' ? 'desc' : 'asc';

        $query = SupplierSite::query()
            ->with('tags')
            ->when($tagSlug !== '', function ($query) use ($tagSlug) {
                $query->whereHas('tags', fn ($sub) => $sub->where('slug', $tagSlug));
            })
            ->when($q !== '', function ($query) use ($q) {
                $like = '%'.$q.'%';
                $query->where(function ($sub) use ($like, $q) {
                    $sub->where('name', 'like', $like)
                        ->orWhere('url', 'like', $like)
                        ->orWhere('notes', 'like', $like)
                        ->orWhere('phone', 'like', $like)
                        ->orWhere('email', 'like', $like)
                        ->orWhere('address', 'like', $like)
                        ->orWhereHas('tags', fn ($tags) => $tags->where('name', 'like', $like));
                });
            })
            ->orderByDesc('is_favorite');

        if ($sort === 'distance') {
            $query->orderByRaw('distance_km IS NULL')
                ->orderBy('distance_km', $sortDir)
                ->orderBy('name');
        } else {
            $query->orderBy('sort_order')
                ->orderBy('name');
        }

        $supplierSites = AdminPaginatedList::paginateOrAll($query, $perPage);

        $filterTags = SupplierTag::query()
            ->whereHas('supplierSites')
            ->orderBy('name')
            ->get(['id', 'name', 'slug']);

        return view('admin.supplier_sites.index', compact(
            'supplierSites',
            'q',
            'tagSlug',
            'perPage',
            'filterTags',
            'sort',
            'sortDir',
        ));
    }

    public function importPreview(Request $request, SupplierWebsiteImportService $import): JsonResponse
    {
        $data = $request->validate([
            'url' => ['required', 'string', 'max:500'],
        ]);

        $result = $import->preview($data['url']);

        return response()->json($result);
    }

    public function refreshDistance(SupplierSite $supplier_site, SupplierSiteDistanceService $distances): JsonResponse
    {
        $result = $distances->refresh($supplier_site);
        $supplier_site->refresh();

        return response()->json([
            'ok' => $result['ok'],
            'message' => $result['message'],
            'distance_km' => $supplier_site->distance_km !== null ? (float) $supplier_site->distance_km : null,
            'drive_minutes' => $supplier_site->drive_minutes,
            'distance_label' => $supplier_site->distanceDisplayLabel(),
            'distance_error' => $supplier_site->distance_error,
        ]);
    }

    public function create()
    {
        return view('admin.supplier_sites.form', [
            'supplierSite' => new SupplierSite,
        ]);
    }

    public function store(Request $request, SupplierSiteDistanceService $distances)
    {
        $this->normalisePhoneOnRequest($request);
        $data = $this->validateSupplierSite($request);

        if ($replay = $this->rejectReplaySubmit($request)) {
            return $replay;
        }

        $site = SupplierSite::create($data);
        $this->rememberSubmitSucceeded($request);
        $site->tags()->sync(SupplierTag::syncFromNames(
            SupplierTag::parseNamesFromInput($request->input('tags_input'))
        ));
        $distances->refresh($site);

        return redirect()
            ->route('admin.supplier-sites.index')
            ->with('success', 'Supplier saved.');
    }

    public function edit(SupplierSite $supplier_site)
    {
        $supplier_site->load('tags');

        return view('admin.supplier_sites.form', [
            'supplierSite' => $supplier_site,
        ]);
    }

    public function update(Request $request, SupplierSite $supplier_site, SupplierSiteDistanceService $distances)
    {
        $this->normalisePhoneOnRequest($request);
        $data = $this->validateSupplierSite($request);

        $previousAddress = trim((string) $supplier_site->address);
        $newAddress = trim((string) ($data['address'] ?? ''));
        if ($previousAddress !== $newAddress) {
            $supplier_site->fill($data);
            $supplier_site->forceFill([
                'distance_km' => null,
                'drive_minutes' => null,
                'distance_checked_at' => null,
                'distance_error' => null,
            ]);
            $supplier_site->save();
        } else {
            $supplier_site->update($data);
        }
        if ($newAddress !== '' && $supplier_site->distance_km === null) {
            $distances->refresh($supplier_site);
        }
        $supplier_site->tags()->sync(SupplierTag::syncFromNames(
            SupplierTag::parseNamesFromInput($request->input('tags_input'))
        ));

        return redirect()
            ->route('admin.supplier-sites.index')
            ->with('success', 'Supplier updated.');
    }

    public function destroy(SupplierSite $supplier_site)
    {
        $supplier_site->delete();

        SupplierTag::query()
            ->whereDoesntHave('supplierSites')
            ->delete();

        return redirect()
            ->route('admin.supplier-sites.index')
            ->with('success', 'Supplier removed.');
    }

    /**
     * @return array<string, mixed>
     */
    protected function validateSupplierSite(Request $request): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:160'],
            'url' => ['required', 'string', 'max:500'],
            'logo_url' => ['nullable', 'string', 'max:500'],
            'notes' => ['nullable', 'string', 'max:5000'],
            'phone' => ['nullable', 'digits:10'],
            'email' => ['nullable', 'email', 'max:255'],
            'address' => ['nullable', 'string', 'max:500'],
            'is_favorite' => ['nullable', 'boolean'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:32767'],
            'tags_input' => ['nullable', 'string', 'max:2000'],
        ]);

        $url = $this->normalizeUrl($data['url']);
        if ($url === null) {
            throw ValidationException::withMessages([
                'url' => 'Enter a valid website URL (e.g. https://debline.com).',
            ]);
        }

        $logoUrl = filled($data['logo_url'] ?? null) ? trim((string) $data['logo_url']) : null;
        if ($logoUrl !== null && ! preg_match('#^https?://#i', $logoUrl)) {
            throw ValidationException::withMessages([
                'logo_url' => 'Logo URL must start with http:// or https://.',
            ]);
        }
        if ($logoUrl !== null && $this->normalizeUrl($logoUrl) === null) {
            throw ValidationException::withMessages([
                'logo_url' => 'Logo URL must be a valid http or https address.',
            ]);
        }

        return [
            'name' => trim($data['name']),
            'url' => $url,
            'logo_url' => $logoUrl,
            'notes' => filled($data['notes'] ?? null) ? trim($data['notes']) : null,
            'phone' => filled($data['phone'] ?? null) ? (string) $data['phone'] : null,
            'email' => filled($data['email'] ?? null) ? strtolower(trim($data['email'])) : null,
            'address' => filled($data['address'] ?? null) ? trim($data['address']) : null,
            'is_favorite' => $request->boolean('is_favorite'),
            'sort_order' => (int) ($data['sort_order'] ?? 0),
        ];
    }

    protected function normalisePhoneOnRequest(Request $request): void
    {
        if (! $request->has('phone')) {
            return;
        }

        $digits = preg_replace('/\D/', '', (string) $request->input('phone'));

        $request->merge([
            'phone' => $digits === '' ? null : $digits,
        ]);
    }

    protected function normalizeUrl(string $raw): ?string
    {
        $url = trim($raw);
        if ($url === '') {
            return null;
        }

        if (! preg_match('#^https?://#i', $url)) {
            $url = 'https://'.$url;
        }

        $parts = parse_url($url);
        if (! is_array($parts) || empty($parts['host'])) {
            return null;
        }

        $scheme = strtolower((string) ($parts['scheme'] ?? 'https'));
        if (! in_array($scheme, ['http', 'https'], true)) {
            return null;
        }

        $host = strtolower((string) $parts['host']);
        $path = $parts['path'] ?? '';
        $query = isset($parts['query']) ? '?'.$parts['query'] : '';
        $fragment = isset($parts['fragment']) ? '#'.$parts['fragment'] : '';
        $port = isset($parts['port']) ? ':'.$parts['port'] : '';

        return $scheme.'://'.$host.$port.$path.$query.$fragment;
    }
}

<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\SupplierSite;
use App\Models\SupplierTag;
use App\Support\AdminPaginatedList;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class SupplierSiteController extends Controller
{
    public function index(Request $request)
    {
        $q = trim((string) $request->query('q', ''));
        $tagSlug = trim((string) $request->query('tag', ''));
        $perPage = AdminPaginatedList::perPage($request);

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
            ->orderByDesc('is_favorite')
            ->orderBy('sort_order')
            ->orderBy('name');

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
        ));
    }

    public function create()
    {
        return view('admin.supplier_sites.form', [
            'supplierSite' => new SupplierSite,
        ]);
    }

    public function store(Request $request)
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

    public function update(Request $request, SupplierSite $supplier_site)
    {
        $this->normalisePhoneOnRequest($request);
        $data = $this->validateSupplierSite($request);

        $supplier_site->update($data);
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

        return [
            'name' => trim($data['name']),
            'url' => $url,
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

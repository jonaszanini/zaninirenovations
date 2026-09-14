<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\TileEdge;
use App\Models\TileMortarType;
use App\Models\TileGroutType;
use App\Models\TileProduct;
use App\Support\CadMoney;
use App\Support\QuotationAfterSaveRedirect;
use App\Services\ProjectPhotoWebpStorage;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Facades\Storage;

class TileDatabaseController extends Controller
{
    public function edit(Request $request)
    {
        $mortarTypes = TileMortarType::query()->orderBy('sort_order')->orderBy('id')->get();
        $groutTypes = TileGroutType::query()->orderBy('sort_order')->orderBy('id')->get();
        $products = TileProduct::query()->orderBy('sort_order')->orderBy('id')->get();
        $edges = TileEdge::query()->orderBy('sort_order')->orderBy('id')->get();
        $qpid = $request->filled('project') ? (int) $request->query('project') : null;

        return view('admin.quotations.tile.price_configuration', compact(
            'mortarTypes',
            'groutTypes',
            'products',
            'edges',
            'qpid'
        ));
    }

    public function update(Request $request)
    {
        $this->normalizeMoneyFields($request);
        $this->normalizeProductUrls($request);
        $this->dropInvalidEmptyUploads($request);

        $data = $request->validate([
            'mortars' => ['nullable', 'array'],
            'mortars.*.id' => ['nullable', 'integer', 'exists:tile_mortar_types,id'],
            'mortars.*.name' => ['required', 'string', 'max:255'],
            'mortars.*.bag_weight_lbs' => ['required', 'numeric', 'min:0'],
            'mortars.*.cost_price' => ['required', 'numeric', 'min:0'],
            'mortars.*.sort_order' => ['nullable', 'integer', 'min:0'],
            'mortars.*.is_active' => ['nullable', 'boolean'],
            'new_mortars' => ['nullable', 'array'],
            'new_mortars.*.name' => ['nullable', 'string', 'max:255'],
            'new_mortars.*.bag_weight_lbs' => ['nullable', 'numeric', 'min:0'],
            'new_mortars.*.cost_price' => ['nullable', 'numeric', 'min:0'],
            'delete_mortar_ids' => ['nullable', 'array'],
            'delete_mortar_ids.*' => ['integer', 'exists:tile_mortar_types,id'],

            'grouts' => ['nullable', 'array'],
            'grouts.*.id' => ['nullable', 'integer', 'exists:tile_grout_types,id'],
            'grouts.*.name' => ['required', 'string', 'max:255'],
            'grouts.*.bag_weight_lbs' => ['required', 'numeric', 'min:0'],
            'grouts.*.density_factor' => ['required', 'numeric', 'min:0'],
            'grouts.*.cost_price' => ['required', 'numeric', 'min:0'],
            'grouts.*.sort_order' => ['nullable', 'integer', 'min:0'],
            'grouts.*.is_active' => ['nullable', 'boolean'],
            'new_grouts' => ['nullable', 'array'],
            'new_grouts.*.name' => ['nullable', 'string', 'max:255'],
            'new_grouts.*.bag_weight_lbs' => ['nullable', 'numeric', 'min:0'],
            'new_grouts.*.density_factor' => ['nullable', 'numeric', 'min:0'],
            'new_grouts.*.cost_price' => ['nullable', 'numeric', 'min:0'],
            'delete_grout_ids' => ['nullable', 'array'],
            'delete_grout_ids.*' => ['integer', 'exists:tile_grout_types,id'],

            'products' => ['nullable', 'array'],
            'products.*.id' => ['nullable', 'integer', 'exists:tile_products,id'],
            'products.*.name' => ['required', 'string', 'max:255'],
            'products.*.length_inches' => ['required', 'numeric', 'min:0'],
            'products.*.width_inches' => ['required', 'numeric', 'min:0'],
            'products.*.thickness_inches' => ['required', 'numeric', 'min:0'],
            'products.*.cost_per_sqft' => ['required', 'numeric', 'min:0'],
            'products.*.url' => ['nullable', 'url', 'max:2048'],
            'products.*.image' => ['nullable', 'image', 'max:5120'],
            'products.*.sort_order' => ['nullable', 'integer', 'min:0'],
            'products.*.is_active' => ['nullable', 'boolean'],
            'new_products' => ['nullable', 'array'],
            'new_products.*.name' => ['nullable', 'string', 'max:255'],
            'new_products.*.url' => ['nullable', 'url', 'max:2048'],
            'new_products.*.image' => ['nullable', 'image', 'max:5120'],
            'new_products.*.length_inches' => ['nullable', 'numeric', 'min:0'],
            'new_products.*.width_inches' => ['nullable', 'numeric', 'min:0'],
            'new_products.*.thickness_inches' => ['nullable', 'numeric', 'min:0'],
            'new_products.*.cost_per_sqft' => ['nullable', 'numeric', 'min:0'],
            'delete_product_ids' => ['nullable', 'array'],
            'delete_product_ids.*' => ['integer', 'exists:tile_products,id'],

            'edges' => ['nullable', 'array'],
            'edges.*.id' => ['nullable', 'integer', 'exists:tile_edges,id'],
            'edges.*.name' => ['required', 'string', 'max:255'],
            'edges.*.url' => ['nullable', 'url', 'max:2048'],
            'edges.*.thickness' => ['nullable', 'string', 'max:32'],
            'edges.*.color' => ['nullable', 'string', 'max:120'],
            'edges.*.unit_price' => ['required', 'numeric', 'min:0'],
            'edges.*.sort_order' => ['nullable', 'integer', 'min:0'],
            'edges.*.is_active' => ['nullable', 'boolean'],
            'new_edges' => ['nullable', 'array'],
            'new_edges.*.name' => ['nullable', 'string', 'max:255'],
            'new_edges.*.url' => ['nullable', 'url', 'max:2048'],
            'new_edges.*.thickness' => ['nullable', 'string', 'max:32'],
            'new_edges.*.color' => ['nullable', 'string', 'max:120'],
            'new_edges.*.unit_price' => ['nullable', 'numeric', 'min:0'],
            'delete_edge_ids' => ['nullable', 'array'],
            'delete_edge_ids.*' => ['integer', 'exists:tile_edges,id'],
        ], [
            'products.*.url.url' => 'Each tile supplier URL must be a valid link (include https://).',
            'new_products.*.url.url' => 'Each new tile supplier URL must be a valid link (include https://), or leave it blank.',
            'new_products.*.image.image' => 'New tile images must be a JPG, PNG, GIF, or WebP under 5 MB.',
            'products.*.image.image' => 'Tile images must be a JPG, PNG, GIF, or WebP under 5 MB.',
            'edges.*.url.url' => 'Each tile edge URL must be a valid link (include https://).',
            'new_edges.*.url.url' => 'Each new tile edge URL must be a valid link (include https://), or leave it blank.',
        ]);

        // 1. Process Mortars
        foreach ($data['mortars'] ?? [] as $index => $row) {
            if (! is_array($row) || empty($row['id'])) {
                continue;
            }
            $m = TileMortarType::query()->find($row['id']);
            if ($m) {
                $m->update([
                    'name' => trim((string) $row['name']),
                    'bag_weight_lbs' => round((float) $row['bag_weight_lbs'], 2),
                    'cost_price' => round((float) $row['cost_price'], 4),
                    'sort_order' => max(0, (int) ($row['sort_order'] ?? $m->sort_order)),
                    'is_active' => $request->boolean("mortars.{$index}.is_active"),
                ]);
            }
        }
        $maxSortMortar = (int) TileMortarType::query()->max('sort_order');
        foreach ($data['new_mortars'] ?? [] as $row) {
            if (! is_array($row) || empty(trim((string) ($row['name'] ?? '')))) {
                continue;
            }
            TileMortarType::query()->create([
                'name' => trim((string) $row['name']),
                'bag_weight_lbs' => round((float) ($row['bag_weight_lbs'] ?? 50), 2),
                'cost_price' => round((float) ($row['cost_price'] ?? 0), 4),
                'sort_order' => ++$maxSortMortar,
                'is_active' => true,
            ]);
        }
        foreach ($data['delete_mortar_ids'] ?? [] as $id) {
            if ($id > 0) {
                TileMortarType::query()->whereKey($id)->delete();
            }
        }

        // 2. Process Grouts
        foreach ($data['grouts'] ?? [] as $index => $row) {
            if (! is_array($row) || empty($row['id'])) {
                continue;
            }
            $g = TileGroutType::query()->find($row['id']);
            if ($g) {
                $g->update([
                    'name' => trim((string) $row['name']),
                    'bag_weight_lbs' => round((float) $row['bag_weight_lbs'], 2),
                    'density_factor' => round((float) $row['density_factor'], 4),
                    'cost_price' => round((float) $row['cost_price'], 4),
                    'sort_order' => max(0, (int) ($row['sort_order'] ?? $g->sort_order)),
                    'is_active' => $request->boolean("grouts.{$index}.is_active"),
                ]);
            }
        }
        $maxSortGrout = (int) TileGroutType::query()->max('sort_order');
        foreach ($data['new_grouts'] ?? [] as $row) {
            if (! is_array($row) || empty(trim((string) ($row['name'] ?? '')))) {
                continue;
            }
            TileGroutType::query()->create([
                'name' => trim((string) $row['name']),
                'bag_weight_lbs' => round((float) ($row['bag_weight_lbs'] ?? 10), 2),
                'density_factor' => round((float) ($row['density_factor'] ?? 1.6), 4),
                'cost_price' => round((float) ($row['cost_price'] ?? 0), 4),
                'sort_order' => ++$maxSortGrout,
                'is_active' => true,
            ]);
        }
        foreach ($data['delete_grout_ids'] ?? [] as $id) {
            if ($id > 0) {
                TileGroutType::query()->whereKey($id)->delete();
            }
        }

        // 3. Process Products — update existing
        foreach ($data['products'] ?? [] as $index => $validated) {
            if (! is_array($validated) || empty($validated['id'])) {
                continue;
            }

            $productId = (int) $validated['id'];
            $p = TileProduct::query()->find($productId);
            if (! $p) {
                continue;
            }

            $updateData = [
                'name' => trim((string) $validated['name']),
                'url' => ! empty($validated['url']) ? trim((string) $validated['url']) : null,
                'length_inches' => round((float) $validated['length_inches'], 4),
                'width_inches' => round((float) $validated['width_inches'], 4),
                'thickness_inches' => round((float) $validated['thickness_inches'], 4),
                'cost_per_sqft' => round((float) $validated['cost_per_sqft'], 4),
                'sort_order' => max(0, (int) ($validated['sort_order'] ?? $p->sort_order)),
                'is_active' => $request->boolean("products.{$index}.is_active"),
            ];

            $file = $request->file("products.{$index}.image");
            if ($file instanceof UploadedFile && $file->isValid()) {
                $updateData['image_path'] = $this->storeProductImage($file, $p->image_path);
            }

            $p->update($updateData);
        }

        // 4. Create new products (iterate validated rows — keeps indexes aligned with files)
        $maxSortProduct = (int) TileProduct::query()->max('sort_order');
        $created = 0;
        foreach ($data['new_products'] ?? [] as $index => $validated) {
            if (! is_array($validated)) {
                continue;
            }

            $name = trim((string) ($validated['name'] ?? ''));
            if ($name === '') {
                continue;
            }

            $imagePath = null;
            $file = $request->file("new_products.{$index}.image");
            if ($file instanceof UploadedFile && $file->isValid()) {
                $imagePath = $this->storeProductImage($file);
            }

            TileProduct::query()->create([
                'name' => $name,
                'url' => ! empty($validated['url']) ? trim((string) $validated['url']) : null,
                'image_path' => $imagePath,
                'length_inches' => round((float) ($validated['length_inches'] ?? 0), 4),
                'width_inches' => round((float) ($validated['width_inches'] ?? 0), 4),
                'thickness_inches' => round((float) ($validated['thickness_inches'] ?? 0), 4),
                'cost_per_sqft' => round((float) ($validated['cost_per_sqft'] ?? 0), 4),
                'sort_order' => ++$maxSortProduct,
                'is_active' => true,
            ]);
            $created++;
        }

        foreach ($data['delete_product_ids'] ?? [] as $id) {
            if ($id > 0) {
                $p = TileProduct::query()->find($id);
                if ($p) {
                    $this->deleteProductImage($p->image_path);
                    $p->delete();
                }
            }
        }

        // 5. Process Tile Edges
        foreach ($data['edges'] ?? [] as $index => $row) {
            if (! is_array($row) || empty($row['id'])) {
                continue;
            }
            $edge = TileEdge::query()->find($row['id']);
            if ($edge) {
                $edge->update([
                    'name' => trim((string) $row['name']),
                    'url' => ! empty($row['url']) ? trim((string) $row['url']) : null,
                    'thickness' => filled($row['thickness'] ?? null) ? trim((string) $row['thickness']) : null,
                    'color' => filled($row['color'] ?? null) ? trim((string) $row['color']) : null,
                    'unit_price' => round((float) $row['unit_price'], 4),
                    'sort_order' => max(0, (int) ($row['sort_order'] ?? $edge->sort_order)),
                    'is_active' => $request->boolean("edges.{$index}.is_active"),
                ]);
            }
        }
        $maxSortEdge = (int) TileEdge::query()->max('sort_order');
        foreach ($data['new_edges'] ?? [] as $row) {
            if (! is_array($row) || empty(trim((string) ($row['name'] ?? '')))) {
                continue;
            }
            TileEdge::query()->create([
                'name' => trim((string) $row['name']),
                'url' => ! empty($row['url']) ? trim((string) $row['url']) : null,
                'thickness' => filled($row['thickness'] ?? null) ? trim((string) $row['thickness']) : null,
                'color' => filled($row['color'] ?? null) ? trim((string) $row['color']) : null,
                'unit_price' => round((float) ($row['unit_price'] ?? 0), 4),
                'sort_order' => ++$maxSortEdge,
                'is_active' => true,
            ]);
        }
        foreach ($data['delete_edge_ids'] ?? [] as $id) {
            if ($id > 0) {
                TileEdge::query()->whereKey($id)->delete();
            }
        }

        foreach (TileProduct::query()->whereNotNull('image_path')->get(['id', 'image_path']) as $product) {
            if ($product->image_path && Storage::disk('public')->exists($product->image_path)) {
                app(ProjectPhotoWebpStorage::class)->syncPublicMirror($product->image_path);
            }
        }

        $message = 'Tile database updated.';
        if ($created > 0) {
            $message .= ' Added '.$created.' new tile'.($created === 1 ? '' : 's').'.';
        }

        return redirect()
            ->route(
                'admin.quotations.tile.price-configuration',
                QuotationAfterSaveRedirect::withReturnQuery(
                    $request,
                    $request->filled('project') ? ['project' => $request->query('project')] : []
                )
            )
            ->with('success', $message);
    }

    /**
     * Strip thousands separators from masked CAD money fields before numeric validation.
     */
    protected function normalizeMoneyFields(Request $request): void
    {
        foreach (['mortars', 'new_mortars', 'grouts', 'new_grouts'] as $group) {
            $rows = $request->input($group);
            if (! is_array($rows)) {
                continue;
            }
            foreach ($rows as $i => $row) {
                if (! is_array($row) || ! array_key_exists('cost_price', $row)) {
                    continue;
                }
                $rows[$i]['cost_price'] = CadMoney::stripGrouping($row['cost_price']);
            }
            $request->merge([$group => $rows]);
        }

        foreach (['products', 'new_products'] as $group) {
            $rows = $request->input($group);
            if (! is_array($rows)) {
                continue;
            }
            foreach ($rows as $i => $row) {
                if (! is_array($row) || ! array_key_exists('cost_per_sqft', $row)) {
                    continue;
                }
                $rows[$i]['cost_per_sqft'] = CadMoney::stripGrouping($row['cost_per_sqft']);
            }
            $request->merge([$group => $rows]);
        }

        foreach (['edges', 'new_edges'] as $group) {
            $rows = $request->input($group);
            if (! is_array($rows)) {
                continue;
            }
            foreach ($rows as $i => $row) {
                if (! is_array($row) || ! array_key_exists('unit_price', $row)) {
                    continue;
                }
                $rows[$i]['unit_price'] = CadMoney::stripGrouping($row['unit_price']);
            }
            $request->merge([$group => $rows]);
        }
    }

    /**
     * Accept supplier links with or without a scheme (prepend https://).
     */
    protected function normalizeProductUrls(Request $request): void
    {
        foreach (['products', 'new_products', 'edges', 'new_edges'] as $group) {
            $rows = $request->input($group);
            if (! is_array($rows)) {
                continue;
            }
            foreach ($rows as $i => $row) {
                if (! is_array($row) || ! array_key_exists('url', $row)) {
                    continue;
                }
                $rows[$i]['url'] = $this->normalizeOptionalUrl($row['url']);
            }
            $request->merge([$group => $rows]);
        }
    }

    protected function normalizeOptionalUrl(mixed $raw): ?string
    {
        if ($raw === null) {
            return null;
        }
        $url = trim((string) $raw);
        if ($url === '') {
            return null;
        }
        if (! preg_match('#^https?://#i', $url)) {
            $url = 'https://'.$url;
        }

        return $url;
    }

    /**
     * Drop failed/empty uploads so nullable|image does not fail the whole save.
     */
    protected function dropInvalidEmptyUploads(Request $request): void
    {
        $files = $request->allFiles();
        $changed = false;

        foreach (['products', 'new_products'] as $group) {
            if (empty($files[$group]) || ! is_array($files[$group])) {
                continue;
            }
            foreach ($files[$group] as $index => $row) {
                if (! is_array($row) || ! isset($row['image'])) {
                    continue;
                }
                $file = $row['image'];
                if ($file instanceof UploadedFile && ! $file->isValid()) {
                    unset($files[$group][$index]['image']);
                    $changed = true;
                }
            }
        }

        if ($changed) {
            $request->files->replace($files);
        }
    }

    protected function storeProductImage(UploadedFile $file, ?string $previousPath = null): string
    {
        Storage::disk('public')->makeDirectory('tiles');

        $path = $file->store('tiles', 'public');
        if (! is_string($path) || $path === '' || ! Storage::disk('public')->exists($path)) {
            throw ValidationException::withMessages([
                'products' => 'Could not save the tile image. Check that storage/app/public is writable on the server.',
            ]);
        }

        app(ProjectPhotoWebpStorage::class)->syncPublicMirror($path);

        if ($previousPath && $previousPath !== $path) {
            $this->deleteProductImage($previousPath);
        }

        return $path;
    }

    protected function deleteProductImage(?string $relativePath): void
    {
        if (! filled($relativePath)) {
            return;
        }

        Storage::disk('public')->delete($relativePath);

        $mirror = public_path('storage/'.ltrim(str_replace('\\', '/', $relativePath), '/'));
        if (is_file($mirror)) {
            @unlink($mirror);
        }
    }
}

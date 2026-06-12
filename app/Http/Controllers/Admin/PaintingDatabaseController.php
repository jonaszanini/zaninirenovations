<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\PaintingPaintLine;
use App\Models\PaintingSettings;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class PaintingDatabaseController extends Controller
{
    public function edit(Request $request)
    {
        $settings = PaintingSettings::current();
        $paintLines = PaintingPaintLine::query()->orderBy('sort_order')->orderBy('id')->get();
        $qpid = $request->filled('project') ? (int) $request->query('project') : null;

        return view('admin.quotations.painting.price_configuration', compact(
            'settings',
            'paintLines',
            'qpid'
        ));
    }

    public function update(Request $request)
    {
        $data = $request->validate([
            'labor_per_sqf' => ['required', 'numeric', 'min:0'],
            'paint_per_gallon' => ['required', 'numeric', 'min:0'],
            'material_addon_fee' => ['required', 'numeric', 'min:0'],
            'sqf_per_gallon' => ['required', 'numeric', 'min:0.01'],
            'paint_lines' => ['nullable', 'array'],
            'paint_lines.*.id' => ['nullable', 'integer', 'exists:painting_paint_lines,id'],
            'paint_lines.*.name' => ['required', 'string', 'max:160'],
            'paint_lines.*.sort_order' => ['nullable', 'integer', 'min:0', 'max:9999'],
            'paint_lines.*.is_active' => ['nullable', 'boolean'],
            'new_paint_lines' => ['nullable', 'array'],
            'new_paint_lines.*.name' => ['nullable', 'string', 'max:160'],
        ]);

        $settings = PaintingSettings::query()->find(1);
        if (! $settings) {
            $settings = new PaintingSettings(['id' => 1]);
        }
        $settings->fill([
            'labor_per_sqf' => round((float) $data['labor_per_sqf'], 4),
            'paint_per_gallon' => round((float) $data['paint_per_gallon'], 2),
            'material_addon_fee' => round((float) $data['material_addon_fee'], 2),
            'sqf_per_gallon' => round((float) $data['sqf_per_gallon'], 2),
        ]);
        $settings->save();

        foreach ($data['paint_lines'] ?? [] as $row) {
            if (! is_array($row)) {
                continue;
            }
            $id = (int) ($row['id'] ?? 0);
            if ($id <= 0) {
                continue;
            }
            $line = PaintingPaintLine::query()->find($id);
            if (! $line) {
                continue;
            }
            $line->name = trim((string) $row['name']);
            $line->sort_order = max(0, (int) ($row['sort_order'] ?? $line->sort_order));
            $line->is_active = filter_var($row['is_active'] ?? true, FILTER_VALIDATE_BOOLEAN);
            $line->save();
        }

        foreach ($data['new_paint_lines'] ?? [] as $row) {
            if (! is_array($row)) {
                continue;
            }
            $name = trim((string) ($row['name'] ?? ''));
            if ($name === '') {
                continue;
            }
            $baseKey = Str::slug($name) ?: 'paint_line';
            $key = $baseKey;
            $n = 1;
            while (PaintingPaintLine::query()->where('key', $key)->exists()) {
                $key = $baseKey.'_'.$n;
                $n++;
            }
            $maxSort = (int) PaintingPaintLine::query()->max('sort_order');
            PaintingPaintLine::query()->create([
                'key' => $key,
                'name' => $name,
                'sort_order' => $maxSort + 1,
                'is_active' => true,
            ]);
        }

        return redirect()
            ->route('admin.quotations.painting.price-configuration', $request->filled('project') ? ['project' => $request->query('project')] : [])
            ->with('success', 'Painting price database updated.');
    }
}

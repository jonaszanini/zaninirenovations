<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\StairsMaterialType;
use App\Models\StairsSettings;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class StairsDatabaseController extends Controller
{
    public function edit(Request $request)
    {
        $materialTypes = StairsMaterialType::query()->orderBy('sort_order')->get();
        $settings = StairsSettings::current();
        $qpid = $request->filled('project') ? (int) $request->query('project') : null;

        return view('admin.quotations.stairs.price_configuration', compact(
            'materialTypes',
            'settings',
            'qpid'
        ));
    }

    public function update(Request $request)
    {
        $fileRules = [];
        foreach (StairsMaterialType::query()->pluck('id') as $mid) {
            $fileRules['material_photo_'.$mid] = ['nullable', 'file', 'image', 'max:5120'];
        }

        $data = $request->validate(array_merge([
            'stain_per_step' => ['required', 'numeric', 'min:0'],
            'clear_coat_per_step' => ['required', 'numeric', 'min:0'],
            'carpet_removal_per_step' => ['required', 'numeric', 'min:0'],
            'materials' => ['required', 'array'],
            'materials.*.id' => ['required', 'integer', 'exists:stairs_material_types,id'],
        ], $fileRules));

        $settings = StairsSettings::query()->find(1);
        if ($settings) {
            $settings->update([
                'stain_per_step' => round((float) $data['stain_per_step'], 2),
                'clear_coat_per_step' => round((float) $data['clear_coat_per_step'], 2),
                'carpet_removal_per_step' => round((float) $data['carpet_removal_per_step'], 2),
            ]);
        }

        foreach ($data['materials'] as $row) {
            $id = (int) $row['id'];
            $mat = StairsMaterialType::query()->find($id);
            if (! $mat) {
                continue;
            }

            $prefix = 'material_photo_'.$id;
            $pricing = $mat->mergedPricing();

            foreach (['no_open', 'one_open'] as $side) {
                foreach (['standard', 'winder', 'double_landing', 'single_landing'] as $st) {
                    $key = $side.'_'.$st;
                    $val = $request->input('materials.'.$id.'.'.$key);
                    if ($val !== null && $val !== '') {
                        $pricing[$side][$st] = round((float) str_replace(',', '', (string) $val), 2);
                    }
                }
            }
            $key2 = 'two_open_standard';
            $val2 = $request->input('materials.'.$id.'.'.$key2);
            if ($val2 !== null && $val2 !== '') {
                $pricing['two_open']['standard'] = round((float) str_replace(',', '', (string) $val2), 2);
            }
            if ($mat->nosing_mode === StairsMaterialType::NOSING_CHOICE) {
                foreach (array_keys(StairsMaterialType::nosingChoiceLabels()) as $nk) {
                    $nv = $request->input('materials.'.$id.'.nosing_'.$nk);
                    if ($nv !== null && $nv !== '') {
                        $pricing['nosing_prices'][$nk] = round((float) str_replace(',', '', (string) $nv), 2);
                    }
                }
            }

            $mat->pricing = $pricing;

            $upload = $request->file($prefix);
            if ($upload instanceof \Illuminate\Http\UploadedFile && $upload->isValid()) {
                $path = $upload->store('stairs-materials', 'public');
                if ($mat->photo_path && Storage::disk('public')->exists($mat->photo_path)) {
                    Storage::disk('public')->delete($mat->photo_path);
                }
                $mat->photo_path = $path;
            }

            $mat->save();
        }

        return redirect()
            ->back()
            ->with('success', 'Stairs price database updated.');
    }
}

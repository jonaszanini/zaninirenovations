<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\StairsMaterialType;
use App\Models\StairsSettings;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

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

    /**
     * Serve material images through the app so they work even when public/storage is not symlinked.
     */
    public function materialPhoto(StairsMaterialType $stairs_material_type)
    {
        if (! filled($stairs_material_type->photo_path)) {
            abort(404);
        }

        $relative = ltrim(str_replace('\\', '/', (string) $stairs_material_type->photo_path), '/');
        if ($relative === '' || str_contains($relative, '..')) {
            abort(404);
        }

        $disk = Storage::disk('public');
        if (! $disk->exists($relative)) {
            abort(404);
        }

        $full = $disk->path($relative);
        $root = realpath(storage_path('app/public'));
        $real = realpath($full);
        if ($root === false || $real === false || ! str_starts_with($real, $root)) {
            abort(404);
        }

        return response()->file($real);
    }

    public function nosingOptionPhoto(string $key)
    {
        if (! array_key_exists($key, StairsMaterialType::nosingChoiceLabels())) {
            abort(404);
        }

        $settings = StairsSettings::query()->find(1);
        $relative = $settings?->nosingOptionPhotoRelativePath($key);
        if ($relative === null || str_contains($relative, '..')) {
            abort(404);
        }

        $disk = Storage::disk('public');
        if (! $disk->exists($relative)) {
            abort(404);
        }

        $full = $disk->path($relative);
        $root = realpath(storage_path('app/public'));
        $real = realpath($full);
        if ($root === false || $real === false || ! str_starts_with($real, $root)) {
            abort(404);
        }

        return response()->file($real);
    }

    public function update(Request $request)
    {
        $fileRules = [];
        foreach (StairsMaterialType::query()->pluck('id') as $mid) {
            // max: size in KB; permissive mimes so screenshots still pass (stricter `image` can reject some PNGs)
            $fileRules['material_photo_'.$mid] = [
                'nullable',
                'file',
                'mimes:jpg,jpeg,png,webp,gif',
                'max:20480',
            ];
        }

        foreach (array_keys(StairsMaterialType::nosingChoiceLabels()) as $nKey) {
            $fileRules['nosing_option_photo_'.$nKey] = [
                'nullable',
                'file',
                'mimes:jpg,jpeg,png,webp,gif',
                'max:20480',
            ];
        }

        $request->validate(array_merge([
            'stain_per_step' => ['required', 'numeric', 'min:0'],
            'clear_coat_per_step' => ['required', 'numeric', 'min:0'],
            'carpet_removal_per_step' => ['required', 'numeric', 'min:0'],
        ], $fileRules));

        $settings = StairsSettings::query()->find(1);
        if ($settings) {
            $photos = is_array($settings->nosing_option_photos) ? $settings->nosing_option_photos : [];

            foreach (array_keys(StairsMaterialType::nosingChoiceLabels()) as $nKey) {
                $prefix = 'nosing_option_photo_'.$nKey;
                $upload = $request->file($prefix);
                if (! $upload instanceof UploadedFile) {
                    continue;
                }
                if (! $upload->isValid()) {
                    $msg = $upload->getErrorMessage() ?: 'Upload was rejected (check PHP upload_max_filesize / post_max_size on the server).';

                    throw ValidationException::withMessages([
                        $prefix => $msg,
                    ]);
                }

                $path = $upload->store('stairs-nosing-options', 'public');
                if ($path === false) {
                    throw ValidationException::withMessages([
                        $prefix => 'Could not store the image on disk. Check storage permissions.',
                    ]);
                }

                $prev = $photos[$nKey] ?? null;
                if (is_string($prev) && $prev !== '' && Storage::disk('public')->exists($prev)) {
                    Storage::disk('public')->delete($prev);
                }
                $photos[$nKey] = $path;
            }

            $settings->update([
                'stain_per_step' => round((float) $request->input('stain_per_step'), 2),
                'clear_coat_per_step' => round((float) $request->input('clear_coat_per_step'), 2),
                'carpet_removal_per_step' => round((float) $request->input('carpet_removal_per_step'), 2),
                'nosing_option_photos' => $photos,
            ]);
        }

        foreach (StairsMaterialType::query()->orderBy('sort_order')->get() as $mat) {
            $id = (int) $mat->id;
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
            $val2 = $request->input('materials.'.$id.'.two_open_standard');
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

            $prefix = 'material_photo_'.$id;
            $upload = $request->file($prefix);
            if ($upload instanceof UploadedFile) {
                if (! $upload->isValid()) {
                    $msg = $upload->getErrorMessage() ?: 'Upload was rejected (check PHP upload_max_filesize / post_max_size on the server).';

                    throw ValidationException::withMessages([
                        $prefix => $msg,
                    ]);
                }

                $path = $upload->store('stairs-materials', 'public');
                if ($path === false) {
                    throw ValidationException::withMessages([
                        $prefix => 'Could not store the image on disk. Check storage permissions.',
                    ]);
                }

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

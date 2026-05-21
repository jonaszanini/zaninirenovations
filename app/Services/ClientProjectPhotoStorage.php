<?php

namespace App\Services;

use App\Models\ClientProject;
use App\Models\ClientProjectPhoto;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

class ClientProjectPhotoStorage
{
    public const MAX_PHOTOS_PER_PROJECT = 15;

    public const STORAGE_FOLDER = 'client-project-photos';

    private ProjectPhotoWebpStorage $converter;

    public function __construct(?ProjectPhotoWebpStorage $converter = null)
    {
        $this->converter = $converter ?? new ProjectPhotoWebpStorage;
    }

    /**
     * @return list<ClientProjectPhoto>
     */
    public function storeUploads(ClientProject $project, array $files): array
    {
        $files = array_values(array_filter($files, fn ($f) => $f instanceof UploadedFile));
        if ($files === []) {
            return [];
        }

        $existing = (int) $project->photos()->count();
        $allowed = max(0, self::MAX_PHOTOS_PER_PROJECT - $existing);
        if ($allowed < 1) {
            throw new \RuntimeException('This project already has the maximum of '.self::MAX_PHOTOS_PER_PROJECT.' photos.');
        }

        if (count($files) > $allowed) {
            throw new \RuntimeException('Too many photos selected (max '.$allowed.' more for this project).');
        }

        $startOrder = (int) $project->photos()->max('sort_order');
        $created = [];

        foreach ($files as $i => $file) {
            $path = $this->converter->store($file, (int) $project->id, self::STORAGE_FOLDER);
            $created[] = $project->photos()->create([
                'path' => $path,
                'sort_order' => $startOrder + $i + 1,
            ]);
        }

        return $created;
    }

    public function deletePhoto(ClientProjectPhoto $photo): void
    {
        $path = $photo->path;
        $photo->delete();
        if (is_string($path) && $path !== '') {
            Storage::disk('public')->delete($path);
        }
    }

    public function purgeProjectPhotos(ClientProject $project): void
    {
        foreach ($project->photos as $photo) {
            if (filled($photo->path)) {
                Storage::disk('public')->delete($photo->path);
            }
        }
        $project->photos()->delete();
    }

    /** @return array<string, mixed> */
    public static function uploadValidationRules(int $existingCount = 0): array
    {
        $maxNew = max(1, self::MAX_PHOTOS_PER_PROJECT - max(0, $existingCount));

        return [
            'photos' => ['nullable', 'array', 'max:'.$maxNew],
            'photos.*' => ['file', 'image', 'mimes:jpg,jpeg,png,webp', 'max:10240'],
        ];
    }

    public static function validateUploadCount(Request $request, int $existingCount): ?string
    {
        $fileCount = $request->hasFile('photos') ? count((array) $request->file('photos')) : 0;
        if ($fileCount === 0) {
            return null;
        }
        $allowed = max(0, self::MAX_PHOTOS_PER_PROJECT - $existingCount);
        if ($fileCount > $allowed) {
            return 'Too many photos (max '.$allowed.' more; '.self::MAX_PHOTOS_PER_PROJECT.' per project).';
        }

        return null;
    }
}

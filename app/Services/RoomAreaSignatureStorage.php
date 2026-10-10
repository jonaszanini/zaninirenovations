<?php

namespace App\Services;

use App\Models\RoomAreaProject;
use Illuminate\Support\Facades\Storage;

class RoomAreaSignatureStorage
{
    public function pathFor(RoomAreaProject $project): string
    {
        return 'room-plans/signatures/project-'.$project->id.'.png';
    }

    public function storePng(RoomAreaProject $project, string $binary): string
    {
        $path = $this->pathFor($project);
        if ($project->signature_path && $project->signature_path !== $path) {
            Storage::disk('local')->delete($project->signature_path);
        }
        Storage::disk('local')->put($path, $binary);

        return $path;
    }

    public function delete(RoomAreaProject $project): void
    {
        if ($project->signature_path) {
            Storage::disk('local')->delete($project->signature_path);
        }
        $fallback = $this->pathFor($project);
        if (Storage::disk('local')->exists($fallback)) {
            Storage::disk('local')->delete($fallback);
        }
    }

    public function dataUri(RoomAreaProject $project): ?string
    {
        $path = $project->signature_path ?: $this->pathFor($project);
        if (! Storage::disk('local')->exists($path)) {
            return null;
        }
        $binary = Storage::disk('local')->get($path);
        if (! is_string($binary) || $binary === '') {
            return null;
        }

        return 'data:image/png;base64,'.base64_encode($binary);
    }

    public function decodePngDataUrl(string $dataUrl): ?string
    {
        if (! str_starts_with($dataUrl, 'data:image/png;base64,')) {
            return null;
        }
        $binary = base64_decode(substr($dataUrl, strlen('data:image/png;base64,')), true);
        if (! is_string($binary) || $binary === '' || strncmp($binary, "\x89PNG", 4) !== 0) {
            return null;
        }

        return $binary;
    }
}

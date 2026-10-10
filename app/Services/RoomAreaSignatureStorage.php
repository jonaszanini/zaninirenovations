<?php

namespace App\Services;

use App\Models\RoomAreaProject;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class RoomAreaSignatureStorage
{
    public function uniquePngPath(RoomAreaProject $project): string
    {
        return 'room-plans/signatures/'.$project->id.'/'.Str::uuid()->toString().'.png';
    }

    public function uniquePdfPath(RoomAreaProject $project): string
    {
        return 'room-plans/signatures/'.$project->id.'/'.Str::uuid()->toString().'.pdf';
    }

    public function put(string $path, string $binary): string
    {
        Storage::disk('local')->put($path, $binary);

        return $path;
    }

    public function get(string $path): ?string
    {
        if (! Storage::disk('local')->exists($path)) {
            return null;
        }
        $binary = Storage::disk('local')->get($path);

        return is_string($binary) && $binary !== '' ? $binary : null;
    }

    public function dataUriForPath(?string $path): ?string
    {
        $binary = $path ? $this->get($path) : null;
        if ($binary === null) {
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

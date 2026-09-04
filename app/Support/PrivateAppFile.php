<?php

namespace App\Support;

use Illuminate\Support\Facades\Storage;

/**
 * Job receipts and client-project media are stored on the private local disk.
 * Existing public-disk copies are migrated on first read, then removed from /storage.
 */
class PrivateAppFile
{
    public const FOLDERS = [
        'expense-receipts',
        'client-project-photos',
    ];

    public static function isPrivateRelative(string $relative): bool
    {
        $relative = self::normalize($relative);
        foreach (self::FOLDERS as $folder) {
            if (str_starts_with($relative, $folder.'/')) {
                return true;
            }
        }

        return false;
    }

    public static function normalize(string $relative): string
    {
        return ltrim(str_replace('\\', '/', $relative), '/');
    }

    public static function put(string $relative, string $contents): void
    {
        $relative = self::normalize($relative);
        Storage::disk('local')->put($relative, $contents);
        self::forgetPublic($relative);
    }

    public static function get(string $relative): ?string
    {
        $relative = self::normalize($relative);
        if ($relative === '' || str_contains($relative, '..')) {
            return null;
        }

        if (Storage::disk('local')->exists($relative)) {
            $bytes = Storage::disk('local')->get($relative);

            return is_string($bytes) ? $bytes : null;
        }

        $fromPublic = null;
        if (Storage::disk('public')->exists($relative)) {
            $fromPublic = Storage::disk('public')->get($relative);
        }
        if (! is_string($fromPublic) || $fromPublic === '') {
            $mirror = public_path('storage/'.$relative);
            if (is_file($mirror)) {
                $fromPublic = file_get_contents($mirror);
            }
        }
        if (! is_string($fromPublic) || $fromPublic === '') {
            return null;
        }

        Storage::disk('local')->put($relative, $fromPublic);
        self::forgetPublic($relative);

        return $fromPublic;
    }

    public static function exists(string $relative): bool
    {
        return self::absolutePath($relative) !== null;
    }

    public static function absolutePath(string $relative): ?string
    {
        $relative = self::normalize($relative);
        if ($relative === '' || str_contains($relative, '..')) {
            return null;
        }

        if (Storage::disk('local')->exists($relative)) {
            return str_replace('\\', '/', Storage::disk('local')->path($relative));
        }

        if (self::get($relative) === null) {
            return null;
        }

        return str_replace('\\', '/', Storage::disk('local')->path($relative));
    }

    public static function delete(string $relative): void
    {
        $relative = self::normalize($relative);
        if ($relative === '') {
            return;
        }
        Storage::disk('local')->delete($relative);
        self::forgetPublic($relative);
    }

    public static function forgetPublic(string $relative): void
    {
        $relative = self::normalize($relative);
        Storage::disk('public')->delete($relative);
        $mirror = public_path('storage/'.$relative);
        if (is_file($mirror)) {
            @unlink($mirror);
        }
    }
}

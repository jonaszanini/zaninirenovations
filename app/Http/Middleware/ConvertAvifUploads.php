<?php

namespace App\Http\Middleware;

use App\Support\AvifUploadConverter;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;

/**
 * Swaps any .avif upload for a WebP copy before validation and controllers run.
 */
class ConvertAvifUploads
{
    public function handle(Request $request, Closure $next)
    {
        if ($request->files->count() > 0) {
            $files = $request->allFiles();
            $changed = false;
            $converted = $this->walk($files, $changed);
            if ($changed) {
                $request->files->replace($converted);
                // Drop Laravel's cached converted-files list so file()/allFiles() see the WebP copies.
                (function () {
                    $this->convertedFiles = null;
                })->call($request);
            }
        }

        return $next($request);
    }

    /**
     * @param  array<string|int, mixed>  $files
     * @return array<string|int, mixed>
     */
    private function walk(array $files, bool &$changed): array
    {
        foreach ($files as $key => $value) {
            if (is_array($value)) {
                $files[$key] = $this->walk($value, $changed);
            } elseif ($value instanceof UploadedFile && AvifUploadConverter::isAvif($value)) {
                $webp = AvifUploadConverter::convert($value);
                if ($webp !== $value) {
                    $files[$key] = $webp;
                    $changed = true;
                }
            }
        }

        return $files;
    }
}

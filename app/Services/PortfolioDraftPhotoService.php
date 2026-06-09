<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Stashes portfolio photos during project create so they survive validation errors
 * (browsers cannot repopulate file inputs after redirect).
 */
class PortfolioDraftPhotoService
{
    public const SESSION_KEY = 'portfolio_create_draft_photos';

    public function __construct(
        protected ProjectPhotoWebpStorage $webpStorage,
    ) {}

    /**
     * @return list<string> Relative paths under the public disk
     */
    public function listPaths(): array
    {
        $paths = session(self::SESSION_KEY, []);
        if (! is_array($paths)) {
            return [];
        }

        return array_values(array_filter($paths, fn ($path) => is_string($path) && $path !== ''));
    }

    /**
     * @param  list<string>|null  $preferredOrder  e.g. old('draft_photos') after validation fail
     * @return list<array{path: string, url: string, name: string}>
     */
    public function resolveForForm(?array $preferredOrder = null): array
    {
        $sessionPaths = $this->listPaths();
        $order = array_values(array_filter(
            (array) ($preferredOrder ?? []),
            fn ($path) => is_string($path) && $path !== '',
        ));

        $merged = [];
        foreach (array_merge($order, $sessionPaths) as $path) {
            $path = ltrim(str_replace('\\', '/', $path), '/');
            if (! $this->isOwnedDraftPath($path)) {
                continue;
            }
            if (! Storage::disk('public')->exists($path)) {
                $this->forget($path);

                continue;
            }
            $merged[$path] = true;
        }

        $out = [];
        foreach (array_keys($merged) as $path) {
            $out[] = [
                'path' => $path,
                'url' => $this->publicUrl($path),
                'name' => basename($path),
            ];
        }

        return $out;
    }

    public function stashWebp(string $webpBytes): string
    {
        if ($webpBytes === '') {
            throw new RuntimeException('Image is empty.');
        }

        $path = $this->newDraftRelativePath();
        Storage::disk('public')->put($path, $webpBytes);
        $this->webpStorage->syncPublicMirror($path);
        $this->remember($path);

        return $path;
    }

    public function stashUpload(UploadedFile $file): string
    {
        $realPath = $file->getRealPath();
        if ($realPath === false || ! is_file($realPath)) {
            throw new RuntimeException('Could not read the uploaded file.');
        }

        $bytes = file_get_contents($realPath);
        if ($bytes === false || $bytes === '') {
            throw new RuntimeException('Uploaded image is empty.');
        }

        $mime = strtolower((string) $file->getMimeType());
        if ($mime === '' || ! str_starts_with($mime, 'image/')) {
            $mime = 'image/jpeg';
        }

        $webp = $this->webpStorage->convertBinaryToWebp(
            $bytes,
            $mime,
            $file->getClientOriginalName(),
        );

        return $this->stashWebp($webp);
    }

    /**
     * @param  list<string>|null  $paths
     * @return list<string>
     */
    public function validatePaths(?array $paths): array
    {
        if ($paths === null || $paths === []) {
            return [];
        }

        $allowed = array_fill_keys($this->listPaths(), true);
        $out = [];

        foreach ($paths as $path) {
            $path = ltrim(str_replace('\\', '/', (string) $path), '/');
            if (! isset($allowed[$path]) || ! $this->isOwnedDraftPath($path)) {
                continue;
            }
            if (! Storage::disk('public')->exists($path)) {
                $this->forget($path);

                continue;
            }
            $out[] = $path;
        }

        return array_values(array_unique($out));
    }

    /**
     * @param  list<string>  $paths
     * @return list<string> New paths under project-photos/{projectId}/
     */
    public function adoptPathsForProject(int $projectId, array $paths): array
    {
        $projectId = (int) $projectId;
        if ($projectId < 1) {
            throw new RuntimeException('Invalid project.');
        }

        $saved = [];
        foreach ($this->validatePaths($paths) as $draftPath) {
            $basename = basename($draftPath);
            $dest = 'project-photos/'.$projectId.'/'.$basename;
            if (Storage::disk('public')->exists($dest)) {
                $dest = 'project-photos/'.$projectId.'/'.Str::uuid()->toString().'.webp';
            }

            Storage::disk('public')->move($draftPath, $dest);
            $this->webpStorage->syncPublicMirror($dest);
            $this->forget($draftPath);
            $saved[] = $dest;
        }

        return $saved;
    }

    public function remove(string $path): bool
    {
        $path = ltrim(str_replace('\\', '/', $path), '/');
        if (! $this->isOwnedDraftPath($path) || ! in_array($path, $this->listPaths(), true)) {
            return false;
        }

        Storage::disk('public')->delete($path);
        $mirror = public_path('storage/'.$path);
        if (is_file($mirror)) {
            @unlink($mirror);
        }
        $this->forget($path);

        return true;
    }

    public function clear(): void
    {
        foreach ($this->listPaths() as $path) {
            Storage::disk('public')->delete($path);
            $mirror = public_path('storage/'.$path);
            if (is_file($mirror)) {
                @unlink($mirror);
            }
        }

        session()->forget(self::SESSION_KEY);
    }

    private function remember(string $path): void
    {
        $paths = $this->listPaths();
        if (! in_array($path, $paths, true)) {
            $paths[] = $path;
        }
        session([self::SESSION_KEY => array_values($paths)]);
    }

    private function forget(string $path): void
    {
        $paths = array_values(array_filter(
            $this->listPaths(),
            fn ($p) => $p !== $path,
        ));
        session([self::SESSION_KEY => $paths]);
    }

    private function newDraftRelativePath(): string
    {
        return $this->draftDirectory().'/'.Str::uuid()->toString().'.webp';
    }

    private function draftDirectory(): string
    {
        $userId = (int) auth()->id();
        if ($userId < 1) {
            throw new RuntimeException('You must be signed in.');
        }

        return 'project-photos/drafts/'.$userId;
    }

    private function isOwnedDraftPath(string $path): bool
    {
        $userId = (int) auth()->id();
        if ($userId < 1) {
            return false;
        }

        $prefix = 'project-photos/drafts/'.$userId.'/';

        return str_starts_with($path, $prefix)
            && ! str_contains($path, '..')
            && str_ends_with(strtolower($path), '.webp');
    }

    private function publicUrl(string $relativePath): string
    {
        return '/storage/'.ltrim(str_replace('\\', '/', $relativePath), '/');
    }
}

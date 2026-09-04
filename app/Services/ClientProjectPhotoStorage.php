<?php

namespace App\Services;

use App\Models\ClientProject;
use App\Models\ClientProjectPhoto;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class ClientProjectPhotoStorage
{
    public const MAX_PHOTOS_PER_PROJECT = 15;

    public const STORAGE_FOLDER = 'client-project-photos';

    /** @var int Kilobytes */
    public const MAX_IMAGE_KB = 10240;

    /** @var int Kilobytes */
    public const MAX_VIDEO_KB = 51200;

    /** @var int Kilobytes */
    public const MAX_PDF_KB = 20480;

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
            throw new \RuntimeException('This project already has the maximum of '.self::MAX_PHOTOS_PER_PROJECT.' files.');
        }

        if (count($files) > $allowed) {
            throw new \RuntimeException('Too many files selected (max '.$allowed.' more for this project).');
        }

        $startOrder = (int) $project->photos()->max('sort_order');
        $created = [];

        foreach ($files as $i => $file) {
            $error = self::validateSingleUpload($file);
            if ($error !== null) {
                throw new \RuntimeException($error);
            }

            $mediaType = self::classifyUpload($file);
            $storedFilename = $file->getClientOriginalName();

            $path = match ($mediaType) {
                ClientProjectPhoto::TYPE_IMAGE => $this->storeProjectImage($project, $file, $storedFilename),
                ClientProjectPhoto::TYPE_VIDEO, ClientProjectPhoto::TYPE_PDF => $this->storeRawFile($file, (int) $project->id, $mediaType),
                default => throw new \RuntimeException('Unsupported file type.'),
            };

            $created[] = $project->photos()->create([
                'path' => $path,
                'media_type' => $mediaType,
                'original_filename' => $storedFilename,
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
            \App\Support\PrivateAppFile::delete($path);
        }
    }

    public function rotateImage(ClientProjectPhoto $photo, string $direction): void
    {
        if (! $photo->isImage()) {
            throw new \RuntimeException('Only photos can be rotated.');
        }

        $relative = ltrim(str_replace('\\', '/', (string) $photo->path), '/');
        if ($relative === '' || ! str_ends_with(strtolower($relative), '.webp')) {
            throw new \RuntimeException('Only WebP photos can be rotated.');
        }

        $fullPath = \App\Support\PrivateAppFile::absolutePath($relative);
        if ($fullPath === null || ! is_file($fullPath)) {
            throw new \RuntimeException('Photo file is missing on disk.');
        }

        app(ProjectPhotoRotationService::class)->rotateWebp($fullPath, $direction);
        $this->converter->recompressWebpAtPath($fullPath);
        $photo->touch();
    }

    public function purgeProjectPhotos(ClientProject $project): void
    {
        foreach ($project->photos as $photo) {
            if (filled($photo->path)) {
                \App\Support\PrivateAppFile::delete($photo->path);
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
            'photos.*' => [
                'file',
                function (string $attribute, mixed $value, \Closure $fail): void {
                    if (! $value instanceof UploadedFile) {
                        return;
                    }
                    $message = self::validateSingleUpload($value);
                    if ($message !== null) {
                        $fail($message);
                    }
                },
            ],
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
            return 'Too many files (max '.$allowed.' more; '.self::MAX_PHOTOS_PER_PROJECT.' per project).';
        }

        return null;
    }

    public static function validateSingleUpload(UploadedFile $file): ?string
    {
        $mime = strtolower((string) $file->getMimeType());
        $ext = strtolower((string) $file->getClientOriginalExtension());
        $sizeKb = (int) ceil(((int) $file->getSize()) / 1024);

        if (self::isPdf($mime, $ext)) {
            if ($sizeKb > self::MAX_PDF_KB) {
                return 'Each PDF must be '.self::formatMaxMb(self::MAX_PDF_KB).' or smaller.';
            }

            return null;
        }

        if (self::isVideo($mime, $ext)) {
            if (! self::isAllowedVideo($mime, $ext)) {
                return 'Videos must be MP4, WebM, or MOV.';
            }
            if ($sizeKb > self::MAX_VIDEO_KB) {
                return 'Each video must be '.self::formatMaxMb(self::MAX_VIDEO_KB).' or smaller.';
            }

            return null;
        }

        if (self::isImage($mime, $ext)) {
            if ($sizeKb > self::MAX_IMAGE_KB) {
                return 'Each image must be '.self::formatMaxMb(self::MAX_IMAGE_KB).' or smaller.';
            }

            return null;
        }

        return 'Each file must be a JPEG, PNG, or WebP image, an MP4/WebM/MOV video, or a PDF.';
    }

    public static function classifyUpload(UploadedFile $file): string
    {
        $mime = strtolower((string) $file->getMimeType());
        $ext = strtolower((string) $file->getClientOriginalExtension());

        if (self::isPdf($mime, $ext)) {
            return ClientProjectPhoto::TYPE_PDF;
        }
        if (self::isVideo($mime, $ext)) {
            return ClientProjectPhoto::TYPE_VIDEO;
        }

        return ClientProjectPhoto::TYPE_IMAGE;
    }

    /** @return list<string> MIME types for the file input accept attribute */
    public static function acceptAttributeList(): array
    {
        return [
            'image/jpeg',
            'image/png',
            'image/webp',
            '.jpg',
            '.jpeg',
            '.png',
            '.webp',
            'video/mp4',
            'video/webm',
            'video/quicktime',
            '.mp4',
            '.webm',
            '.mov',
            'application/pdf',
            '.pdf',
        ];
    }

    private function storeProjectImage(
        ClientProject $project,
        UploadedFile $file,
        string &$storedFilename
    ): string {
        $basename = $this->nextProjectImageBasename($project);
        $storedFilename = $basename.'.webp';

        return $this->converter->store(
            $file,
            (int) $project->id,
            self::STORAGE_FOLDER,
            $basename
        );
    }

    private function nextProjectImageBasename(ClientProject $project): string
    {
        $max = 0;

        foreach ($project->photos()->get() as $photo) {
            if (! $photo->isImage()) {
                continue;
            }

            foreach ([
                pathinfo($photo->displayName(), PATHINFO_FILENAME),
                pathinfo((string) $photo->path, PATHINFO_FILENAME),
            ] as $base) {
                if (preg_match('/^Project-image(\d+)$/i', (string) $base, $matches)) {
                    $max = max($max, (int) $matches[1]);
                }
            }
        }

        $next = $max + 1;

        return 'Project-image'.str_pad((string) $next, 2, '0', STR_PAD_LEFT);
    }

    private function storeRawFile(UploadedFile $file, int $projectId, string $mediaType): string
    {
        $ext = $mediaType === ClientProjectPhoto::TYPE_PDF
            ? 'pdf'
            : $this->videoExtension($file);

        $leaf = Str::uuid()->toString().'.'.$ext;
        $directory = self::STORAGE_FOLDER.'/'.$projectId;
        $relativePath = $directory.'/'.$leaf;

        $realPath = $file->getRealPath();
        if ($realPath === false) {
            throw new \RuntimeException('Could not read the uploaded file.');
        }

        $bytes = file_get_contents($realPath);
        if ($bytes === false || $bytes === '') {
            throw new \RuntimeException('Could not read the uploaded file.');
        }

        \App\Support\PrivateAppFile::put($relativePath, $bytes);

        return $relativePath;
    }

    private function videoExtension(UploadedFile $file): string
    {
        $ext = strtolower((string) $file->getClientOriginalExtension());
        if (in_array($ext, ['mp4', 'webm', 'mov'], true)) {
            return $ext;
        }

        $mime = strtolower((string) $file->getMimeType());

        return match ($mime) {
            'video/webm' => 'webm',
            'video/quicktime' => 'mov',
            default => 'mp4',
        };
    }

    private function syncPublicStorageMirror(string $relativePath): void
    {
        $relativePath = ltrim(str_replace('\\', '/', $relativePath), '/');
        $publicStorageRoot = public_path('storage');

        if (! is_dir($publicStorageRoot)) {
            return;
        }

        $source = Storage::disk('public')->path($relativePath);
        if (! is_file($source)) {
            return;
        }

        $destination = public_path('storage/'.$relativePath);
        $destinationDir = dirname($destination);
        if (! is_dir($destinationDir) && ! @mkdir($destinationDir, 0775, true) && ! is_dir($destinationDir)) {
            return;
        }

        $sourceReal = realpath($source);
        $destinationReal = is_file($destination) ? realpath($destination) : false;
        if ($sourceReal !== false && $destinationReal !== false && $sourceReal === $destinationReal) {
            return;
        }

        @copy($source, $destination);
    }

    private static function isPdf(string $mime, string $ext): bool
    {
        return $mime === 'application/pdf' || $ext === 'pdf';
    }

    private static function isVideo(string $mime, string $ext): bool
    {
        return str_starts_with($mime, 'video/') || in_array($ext, ['mp4', 'webm', 'mov'], true);
    }

    private static function isAllowedVideo(string $mime, string $ext): bool
    {
        if (in_array($ext, ['mp4', 'webm', 'mov'], true)) {
            return true;
        }

        return in_array($mime, ['video/mp4', 'video/webm', 'video/quicktime'], true);
    }

    private static function isImage(string $mime, string $ext): bool
    {
        return in_array($mime, ['image/jpeg', 'image/jpg', 'image/pjpeg', 'image/png', 'image/webp'], true)
            || in_array($ext, ['jpg', 'jpeg', 'png', 'webp'], true);
    }

    private static function formatMaxMb(int $kb): string
    {
        if ($kb % 1024 === 0) {
            return (string) ($kb / 1024).' MB';
        }

        return $kb.' KB';
    }
}

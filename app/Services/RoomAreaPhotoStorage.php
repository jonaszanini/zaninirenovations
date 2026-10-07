<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Resize/compress room site photos onto the private local disk.
 * Long edge ~1600px, WebP (or JPEG fallback) at ~80% quality.
 */
class RoomAreaPhotoStorage
{
    public const MAX_EDGE_PX = 1600;

    public const QUALITY = 80;

    public const MAX_UPLOAD_KB = 10240;

    public const FOLDER = 'room-photos';

    /**
     * @return array{path: string, mime: string, width: int, height: int, bytes: int}
     */
    public function store(UploadedFile $file, int $roomId): array
    {
        $bytes = $this->readUpload($file);
        $encoded = $this->compress($bytes);
        $leaf = Str::uuid()->toString().$encoded['ext'];
        $path = self::FOLDER.'/'.$roomId.'/'.$leaf;
        Storage::disk('local')->put($path, $encoded['bytes']);

        return [
            'path' => $path,
            'mime' => $encoded['mime'],
            'width' => $encoded['width'],
            'height' => $encoded['height'],
            'bytes' => strlen($encoded['bytes']),
        ];
    }

    public function delete(?string $path): void
    {
        $path = ltrim(str_replace('\\', '/', (string) $path), '/');
        if ($path === '' || str_contains($path, '..')) {
            return;
        }
        if (Storage::disk('local')->exists($path)) {
            Storage::disk('local')->delete($path);
        }
    }

    public function deleteDirectory(int $roomId): void
    {
        $dir = self::FOLDER.'/'.$roomId;
        if (Storage::disk('local')->exists($dir)) {
            Storage::disk('local')->deleteDirectory($dir);
        }
    }

    /**
     * JPEG/PNG data URI for DomPDF (WebP is not reliable in DomPDF 2).
     */
    public function pdfDataUri(string $path): ?string
    {
        $path = ltrim(str_replace('\\', '/', $path), '/');
        if ($path === '' || ! Storage::disk('local')->exists($path)) {
            return null;
        }
        $bytes = Storage::disk('local')->get($path);
        if (! is_string($bytes) || $bytes === '') {
            return null;
        }

        $im = @imagecreatefromstring($bytes);
        if ($im === false) {
            return null;
        }

        $tmp = tempnam(sys_get_temp_dir(), 'rap');
        if ($tmp === false) {
            imagedestroy($im);

            return null;
        }
        $jpg = $tmp.'.jpg';
        @unlink($tmp);
        imagejpeg($im, $jpg, self::QUALITY);
        imagedestroy($im);
        $out = file_get_contents($jpg);
        @unlink($jpg);
        if (! is_string($out) || $out === '') {
            return null;
        }

        return 'data:image/jpeg;base64,'.base64_encode($out);
    }

    /**
     * @return array{bytes: string, mime: string, ext: string, width: int, height: int}
     */
    public function compress(string $bytes): array
    {
        if ($bytes === '') {
            throw new RuntimeException('Image is empty.');
        }

        $im = @imagecreatefromstring($bytes);
        if ($im === false) {
            throw new RuntimeException('Could not read the image.');
        }

        $srcW = imagesx($im);
        $srcH = imagesy($im);
        if ($srcW < 1 || $srcH < 1) {
            imagedestroy($im);
            throw new RuntimeException('Could not read the image.');
        }

        $max = self::MAX_EDGE_PX;
        $scale = 1.0;
        $long = max($srcW, $srcH);
        if ($long > $max) {
            $scale = $max / $long;
        }
        $dstW = max(1, (int) round($srcW * $scale));
        $dstH = max(1, (int) round($srcH * $scale));

        if ($dstW !== $srcW || $dstH !== $srcH) {
            $dst = imagecreatetruecolor($dstW, $dstH);
            if ($dst === false) {
                imagedestroy($im);
                throw new RuntimeException('Could not resize the image.');
            }
            imagealphablending($dst, false);
            imagesavealpha($dst, true);
            imagecopyresampled($dst, $im, 0, 0, 0, 0, $dstW, $dstH, $srcW, $srcH);
            imagedestroy($im);
            $im = $dst;
        }

        $tmp = tempnam(sys_get_temp_dir(), 'rap');
        if ($tmp === false) {
            imagedestroy($im);
            throw new RuntimeException('Could not create a temp file.');
        }

        $useWebp = function_exists('imagewebp');
        $target = $tmp.($useWebp ? '.webp' : '.jpg');
        @unlink($tmp);

        $ok = $useWebp
            ? imagewebp($im, $target, self::QUALITY)
            : imagejpeg($im, $target, self::QUALITY);
        imagedestroy($im);

        if (! $ok || ! is_file($target)) {
            throw new RuntimeException('Could not encode the image.');
        }

        $out = file_get_contents($target);
        @unlink($target);
        if (! is_string($out) || $out === '') {
            throw new RuntimeException('Could not encode the image.');
        }

        return [
            'bytes' => $out,
            'mime' => $useWebp ? 'image/webp' : 'image/jpeg',
            'ext' => $useWebp ? '.webp' : '.jpg',
            'width' => $dstW,
            'height' => $dstH,
        ];
    }

    private function readUpload(UploadedFile $file): string
    {
        $path = $file->getRealPath();
        if ($path === false || ! is_file($path)) {
            throw new RuntimeException('Could not read the uploaded file.');
        }
        $bytes = file_get_contents($path);
        if (! is_string($bytes) || $bytes === '') {
            throw new RuntimeException('Could not read the uploaded file.');
        }

        return $bytes;
    }
}

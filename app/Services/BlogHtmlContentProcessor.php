<?php

namespace App\Services;

use App\Support\Logging\AppLogger;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class BlogHtmlContentProcessor
{
    public function __construct(protected BlogImageStorage $images) {}

    /**
     * Convert pasted base64 images in TinyMCE HTML to stored WebP files.
     */
    public function persistInlineImages(string $html, int $postId): string
    {
        if ($html === '') {
            return $html;
        }

        return preg_replace_callback(
            '/<img([^>]*)\ssrc=["\']data:image\/([^;]+);base64,([^"\']+)["\']([^>]*)>/i',
            function (array $m) use ($postId) {
                $ext = strtolower($m[2]);
                if ($ext === 'jpeg') {
                    $ext = 'jpg';
                }
                $binary = base64_decode($m[3], true);
                if ($binary === false || $binary === '') {
                    return $m[0];
                }

                $tmp = tempnam(sys_get_temp_dir(), 'blogimg');
                if ($tmp === false) {
                    return $m[0];
                }
                $path = $tmp.'.'.$ext;
                rename($tmp, $path);
                file_put_contents($path, $binary);

                try {
                    $upload = new \Illuminate\Http\UploadedFile(
                        $path,
                        'inline.'.($ext === 'jpg' ? 'jpg' : $ext),
                        'image/'.$ext,
                        null,
                        true
                    );
                    $relative = $this->images->storeInline($upload, $postId);
                    $url = $this->images->publicUrl($relative);

                    return '<img'.$m[1].' src="'.e($url).'" loading="lazy"'.$m[4].'>';
                } catch (\Throwable $e) {
                    AppLogger::debug('blog.inline_image.import_failed', [
                        'post_id' => $postId,
                    ], $e);

                    return $m[0];
                } finally {
                    @unlink($path);
                }
            },
            $html
        ) ?? $html;
    }

    /** Ensure img tags have loading="lazy" for public output. */
    public function addLazyLoadToImages(string $html): string
    {
        if ($html === '') {
            return $html;
        }

        return preg_replace_callback(
            '/<img(?![^>]*\bloading=)([^>]*)>/i',
            fn (array $m) => '<img loading="lazy"'.$m[1].'>',
            $html
        ) ?? $html;
    }
}

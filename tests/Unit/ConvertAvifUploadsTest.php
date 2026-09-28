<?php

namespace Tests\Unit;

use App\Http\Middleware\ConvertAvifUploads;
use App\Support\AvifUploadConverter;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

class ConvertAvifUploadsTest extends TestCase
{
    public function test_non_avif_uploads_are_untouched(): void
    {
        $png = UploadedFile::fake()->create('photo.png', 10, 'image/png');
        $request = Request::create('/x', 'POST', [], [], ['photos' => [$png]]);

        (new ConvertAvifUploads)->handle($request, fn ($r) => response('ok'));

        $this->assertSame('photo.png', $request->file('photos')[0]->getClientOriginalName());
    }

    public function test_avif_is_detected_by_extension(): void
    {
        $avif = UploadedFile::fake()->create('photo.avif', 10, 'image/avif');

        $this->assertTrue(AvifUploadConverter::isAvif($avif));
    }

    public function test_avif_becomes_webp_when_server_can_decode(): void
    {
        if (! function_exists('imageavif') || ! AvifUploadConverter::supported()) {
            $this->markTestSkipped('PHP has no AVIF encoder/decoder here.');
        }

        $im = imagecreatetruecolor(8, 8);
        $path = tempnam(sys_get_temp_dir(), 'av').'.avif';
        imageavif($im, $path);
        imagedestroy($im);

        $upload = new UploadedFile($path, 'shot.avif', 'image/avif', null, true);
        $request = Request::create('/x', 'POST', [], [], ['image' => $upload]);

        (new ConvertAvifUploads)->handle($request, fn ($r) => response('ok'));

        $file = $request->file('image');
        $this->assertSame('shot.webp', $file->getClientOriginalName());
        $this->assertSame('image/webp', $file->getClientMimeType());
    }
}

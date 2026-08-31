<?php

namespace Tests\Unit\Services;

use App\Models\ClientProjectPhoto;
use App\Services\ClientProjectPhotoStorage;
use Tests\TestCase;

class ClientProjectPhotoStorageRotateTest extends TestCase
{
    public function test_rotate_rejects_video(): void
    {
        $photo = new ClientProjectPhoto([
            'path' => 'client-project-photos/1/clip.mp4',
            'media_type' => ClientProjectPhoto::TYPE_VIDEO,
        ]);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Only photos can be rotated.');

        (new ClientProjectPhotoStorage)->rotateImage($photo, 'cw');
    }

    public function test_rotate_rejects_non_webp(): void
    {
        $photo = new ClientProjectPhoto([
            'path' => 'client-project-photos/1/scan.jpg',
            'media_type' => ClientProjectPhoto::TYPE_IMAGE,
        ]);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Only WebP photos can be rotated.');

        (new ClientProjectPhotoStorage)->rotateImage($photo, 'ccw');
    }
}

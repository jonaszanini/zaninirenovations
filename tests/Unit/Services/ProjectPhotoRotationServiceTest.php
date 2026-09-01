<?php

namespace Tests\Unit\Services;

use App\Services\ProjectPhotoRotationService;
use Tests\TestCase;

class ProjectPhotoRotationServiceTest extends TestCase
{
    public function test_imagick_uses_clockwise_positive_degrees(): void
    {
        $this->assertSame(90.0, ProjectPhotoRotationService::imagickDegrees('cw'));
        $this->assertSame(-90.0, ProjectPhotoRotationService::imagickDegrees('ccw'));
    }

    public function test_gd_uses_counter_clockwise_positive_degrees(): void
    {
        $this->assertSame(-90, ProjectPhotoRotationService::gdDegrees('cw'));
        $this->assertSame(90, ProjectPhotoRotationService::gdDegrees('ccw'));
    }
}

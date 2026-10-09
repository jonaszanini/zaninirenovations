<?php

namespace Tests\Unit\Models;

use App\Models\SharedWork;
use Tests\TestCase;

class SharedWorkLabelTest extends TestCase
{
    public function test_display_label_collapses_nbsp_and_trim(): void
    {
        $nbsp = "Install\u{00A0}flooring";
        $this->assertSame('Install flooring', SharedWork::displayLabel($nbsp));
        $this->assertSame('install flooring', SharedWork::normalizeLabel($nbsp));
        $this->assertSame(
            SharedWork::normalizeLabel('Install flooring'),
            SharedWork::normalizeLabel($nbsp)
        );
    }

    public function test_slug_uses_transliteration_fallback_when_forced(): void
    {
        $this->assertSame('cafe_door', SharedWork::slugFromLabel('Café door', true));
        $this->assertSame('nino', SharedWork::slugFromLabel('Niño', true));
        $this->assertSame('item', SharedWork::slugFromLabel('安装地板', true));
    }

    public function test_uniquify_display_label_suffixes_near_duplicates(): void
    {
        $taken = [
            'install flooring' => true,
            'install flooring (2)' => true,
        ];
        $this->assertSame(
            'Install flooring (3)',
            SharedWork::uniquifyDisplayLabel('Install flooring', fn (string $norm) => isset($taken[$norm]))
        );
        $this->assertSame(
            'Unique name',
            SharedWork::uniquifyDisplayLabel('Unique name', fn (string $norm) => isset($taken[$norm]))
        );

        $long = str_repeat('a', SharedWork::LABEL_MAX);
        $takenLong = [mb_strtolower($long) => true];
        $suffixed = SharedWork::uniquifyDisplayLabel($long, fn (string $norm) => isset($takenLong[$norm]) || $norm === mb_strtolower($long));
        $this->assertLessThanOrEqual(SharedWork::LABEL_MAX, mb_strlen($suffixed));
        $this->assertStringEndsWith(' (2)', $suffixed);
    }
}

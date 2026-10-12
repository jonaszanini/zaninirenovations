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
        $this->assertSame('cafe', SharedWork::slugFromLabel('Café', true));
        $this->assertSame('cafe', SharedWork::slugFromLabel("Caf'e", true));
        $this->assertSame('cafe', SharedWork::slugFromLabel('Caf`e', true));
        $this->assertNotSame('caf_e', SharedWork::slugFromLabel('Café', true));
        $this->assertSame('nino', SharedWork::slugFromLabel('Niño', true));
        $this->assertSame('item', SharedWork::slugFromLabel('安装地板', true));
        $nfd = "Cafe\u{0301}";
        $this->assertSame('cafe', SharedWork::slugFromLabel($nfd, true));
        $this->assertSame('cafedoor', SharedWork::slugFromLabel("Cafe\u{0301}Door", true));

        // Combining marks iconv would turn into `?` must be stripped, not `_`.
        $titlo = "Ca\u{0483}fe";
        $this->assertSame('cafe', SharedWork::slugFromLabel($titlo, true));
        $this->assertSame('cafe', SharedWork::slugFromLabel($titlo, false));
        $this->assertDoesNotMatchRegularExpression('/\p{M}/u', SharedWork::asciiFold($titlo, true));
        $this->assertDoesNotMatchRegularExpression('/\p{M}/u', SharedWork::asciiFold($titlo, false));
        $this->assertSame('cafe', SharedWork::slugFromLabel($titlo));
    }

    public function test_slug_strips_apostrophes_the_same_with_and_without_intl(): void
    {
        $labels = [
            "Rock'n",
            "Rock\u{2019}n",
            'Rock"n',
            "Rock\u{201C}n\u{201D}",
            "Caf'e",
        ];
        foreach ($labels as $label) {
            $withIntl = SharedWork::slugFromLabel($label, false);
            $withoutIntl = SharedWork::slugFromLabel($label, true);
            $this->assertSame($withoutIntl, $withIntl, $label);
            $this->assertSame($withoutIntl, SharedWork::slugFromLabel($label));
        }

        $this->assertSame('rockn', SharedWork::slugFromLabel("Rock'n"));
        $this->assertSame('rockn', SharedWork::slugFromLabel("Rock'n", true));
        $this->assertSame('rockn', SharedWork::slugFromLabel("Rock\u{2019}n"));
        $this->assertSame('rockn', SharedWork::slugFromLabel("Rock\u{2019}n", true));
        $this->assertNotSame('rock_n', SharedWork::slugFromLabel("Rock'n"));
        $this->assertNotSame('rock_n', SharedWork::slugFromLabel("Rock'n", true));
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

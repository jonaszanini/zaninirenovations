<?php

namespace Tests\Unit\Support;

use App\Support\CalendarEventColors;
use PHPUnit\Framework\TestCase;

class CalendarEventColorsTest extends TestCase
{
    public function test_blue_event_uses_dark_text_for_aa(): void
    {
        $this->assertSame('#111827', CalendarEventColors::contrastColorFor('#3788d8'));
        $this->assertSame('#111827', CalendarEventColors::contrastColorFor('#3b82f6'));
    }

    public function test_personal_red_uses_white_text(): void
    {
        $this->assertSame('#ffffff', CalendarEventColors::contrastColorFor('#dc2626'));
    }

    public function test_apply_sets_fc7_color_fields(): void
    {
        $event = CalendarEventColors::apply(['title' => 'Demo'], '#3788d8');

        $this->assertSame('#3788d8', $event['color']);
        $this->assertSame('#111827', $event['contrastColor']);
        $this->assertSame('#3788d8', $event['backgroundColor']);
        $this->assertSame('#111827', $event['textColor']);
    }
}

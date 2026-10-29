<?php

namespace Tests\Feature\Admin;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\ActsAsAdmin;
use Tests\TestCase;

class AdminProjectCalendarSwipeTest extends TestCase
{
    use ActsAsAdmin;
    use RefreshDatabase;

    public function test_calendar_page_loads_swipe_navigation_script_and_mobile_hint(): void
    {
        $this->actingAsAdmin();

        $html = $this->get(route('admin.client-projects.calendar'))->assertOk()->getContent();

        $this->assertStringContainsString('admin-project-calendar-swipe.js', $html);
        $this->assertStringContainsString('AdminProjectCalendarSwipe', $html);
        $this->assertStringContainsString('cp-calendar-swipe-hint', $html);
        $this->assertStringContainsString('swipe left or right', strtolower($html));
    }
}

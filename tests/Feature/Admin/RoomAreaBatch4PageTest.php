<?php

namespace Tests\Feature\Admin;

use Tests\IntegrationTestCase;
use Tests\Support\ActsAsAdmin;

class RoomAreaBatch4PageTest extends IntegrationTestCase
{
    use ActsAsAdmin;

    public function test_rooms_tab_exposes_plan_export_phases_and_signature(): void
    {
        $this->actingAsAdmin();

        $html = $this->get(route('admin.utilities.room-area'))
            ->assertOk()
            ->assertSee('data-ra-side-tab="rooms"', false)
            ->assertSee('data-ra-plan-pdf', false)
            ->assertSee('data-ra-export-csv', false)
            ->assertSee('data-ra-export-xlsx', false)
            ->assertSee('data-ra-phase-add', false)
            ->assertSee('data-ra-phase-list', false)
            ->assertSee('data-ra-sign-pad', false)
            ->assertSee('data-ra-sign-save', false)
            ->assertSee('data-ra-sign-clear', false)
            ->assertSee('data-ra-sign-void', false)
            ->assertSee('data-ra-sign-quote', false)
            ->assertSee('Plan PDF')
            ->assertSee('Add phase')
            ->assertSee('Save signature')
            ->assertSee('Clear pad')
            ->assertSee('Void signature')
            ->getContent();

        $this->assertStringContainsString('admin-info-tip', $html);
        $this->assertStringContainsString('About Saved rooms', $html);
        $this->assertStringContainsString('About Install phases', $html);
        $this->assertStringContainsString('About Client signature', $html);
        $this->assertStringContainsString('no client portal', $html);
        $this->assertStringContainsString('Recorded when you save', $html);
        $this->assertStringContainsString('data-signature-void-template', $html);
        $this->assertStringContainsString('util-ra--fullpage', $html);
        $this->assertStringContainsString('class="admin-btn admin-btn--outline admin-btn--sm util-ra-action-btn" data-ra-quote-all', $html);
        $this->assertStringNotContainsString('mailto:', $html);
        $this->assertStringNotContainsString('type="date"', $html);
    }

    public function test_assets_wire_plan_export_phases_and_dark_theme(): void
    {
        $js = file_get_contents(public_path('js/admin-utilities-room-area.js'));
        $this->assertStringContainsString('function renderPhases(', $js);
        $this->assertStringContainsString('function bindPlanActions(', $js);
        $this->assertStringContainsString('function bindSignaturePad(', $js);
        $this->assertStringContainsString("els.signPad.addEventListener('pointerdown'", $js);
        $this->assertStringNotContainsString("els.signPad.addEventListener('touchstart'", $js);
        $this->assertStringContainsString('Nobody was emailed', $js);
        $this->assertStringContainsString('data-ra-room-phase', $js);
        $this->assertStringContainsString('standard_quotation_id', $js);
        $this->assertStringContainsString('quote_version', $js);
        $this->assertStringContainsString('els.signSave.disabled = true', $js);
        $this->assertStringContainsString('(replaced)', $js);

        $css = file_get_contents(public_path('css/admin-utilities.css'));
        $this->assertStringContainsString('.util-ra-phases', $css);
        $this->assertStringContainsString('.util-ra-sign__pad', $css);
        $this->assertStringContainsString('.util-ra-sign__date', $css);
        $this->assertStringContainsString('.util-ra-plan-actions', $css);
        $this->assertStringContainsString('touch-action: none', $css);

        $dark = file_get_contents(public_path('css/admin-dark.css'));
        $this->assertStringContainsString('html.admin-dark .util-ra-phases', $dark);
        $this->assertStringContainsString('html.admin-dark .util-ra-phase', $dark);
        $this->assertStringContainsString('html.admin-dark .util-ra-sign', $dark);
        $this->assertStringContainsString('html.admin-dark .util-ra-sign__pad', $dark);
        $this->assertStringContainsString('html.admin-dark .util-ra-sign__date', $dark);
    }
}

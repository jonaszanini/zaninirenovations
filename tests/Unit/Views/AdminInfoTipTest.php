<?php

namespace Tests\Unit\Views;

use Illuminate\Support\Facades\Blade;
use Tests\TestCase;

class AdminInfoTipTest extends TestCase
{
    public function test_component_renders_accessible_sibling_button_and_keeps_text(): void
    {
        $html = Blade::render('<x-admin.info-tip label="Board style">Clear span between posts is at most the max spacing.</x-admin.info-tip>');

        $this->assertStringContainsString('type="button"', $html);
        $this->assertStringContainsString('class="admin-info-tip"', $html);
        $this->assertStringContainsString('aria-label="About Board style"', $html);
        $aboutAbout = Blade::render('<x-admin.info-tip label="About business contact">Shown to clients.</x-admin.info-tip>');
        $this->assertStringContainsString('aria-label="About business contact"', $aboutAbout);
        $this->assertStringNotContainsString('About About', $aboutAbout);
        $this->assertStringContainsString('aria-expanded="false"', $html);
        $this->assertStringContainsString('aria-controls="', $html);
        $this->assertStringContainsString('aria-describedby="', $html);
        $this->assertStringContainsString('role="tooltip"', $html);
        $this->assertStringContainsString('Clear span between posts is at most the max spacing.', $html);
        $this->assertStringContainsString('fa-info-circle', $html);
        $this->assertMatchesRegularExpression('/<\/button>\s*<span[^>]*class="admin-info-tip__bubble"/', $html);
        $buttonEnd = strpos($html, '</button>');
        $this->assertNotFalse($buttonEnd);
        $this->assertStringNotContainsString('admin-info-tip__bubble', substr($html, 0, $buttonEnd));
    }

    public function test_text_prop_is_escaped_and_slot_html_is_trusted(): void
    {
        $escaped = Blade::render('<x-admin.info-tip label="SKU" :text="$text" />', [
            'text' => '<script>alert(1)</script>',
        ]);
        $this->assertStringNotContainsString('<script>', $escaped);
        $this->assertStringContainsString('&lt;script&gt;alert(1)&lt;/script&gt;', $escaped);

        $slot = Blade::render('<x-admin.info-tip label="Rates"><strong>Trusted</strong></x-admin.info-tip>');
        $this->assertStringContainsString('<strong>Trusted</strong>', $slot);
    }

    public function test_each_render_gets_a_unique_bubble_id(): void
    {
        $html = Blade::render(
            '<x-admin.info-tip label="A">One</x-admin.info-tip><x-admin.info-tip label="B">Two</x-admin.info-tip>'
        );

        preg_match_all('/aria-controls="([^"]+)"/', $html, $controls);
        preg_match_all('/aria-describedby="([^"]+)"/', $html, $described);
        preg_match_all('/id="(admin-info-tip-[^"]+)"/', $html, $ids);

        $this->assertCount(2, $controls[1]);
        $this->assertNotSame($controls[1][0], $controls[1][1]);
        $this->assertSame($controls[1], $described[1]);
        $this->assertSame($controls[1], $ids[1]);
    }

    public function test_include_wrapper_uses_the_slot_api(): void
    {
        $html = view('admin.partials.info_tip', [
            'tipText' => 'First gate is included in the per-foot rate.',
            'tipLabel' => 'Labour rate',
        ])->render();

        $this->assertStringContainsString('aria-label="About Labour rate"', $html);
        $this->assertStringContainsString('First gate is included in the per-foot rate.', $html);
        $this->assertStringContainsString('aria-controls="', $html);
        $this->assertStringContainsString('aria-describedby="', $html);
    }

    public function test_empty_text_renders_nothing(): void
    {
        $html = view('admin.partials.info_tip', ['tipText' => null])->render();

        $this->assertSame('', trim($html));
    }

    public function test_include_wrapper_throws_when_passed_legacy_text_or_html_keys(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage("no longer accepts 'text' or 'html'");

        view('admin.partials.info_tip', [
            'text' => 'Inherited parent text must not render.',
            'html' => '<em>Inherited parent html</em>',
            'tipText' => 'should not render once the guard fires',
        ])->render();
    }

    public function test_with_legacy_text_key_throws(): void
    {
        $this->expectException(\Illuminate\View\ViewException::class);
        $this->expectExceptionMessage("no longer accepts 'text' or 'html'");

        view('admin.partials.info_tip')->with('text', 'silently dropped')->render();
    }

    public function test_field_hint_with_legacy_html_key_throws(): void
    {
        $this->expectException(\Illuminate\View\ViewException::class);
        $this->expectExceptionMessage("no longer accepts 'text' or 'html'");

        view('admin.client_projects._cp_field_hint')->with([
            'tipText' => 'ok',
            'html' => '<em>nope</em>',
        ])->render();
    }

    public function test_include_first_and_view_paths_compile_to_explicit_render(): void
    {
        $compiler = $this->app['blade.compiler'];
        $include = $compiler->compileString("@include('admin.partials.info_tip', ['tipText' => 'Room area help'])");
        $this->assertStringContainsString('AdminInfoTipInclude::render', $include);

        $hint = $compiler->compileString("@include('admin.client_projects._cp_field_hint', ['tipText' => 'Room area help'])");
        $this->assertStringContainsString('AdminInfoTipInclude::renderHint', $hint);

        $first = $compiler->compileString("@includeFirst(['admin.partials.info_tip'], ['tipText' => 'Room area help'])");
        $this->assertStringContainsString('AdminInfoTipInclude::render', $first);

        $bare = $compiler->compileString("@include('admin.partials.info_tip')");
        $this->assertStringContainsString('AdminInfoTipInclude::render([])', $bare);

        $tipped = $compiler->compileString("@include('admin.partials.tipped_label', ['label' => 'Town', 'tipText' => 'Short name'])");
        $this->assertStringContainsString('AdminInfoTipInclude::renderTippedLabel', $tipped);
    }

    public function test_include_when_and_unless_compile_and_render(): void
    {
        $compiler = $this->app['blade.compiler'];
        $when = $compiler->compileString("@includeWhen(\$ok, 'admin.partials.info_tip', ['tipText' => 'When shown'])");
        $this->assertStringContainsString('if ($ok)', $when);
        $this->assertStringContainsString('AdminInfoTipInclude::render', $when);
        $this->assertStringNotContainsString('$directive', $when);

        $unless = $compiler->compileString("@includeUnless(\$skip, 'admin.partials.info_tip', ['tipText' => 'Unless shown'])");
        $this->assertStringContainsString('if (! ($skip))', $unless);
        $this->assertStringContainsString('AdminInfoTipInclude::render', $unless);

        $shown = \Illuminate\Support\Facades\Blade::render("@includeWhen(true, 'admin.partials.info_tip', ['tipText' => 'When shown'])");
        $this->assertStringContainsString('When shown', $shown);
        $hidden = \Illuminate\Support\Facades\Blade::render("@includeWhen(false, 'admin.partials.info_tip', ['tipText' => 'When hidden'])");
        $this->assertStringNotContainsString('When hidden', $hidden);

        $unlessHidden = \Illuminate\Support\Facades\Blade::render("@includeUnless(true, 'admin.partials.info_tip', ['tipText' => 'Unless hidden'])");
        $this->assertStringNotContainsString('Unless hidden', $unlessHidden);
        $unlessShown = \Illuminate\Support\Facades\Blade::render("@includeUnless(false, 'admin.partials.info_tip', ['tipText' => 'Unless shown'])");
        $this->assertStringContainsString('Unless shown', $unlessShown);
    }

    public function test_split_label_keeps_the_last_word_for_nowrap(): void
    {
        $this->assertSame(
            ['lead' => 'Finished ', 'last' => 'rooms'],
            \App\Support\AdminInfoTipInclude::splitLabel('Finished rooms')
        );
        $this->assertSame(
            ['lead' => 'Owner rate ', 'last' => '(CAD$)'],
            \App\Support\AdminInfoTipInclude::splitLabel('Owner rate (CAD$)')
        );
        $this->assertSame(
            ['lead' => '', 'last' => 'SKU'],
            \App\Support\AdminInfoTipInclude::splitLabel('SKU')
        );
    }

    public function test_split_label_html_balances_strong_tags(): void
    {
        $split = \App\Support\AdminInfoTipInclude::splitLabelHtml('<strong>Client provides all materials</strong>');
        $this->assertSame('<strong>Client provides all</strong> ', $split['lead']);
        $this->assertSame('<strong>materials</strong>', $split['last']);
        $this->assertTrue($split['lastIsHtml']);
        $this->assertSame(substr_count($split['lead'], '<strong>'), substr_count($split['lead'], '</strong>'));
        $this->assertSame(substr_count($split['last'], '<strong>'), substr_count($split['last'], '</strong>'));
    }

    public function test_label_is_html_for_entities_and_split_keeps_them(): void
    {
        $this->assertTrue(\App\Support\AdminInfoTipInclude::labelIsHtml('Include demolition &amp; debris'));
        $this->assertTrue(\App\Support\AdminInfoTipInclude::labelIsHtml('Width &#8212; inches'));
        $this->assertFalse(\App\Support\AdminInfoTipInclude::labelIsHtml('Include demolition & debris'));

        $split = \App\Support\AdminInfoTipInclude::splitLabelHtml('Include demolition &amp; debris');
        $this->assertSame('Include demolition &amp; ', $split['lead']);
        $this->assertSame('debris', $split['last']);
        $this->assertTrue($split['lastIsHtml']);
    }

    public function test_split_label_html_preserves_attributes_on_reopened_tags(): void
    {
        $split = \App\Support\AdminInfoTipInclude::splitLabelHtml('<a href="/admin/materials" class="admin-text-link">Open materials config</a>');
        $this->assertSame('<a href="/admin/materials" class="admin-text-link">Open materials</a> ', $split['lead']);
        $this->assertSame('<a href="/admin/materials" class="admin-text-link">config</a>', $split['last']);
        $this->assertTrue($split['lastIsHtml']);
    }

    public function test_control_id_from_name_is_rewriteable_for_templates(): void
    {
        $this->assertSame(
            'pieces-__P__-edge_banding',
            \App\Support\AdminInfoTipInclude::controlIdFromName('pieces[__P__][edge_banding]')
        );
        $this->assertSame(
            'works-__W__-labour_fixed_hst_included',
            \App\Support\AdminInfoTipInclude::controlIdFromName('works[__W__][labour_fixed_hst_included]')
        );
    }

    public function test_text_attribute_matches_slot_api(): void
    {
        $html = Blade::render('<x-admin.info-tip :text="$text" label="Existing post credit" />', [
            'text' => 'An existing-post tick that actually removes a post also subtracts the configured labour credit.',
        ]);

        $this->assertStringContainsString('type="button"', $html);
        $this->assertStringContainsString('aria-label="About Existing post credit"', $html);
        $this->assertStringContainsString('An existing-post tick that actually removes a post also subtracts the configured labour credit.', $html);
        $this->assertMatchesRegularExpression('/<\/button>\s*<span[^>]*class="admin-info-tip__bubble"/', $html);
    }
}

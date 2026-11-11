<?php

namespace Tests\Unit\Views;

use Tests\TestCase;

class ClientsDirectoryCopyViewTest extends TestCase
{
    public function test_index_and_show_include_copy_controls_and_action_links(): void
    {
        $index = file_get_contents(resource_path('views/admin/clients/index.blade.php'));
        $show = file_get_contents(resource_path('views/admin/clients/show.blade.php'));
        $partial = file_get_contents(resource_path('views/admin/partials/copy_value.blade.php'));

        $this->assertNotFalse($index);
        $this->assertNotFalse($show);
        $this->assertNotFalse($partial);

        $this->assertStringContainsString("route('admin.clients.show'", $index);
        $this->assertStringContainsString("route('admin.clients.edit'", $index);
        $this->assertStringContainsString("'copyLabel' => 'Copy email'", $index);
        $this->assertStringContainsString("'copyLabel' => 'Copy phone'", $index);
        $this->assertStringContainsString("'copyLabel' => 'Copy address'", $index);
        $this->assertStringContainsString("'copyLabel' => 'Copy secondary email'", $index);
        $this->assertStringContainsString("mailto:'.\$client->email", $index);
        $this->assertStringContainsString('telHref', $index);

        $this->assertStringContainsString("route('admin.clients.edit'", $show);
        $this->assertStringContainsString('> Edit', $show);
        $this->assertStringContainsString("'copyLabel' => 'Copy email'", $show);
        $this->assertStringContainsString("'copyLabel' => 'Copy phone'", $show);
        $this->assertStringContainsString("'copyLabel' => 'Copy address'", $show);
        $this->assertStringContainsString("route('admin.client-projects.show'", $show);

        $this->assertStringContainsString('data-copy-value', $partial);
        $this->assertStringContainsString('cl-copy-btn', $partial);
        $this->assertStringContainsString('fa-copy', $partial);
    }

    public function test_copy_button_css_has_mobile_touch_target(): void
    {
        $css = file_get_contents(public_path('css/admin-client-projects.css'));
        $this->assertNotFalse($css);
        $this->assertStringContainsString('.cl-copy-btn', $css);
        $this->assertMatchesRegularExpression(
            '/@media \(max-width: 640px\) \{[^}]*\.cl-copy-btn\s*\{[^}]*min-width:\s*44px/s',
            $css
        );
        $this->assertMatchesRegularExpression(
            '/\.cp-clients-table \.cp-clients-col-address \{\s*width:\s*28%;/',
            $css
        );
    }
}

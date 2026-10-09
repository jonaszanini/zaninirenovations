<?php

namespace Tests\Feature\Admin;

use App\Models\AiInstruction;
use App\Models\Client;
use App\Models\ClientProject;
use App\Models\ClientProjectInvoice;
use Tests\IntegrationTestCase;
use Tests\Support\ActsAsAdmin;

class AdminHotkeySaveMarkupTest extends IntegrationTestCase
{
    use ActsAsAdmin;

    public function test_rendered_admin_pages_opt_in_the_safe_save_button(): void
    {
        $this->actingAsAdmin();

        $this->assertMarkedSave(route('admin.quotations.standard.create'), [
            '/form="sq-standard-form"[^>]*data-hotkey-save|data-hotkey-save[^>]*form="sq-standard-form"/',
        ]);
        $this->assertMarkedSave(route('admin.quotations.fence.create'), [
            '/form="fq-form"[^>]*data-hotkey-save|data-hotkey-save[^>]*form="fq-form"/',
        ]);
        $this->assertMarkedSave(route('admin.quotations.basement.create'), [
            '/id="br-btn-draft"[^>]*data-hotkey-save|data-hotkey-save[^>]*id="br-btn-draft"/',
        ]);
        $this->assertMarkedSave(route('admin.quotations.millwork.create'), [
            '/id="mw-btn-draft"[^>]*data-hotkey-save|data-hotkey-save[^>]*id="mw-btn-draft"/',
        ]);
        $this->assertMarkedSave(route('admin.quotations.shed.create'), [
            '/id="sh-btn-draft"[^>]*data-hotkey-save|data-hotkey-save[^>]*id="sh-btn-draft"/',
        ]);

        $project = ClientProject::factory()->create();
        $this->assertMarkedSave(route('admin.client-projects.edit', $project), [
            '/data-hotkey-save/',
            '/Save changes/',
        ]);

        $client = Client::create(['name' => 'Hotkey Markup Client']);
        $this->assertMarkedSave(route('admin.clients.edit', $client), [
            '/data-hotkey-save/',
        ]);

        $invoice = ClientProjectInvoice::factory()->create();
        $this->assertMarkedSave(
            route('admin.client-projects.invoices.edit', [$invoice->project, $invoice]),
            [
                '/data-cp-invoice-intent="draft"[^>]*data-hotkey-save|data-hotkey-save[^>]*data-cp-invoice-intent="draft"/',
            ]
        );

        $this->assertMarkedSave(route('admin.quotations.shared-prices.edit'), [
            '/data-hotkey-save/',
            '/Save shared prices/',
        ]);
        $this->assertMarkedSave(route('admin.utilities.room-area'), [
            '/data-ra-save-room[^>]*data-hotkey-save|data-hotkey-save[^>]*data-ra-save-room/',
        ]);
        $this->assertMarkedSave(route('admin.blog.posts.create'), [
            '/id="bp-btn-save"[^>]*data-hotkey-save|data-hotkey-save[^>]*id="bp-btn-save"/',
        ]);

        $instruction = AiInstruction::forKey(AiInstruction::KEY_BLOG_POST);
        $this->assertNotNull($instruction);
        $this->assertMarkedSave(route('admin.ai-instructions.edit', $instruction), [
            '/data-hotkey-save/',
            '/Save changes/',
        ]);

        $this->assertMarkedSave(route('admin.quotations.basement.price-configuration'), [
            '/data-hotkey-save/',
            '/Save configuration/',
        ]);
        $this->assertMarkedSave(route('admin.vehicles.index'), [
            '/data-hotkey-save/',
            '/Save business contact/',
            '/Save gas price/',
            '/Save home address/',
        ]);
        $this->assertMarkedSave(route('admin.quotations.fence.config'), [
            '/data-hotkey-save/',
            '/Save links/',
            '/Save formula/',
        ]);
        $this->assertMarkedSave(route('admin.quotations.low-deck.config'), [
            '/data-hotkey-save/',
            '/Save links/',
        ]);
    }

    public function test_rendered_publish_and_send_buttons_are_not_opted_in(): void
    {
        $this->actingAsAdmin();

        $pairs = [
            [route('admin.quotations.basement.create'), 'br-btn-publish'],
            [route('admin.quotations.millwork.create'), 'mw-btn-publish'],
            [route('admin.quotations.shed.create'), 'sh-btn-publish'],
            [route('admin.blog.posts.create'), 'bp-btn-publish'],
        ];

        foreach ($pairs as [$url, $id]) {
            $html = $this->get($url)->assertOk()->getContent();
            $this->assertDoesNotMatchRegularExpression(
                '/id="'.$id.'"[^>]*data-hotkey-save|data-hotkey-save[^>]*id="'.$id.'"/',
                $html,
                $id.' must not have data-hotkey-save'
            );
            $this->assertMatchesRegularExpression('/id="'.$id.'"/', $html, $id.' should still render');
        }

        $invoice = ClientProjectInvoice::factory()->create();
        $html = $this->get(route('admin.client-projects.invoices.edit', [$invoice->project, $invoice]))
            ->assertOk()
            ->getContent();
        $this->assertDoesNotMatchRegularExpression(
            '/data-cp-invoice-intent="close"[^>]*data-hotkey-save|data-hotkey-save[^>]*data-cp-invoice-intent="close"/',
            $html
        );
    }

    /** @param  list<string>  $needles */
    private function assertMarkedSave(string $url, array $needles): void
    {
        $html = $this->get($url)->assertOk()->getContent();
        foreach ($needles as $needle) {
            if (str_starts_with($needle, '/')) {
                $this->assertMatchesRegularExpression($needle, $html, $url.' should match '.$needle);
            } else {
                $this->assertStringContainsString($needle, $html, $url.' should contain '.$needle);
            }
        }
    }
}

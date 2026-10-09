<?php

namespace Tests\Feature\Admin;

use App\Mail\ClientProjectQuoteReceivedClient;
use App\Models\AiInstruction;
use App\Models\Client;
use App\Models\ClientProject;
use App\Models\ClientProjectInvoice;
use Illuminate\Support\Facades\Mail;
use Tests\IntegrationTestCase;
use Tests\Support\ActsAsAdmin;

class AdminHotkeySaveMarkerTest extends IntegrationTestCase
{
    use ActsAsAdmin;

    public function test_standard_quotation_marks_the_safe_save_button(): void
    {
        $this->actingAsAdmin();
        $html = $this->get(route('admin.quotations.standard.create'))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('data-hotkey-save', $html);
        $this->assertMatchesRegularExpression('/form="sq-standard-form"[^>]*data-hotkey-save/', $html);
        $this->assertStringContainsString('admin-hotkey-save.js', $html);
        $this->assertStringContainsString('admin-toast.js', $html);
        $this->assertStringContainsString('id="admin-toast-flash"', $html);
    }

    public function test_fence_quotation_marks_the_safe_save_button(): void
    {
        $this->actingAsAdmin();
        $html = $this->get(route('admin.quotations.fence.create'))
            ->assertOk()
            ->getContent();

        $this->assertMatchesRegularExpression('/form="fq-form"[^>]*data-hotkey-save/', $html);
        $this->assertStringNotContainsString('name="after_save" value="create_project"'."\n".'                class="admin-btn admin-btn--ghost admin-btn--sm" data-hotkey-save', $html);
        $this->assertDoesNotMatchRegularExpression('/after_save"[^>]*data-hotkey-save/', $html);
    }

    public function test_basement_millwork_and_shed_map_to_draft_not_publish(): void
    {
        $this->actingAsAdmin();

        foreach ([
            ['admin.quotations.basement.create', 'br-btn-draft', 'br-btn-publish'],
            ['admin.quotations.millwork.create', 'mw-btn-draft', 'mw-btn-publish'],
            ['admin.quotations.shed.create', 'sh-btn-draft', 'sh-btn-publish'],
        ] as [$route, $draft, $publish]) {
            $html = $this->get(route($route))->assertOk()->getContent();
            $this->assertMatchesRegularExpression('/id="'.$draft.'"[^>]*data-hotkey-save/', $html, $draft.' should be opted in');
            $this->assertDoesNotMatchRegularExpression('/id="'.$publish.'"[^>]*data-hotkey-save/', $html, $publish.' must not be opted in');
        }
    }

    public function test_client_project_and_client_forms_mark_save(): void
    {
        $this->actingAsAdmin();
        $project = ClientProject::factory()->create();
        $client = Client::create(['name' => 'Hotkey Client']);

        $this->get(route('admin.client-projects.edit', $project))
            ->assertOk()
            ->assertSee('data-hotkey-save', false);

        $this->get(route('admin.client-projects.create'))
            ->assertOk()
            ->assertSee('data-hotkey-save', false);

        $this->get(route('admin.clients.edit', $client))
            ->assertOk()
            ->assertSee('data-hotkey-save', false);
    }

    public function test_invoice_marks_draft_not_save_and_close(): void
    {
        $this->actingAsAdmin();
        $invoice = ClientProjectInvoice::factory()->create();
        $html = $this->get(route('admin.client-projects.invoices.edit', [$invoice->clientProject, $invoice]))
            ->assertOk()
            ->getContent();

        $this->assertMatchesRegularExpression('/data-cp-invoice-intent="draft"[^>]*data-hotkey-save/', $html);
        $this->assertDoesNotMatchRegularExpression('/data-cp-invoice-intent="close"[^>]*data-hotkey-save/', $html);
    }

    public function test_shared_prices_room_area_blog_and_settings_mark_save(): void
    {
        $this->actingAsAdmin();

        $this->get(route('admin.quotations.shared-prices.edit'))
            ->assertOk()
            ->assertSee('data-hotkey-save', false)
            ->assertSee('Save shared prices');

        $this->get(route('admin.utilities.room-area'))
            ->assertOk()
            ->assertSee('data-hotkey-save', false)
            ->assertSee('data-ra-save-room', false);

        $this->get(route('admin.blog.posts.create'))
            ->assertOk()
            ->assertSee('id="bp-btn-save"', false)
            ->assertSee('data-hotkey-save', false);

        $html = $this->get(route('admin.blog.posts.create'))->assertOk()->getContent();
        $this->assertDoesNotMatchRegularExpression('/id="bp-btn-publish"[^>]*data-hotkey-save/', $html);

        $instruction = AiInstruction::forKey(AiInstruction::KEY_BLOG_POST);
        $this->assertNotNull($instruction);
        $this->get(route('admin.ai-instructions.edit', $instruction))
            ->assertOk()
            ->assertSee('data-hotkey-save', false);

        $this->get(route('admin.quotations.basement.price-configuration'))
            ->assertOk()
            ->assertSee('data-hotkey-save', false)
            ->assertSee('Save configuration');
    }

    public function test_public_pages_do_not_include_the_admin_save_script(): void
    {
        $this->get('/')->assertOk()->assertDontSee('admin-hotkey-save.js', false)->assertDontSee('admin-toast.js', false);
        $this->get('/contact')->assertOk()->assertDontSee('admin-hotkey-save.js', false);
        $this->get(route('login'))->assertOk()->assertDontSee('admin-hotkey-save.js', false);
    }

    public function test_vehicles_and_config_pages_mark_each_form_save(): void
    {
        $this->actingAsAdmin();

        $vehicles = $this->get(route('admin.vehicles.index'))->assertOk()->getContent();
        $this->assertStringContainsString('Save business contact', $vehicles);
        $this->assertMatchesRegularExpression('/Save business contact[\\s\\S]*?data-hotkey-save|data-hotkey-save[\\s\\S]*?Save business contact/', $vehicles);

        $fence = $this->get(route('admin.quotations.fence.config'))->assertOk()->getContent();
        $this->assertStringContainsString('data-hotkey-save', $fence);
        $this->assertStringContainsString('Save formula', $fence);

        $deck = $this->get(route('admin.quotations.low-deck.config'))->assertOk()->getContent();
        $this->assertStringContainsString('data-hotkey-save', $deck);
    }

    public function test_hotkey_save_never_sends_the_client_quote_email(): void
    {
        Mail::fake();
        $this->actingAsAdmin();

        $this->post(route('admin.client-projects.store'), [
            'name' => 'Hotkey no-email',
            'contacted_via' => 'email',
            'client_name' => 'Hotkey Client',
            'client_email' => 'client@example.com',
            'billing_type' => 'fixed',
            'send_client_email' => '1',
            '_hotkey_save' => '1',
        ])->assertRedirect()->assertSessionHas('success');

        Mail::assertNotSent(ClientProjectQuoteReceivedClient::class);

        $project = ClientProject::where('name', 'Hotkey no-email')->first();
        $this->assertNotNull($project);
        $this->assertStringContainsString('No client email sent', session('success'));
    }
}

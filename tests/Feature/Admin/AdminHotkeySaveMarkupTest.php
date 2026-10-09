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

        $this->assertButtonMarked(route('admin.quotations.standard.create'), [
            'form="sq-standard-form"',
            'data-hotkey-save',
        ]);
        $this->assertButtonMarked(route('admin.quotations.fence.create'), [
            'form="fq-form"',
            'data-hotkey-save',
        ]);
        $this->assertButtonMarked(route('admin.quotations.basement.create'), [
            'id="br-btn-draft"',
            'data-hotkey-save',
        ], 'Save as draft');
        $this->assertButtonMarked(route('admin.quotations.millwork.create'), [
            'id="mw-btn-draft"',
            'data-hotkey-save',
        ], 'Save as draft');
        $this->assertButtonMarked(route('admin.quotations.shed.create'), [
            'id="sh-btn-draft"',
            'data-hotkey-save',
        ], 'Save as draft');

        $project = ClientProject::factory()->create();
        $this->assertButtonMarked(route('admin.client-projects.edit', $project), [
            'data-hotkey-save',
        ], 'Save changes');

        $client = Client::create(['name' => 'Hotkey Markup Client']);
        $this->assertButtonMarked(route('admin.clients.edit', $client), [
            'data-hotkey-save',
        ], 'Save');

        $invoice = ClientProjectInvoice::factory()->create();
        $this->assertButtonMarked(
            route('admin.client-projects.invoices.edit', [$invoice->project, $invoice]),
            [
                'data-cp-invoice-intent="draft"',
                'data-hotkey-save',
            ]
        );

        $this->assertButtonMarked(route('admin.quotations.shared-prices.edit'), [
            'form="sp-lib-form"',
            'data-hotkey-save',
        ], 'Save shared prices');
        $this->assertButtonMarked(route('admin.utilities.room-area'), [
            'data-ra-save-room',
            'data-hotkey-save',
        ]);
        $this->assertButtonMarked(route('admin.blog.posts.create'), [
            'id="bp-btn-save"',
            'data-hotkey-save',
        ]);

        $instruction = AiInstruction::forKey(AiInstruction::KEY_BLOG_POST);
        $this->assertNotNull($instruction);
        $this->assertButtonMarked(route('admin.ai-instructions.edit', $instruction), [
            'data-hotkey-save',
        ], 'Save changes');

        $this->assertButtonMarked(route('admin.quotations.basement.price-configuration'), [
            'form="br-config-form"',
            'data-hotkey-save',
        ], 'Save configuration');

        $vehicles = $this->get(route('admin.vehicles.index'))->assertOk()->getContent();
        $this->assertButtonHas($vehicles, ['data-hotkey-save'], 'Save business contact');
        $this->assertButtonHas($vehicles, ['data-hotkey-save'], 'Save gas price');
        $this->assertButtonHas($vehicles, ['data-hotkey-save'], 'Save home address');
        $this->assertMatchesRegularExpression(
            '/id="company-gas-price"[^>]*data-cp-money-entry="direct"|data-cp-money-entry="direct"[^>]*id="company-gas-price"/',
            $vehicles
        );

        $fence = $this->get(route('admin.quotations.fence.config'))->assertOk()->getContent();
        $this->assertButtonHas($fence, ['data-hotkey-save'], 'Save links');
        $this->assertButtonHas($fence, ['data-hotkey-save'], 'Save formula');

        $deck = $this->get(route('admin.quotations.low-deck.config'))->assertOk()->getContent();
        $this->assertButtonHas($deck, ['data-hotkey-save'], 'Save links');
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
                '/<(?:button|input|a)\b(?=[^>]*\bid="'.$id.'")(?=[^>]*\bdata-hotkey-save)[^>]*>/',
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
            '/<(?:button|input)\b(?=[^>]*\bdata-cp-invoice-intent="close")(?=[^>]*\bdata-hotkey-save)[^>]*>/',
            $html
        );
    }

    /** @param  list<string>  $attrs */
    private function assertButtonMarked(string $url, array $attrs, string $label = ''): void
    {
        $html = $this->get($url)->assertOk()->getContent();
        $this->assertButtonHas($html, $attrs, $label, $url);
    }

    /** @param  list<string>  $attrs */
    private function assertButtonHas(string $html, array $attrs, string $label = '', string $context = ''): void
    {
        $lookaheads = '';
        foreach ($attrs as $attr) {
            $lookaheads .= '(?=[^>]*'.preg_quote($attr, '/').')';
        }
        $labelPart = $label !== ''
            ? '[^>]*>[\s\S]{0,200}?'.preg_quote($label, '/')
            : '[^>]*>';
        $this->assertMatchesRegularExpression(
            '/<(?:button|input)\b'.$lookaheads.$labelPart.'/',
            $html,
            ($context !== '' ? $context.' ' : '').'should mark the '.($label !== '' ? $label.' ' : '').'button ('.implode(' ', $attrs).')'
        );
    }
}

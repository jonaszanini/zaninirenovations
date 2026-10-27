<?php

namespace Tests\Feature\Admin;

use App\Models\SupplierSite;
use App\Models\SupplierTag;
use Tests\IntegrationTestCase;
use Tests\Support\ActsAsAdmin;

class SupplierSiteTest extends IntegrationTestCase
{
    use ActsAsAdmin;

    public function test_store_rejects_invalid_logo_url_scheme(): void
    {
        $this->actingAsAdmin();

        $response = $this->from(route('admin.supplier-sites.create'))
            ->post(route('admin.supplier-sites.store'), [
                'name' => 'Debline',
                'url' => 'https://debline.com',
                'logo_url' => 'ftp://debline.com/logo.png',
            ]);

        $response->assertSessionHasErrors('logo_url');
    }

    public function test_store_creates_supplier_with_tags(): void
    {
        $this->actingAsAdmin();

        $response = $this->post(route('admin.supplier-sites.store'), [
            'name' => 'Debline',
            'url' => 'debline.com',
            'logo_url' => 'https://debline.com/logo.png',
            'tags_input' => 'Bin Rental, crushed stone, bedding sand',
            'notes' => 'Landscaping / aggregates',
            'phone' => '613-555-0100',
            'email' => 'orders@debline.com',
            'address' => '100 Industrial Rd, Carleton Place, ON',
            'is_favorite' => '1',
        ]);

        $response->assertRedirect(route('admin.supplier-sites.index'));
        $response->assertSessionHas('success');

        $site = SupplierSite::query()->where('name', 'Debline')->first();
        $this->assertNotNull($site);
        $this->assertSame('https://debline.com', $site->url);
        $this->assertSame('https://debline.com/logo.png', $site->logo_url);
        $this->assertSame('6135550100', $site->phone);
        $this->assertSame('orders@debline.com', $site->email);
        $this->assertSame('100 Industrial Rd, Carleton Place, ON', $site->address);
        $this->assertTrue($site->is_favorite);
        // MySQL's default utf8mb4_unicode_ci collation sorts case-insensitively,
        // so "bedding" < "Bin" (e < i) regardless of case.
        $this->assertSame(
            ['bedding sand', 'Bin Rental', 'crushed stone'],
            $site->tags()->orderBy('name')->pluck('name')->all()
        );
    }

    public function test_index_search_finds_supplier_by_tag_name(): void
    {
        $this->actingAsAdmin();

        $this->post(route('admin.supplier-sites.store'), [
            'name' => 'Debline',
            'url' => 'https://debline.com',
            'tags_input' => 'Bin Rental, crushed stone',
        ]);

        $this->post(route('admin.supplier-sites.store'), [
            'name' => 'Other Supply',
            'url' => 'https://example.com',
            'tags_input' => 'lumber',
        ]);

        $response = $this->get(route('admin.supplier-sites.index', ['q' => 'crushed']));

        $response->assertOk();
        $response->assertSee('Debline', false);
        $response->assertDontSee('Other Supply', false);
    }

    public function test_index_tag_filter_limits_results(): void
    {
        $this->actingAsAdmin();

        $this->post(route('admin.supplier-sites.store'), [
            'name' => 'Debline',
            'url' => 'https://debline.com',
            'tags_input' => 'Bin Rental',
        ]);

        $this->post(route('admin.supplier-sites.store'), [
            'name' => 'Other Supply',
            'url' => 'https://example.com',
            'tags_input' => 'lumber',
        ]);

        $tag = SupplierTag::query()->where('slug', 'bin-rental')->firstOrFail();

        $response = $this->get(route('admin.supplier-sites.index', ['tag' => $tag->slug]));

        $response->assertOk();
        $response->assertSee('Debline', false);
        $response->assertDontSee('Other Supply', false);
    }

    public function test_index_search_finds_supplier_by_email(): void
    {
        $this->actingAsAdmin();

        $this->post(route('admin.supplier-sites.store'), [
            'name' => 'Debline',
            'url' => 'https://debline.com',
            'email' => 'orders@debline.com',
        ]);

        $this->post(route('admin.supplier-sites.store'), [
            'name' => 'Other Supply',
            'url' => 'https://example.com',
            'email' => 'info@example.com',
        ]);

        $response = $this->get(route('admin.supplier-sites.index', ['q' => 'orders@debline']));

        $response->assertOk();
        $response->assertSee('Debline', false);
        $response->assertDontSee('Other Supply', false);
    }

    public function test_update_syncs_tags_and_normalizes_url(): void
    {
        $this->actingAsAdmin();

        $this->post(route('admin.supplier-sites.store'), [
            'name' => 'Debline',
            'url' => 'https://debline.com',
            'tags_input' => 'Bin Rental',
        ]);

        $site = SupplierSite::query()->where('name', 'Debline')->firstOrFail();

        $response = $this->put(route('admin.supplier-sites.update', $site), [
            'name' => 'Debline Landscaping',
            'url' => 'www.debline.com/shop',
            'tags_input' => 'Bin Rental, bedding sand',
            'is_favorite' => '0',
        ]);

        $response->assertRedirect(route('admin.supplier-sites.index'));

        $site->refresh();
        $this->assertSame('Debline Landscaping', $site->name);
        $this->assertSame('https://www.debline.com/shop', $site->url);
        // MySQL's default utf8mb4_unicode_ci collation sorts case-insensitively,
        // so "bedding" < "Bin" (e < i) regardless of case.
        $this->assertSame(
            ['bedding sand', 'Bin Rental'],
            $site->tags()->orderBy('name')->pluck('name')->all()
        );
    }
}

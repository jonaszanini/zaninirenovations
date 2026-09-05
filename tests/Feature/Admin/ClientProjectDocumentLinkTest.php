<?php

namespace Tests\Feature\Admin;

use App\Models\ClientProject;
use App\Models\ClientProjectDocumentLink;
use Tests\IntegrationTestCase;
use Tests\Support\ActsAsAdmin;

class ClientProjectDocumentLinkTest extends IntegrationTestCase
{
    use ActsAsAdmin;

    public function test_edit_page_includes_document_link_form(): void
    {
        $this->actingAsAdmin();

        $project = ClientProject::factory()->create();

        $this->get(route('admin.client-projects.edit', $project))
            ->assertOk()
            ->assertSee('Document links')
            ->assertSee('Save links')
            ->assertDontSee('e.g. Material list');
    }

    public function test_can_save_multiple_document_links_and_detect_types(): void
    {
        $this->actingAsAdmin();

        $project = ClientProject::factory()->create();

        $this->from(route('admin.client-projects.edit', $project))
            ->post(route('admin.client-projects.document-links.store', $project), [
                'document_links' => [
                    ['url' => 'https://files.example.com/scope.docx', 'kind' => 'auto'],
                    ['url' => 'https://1drv.ms/x/s!abc', 'kind' => 'auto'],
                    ['url' => 'https://onedrive.live.com/edit.aspx?file=Patricia%20bathroom.pptx', 'kind' => 'auto'],
                ],
            ])
            ->assertRedirect(route('admin.client-projects.edit', $project));

        $links = $project->documentLinks()->orderBy('sort_order')->get();
        $this->assertCount(3, $links);
        $this->assertSame(ClientProjectDocumentLink::KIND_WORD, $links[0]->kind);
        $this->assertSame('scope.docx', $links[0]->title);
        $this->assertSame(ClientProjectDocumentLink::KIND_EXCEL, $links[1]->kind);
        $this->assertSame(ClientProjectDocumentLink::KIND_PPT, $links[2]->kind);
        $this->assertSame('Patricia bathroom.pptx', $links[2]->title);
    }

    public function test_rejects_javascript_urls(): void
    {
        $this->actingAsAdmin();

        $project = ClientProject::factory()->create();

        $this->from(route('admin.client-projects.edit', $project))
            ->post(route('admin.client-projects.document-links.store', $project), [
                'document_links' => [
                    ['url' => 'javascript:alert(1)', 'title' => '', 'kind' => 'auto'],
                ],
            ])
            ->assertSessionHasErrors('document_links.0.url');

        $this->assertSame(0, $project->documentLinks()->count());
    }

    public function test_can_delete_a_document_link(): void
    {
        $this->actingAsAdmin();

        $project = ClientProject::factory()->create();
        $link = $project->documentLinks()->create([
            'url' => 'https://files.example.com/scope.docx',
            'title' => 'Scope',
            'kind' => ClientProjectDocumentLink::KIND_WORD,
            'sort_order' => 1,
        ]);

        $this->from(route('admin.client-projects.edit', $project))
            ->delete(route('admin.client-projects.document-links.destroy', [$project, $link]))
            ->assertRedirect(route('admin.client-projects.edit', $project));

        $this->assertSame(0, $project->documentLinks()->count());
    }
}

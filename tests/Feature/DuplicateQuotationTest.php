<?php

namespace Tests\Feature;

use App\Models\ClientProject;
use App\Models\ClientProjectQuotation;
use Tests\IntegrationTestCase;
use Tests\Support\ActsAsAdmin;

class DuplicateQuotationTest extends IntegrationTestCase
{
    use ActsAsAdmin;

    public function test_duplicate_quotation_same_project(): void
    {
        $this->actingAsAdmin();

        $project = ClientProject::factory()->create([
            'status' => ClientProject::STATUS_QUOTE_PENDING,
        ]);

        $quotation = new ClientProjectQuotation();
        $quotation->client_project_id = $project->id;
        $quotation->title = 'Test Quotation';
        $quotation->status = ClientProjectQuotation::STATUS_DRAFT;
        $quotation->type = ClientProjectQuotation::SOURCE_STANDARD;
        $quotation->source_type = ClientProjectQuotation::SOURCE_STANDARD;
        $quotation->quotation_number = ClientProjectQuotation::generateNumber('standard');
        $quotation->items = [
            ['description' => 'Labour', 'amount' => 500, 'apply_hst' => true],
            ['description' => 'Materials', 'amount' => 200, 'apply_hst' => true],
        ];
        $quotation->work_description = 'Test work description';
        $quotation->version = 1;
        $quotation->save();

        $response = $this->post(route('admin.client-projects.quotation.duplicate', [
            'client_project' => $project->id,
            'quotation' => $quotation->id,
        ]), [
            'duplicate_target' => 'same',
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');

        $this->assertDatabaseCount('client_project_quotations', 2);

        $newQuotation = ClientProjectQuotation::where('id', '!=', $quotation->id)->first();

        $this->assertNotNull($newQuotation);
        $this->assertEquals($project->id, $newQuotation->client_project_id);
        $this->assertEquals('Test Quotation', $newQuotation->title);
        $this->assertEquals(ClientProjectQuotation::STATUS_DRAFT, $newQuotation->status);
        $this->assertEquals('Test work description', $newQuotation->work_description);
        $this->assertNotEquals($quotation->quotation_number, $newQuotation->quotation_number);
        $this->assertEquals($quotation->items, $newQuotation->items);
    }

    public function test_duplicate_quotation_different_project(): void
    {
        $this->actingAsAdmin();

        $sourceProject = ClientProject::factory()->create([
            'status' => ClientProject::STATUS_QUOTE_PENDING,
            'name' => 'Source Project',
        ]);

        $targetProject = ClientProject::factory()->create([
            'status' => ClientProject::STATUS_QUOTE_PENDING,
            'name' => 'Target Project',
        ]);

        $quotation = new ClientProjectQuotation();
        $quotation->client_project_id = $sourceProject->id;
        $quotation->title = 'Original Quote';
        $quotation->status = ClientProjectQuotation::STATUS_SENT;
        $quotation->type = ClientProjectQuotation::SOURCE_STANDARD;
        $quotation->source_type = ClientProjectQuotation::SOURCE_STANDARD;
        $quotation->quotation_number = ClientProjectQuotation::generateNumber('standard');
        $quotation->items = [
            ['description' => 'Service A', 'amount' => 1000, 'apply_hst' => true],
        ];
        $quotation->work_description = 'Original work description';
        $quotation->version = 2;
        $quotation->save();

        $response = $this->post(route('admin.client-projects.quotation.duplicate', [
            'client_project' => $sourceProject->id,
            'quotation' => $quotation->id,
        ]), [
            'duplicate_target' => 'other',
            'target_project_id' => $targetProject->id,
        ]);

        $response->assertRedirect(route('admin.client-projects.show', $targetProject) . '#quotation');
        $response->assertSessionHas('success');

        $this->assertDatabaseCount('client_project_quotations', 2);

        $newQuotation = ClientProjectQuotation::where('client_project_id', $targetProject->id)->first();

        $this->assertNotNull($newQuotation);
        $this->assertEquals($targetProject->id, $newQuotation->client_project_id);
        $this->assertEquals('Original Quote', $newQuotation->title);
        $this->assertEquals(ClientProjectQuotation::STATUS_DRAFT, $newQuotation->status);
        $this->assertNotEquals($quotation->quotation_number, $newQuotation->quotation_number);
        $this->assertEquals(1, $newQuotation->version);
    }

    public function test_duplicate_to_closed_project_fails(): void
    {
        $this->actingAsAdmin();

        $sourceProject = ClientProject::factory()->create([
            'status' => ClientProject::STATUS_QUOTE_PENDING,
        ]);

        $closedProject = ClientProject::factory()->create([
            'status' => ClientProject::STATUS_COMPLETED,
        ]);

        $quotation = new ClientProjectQuotation();
        $quotation->client_project_id = $sourceProject->id;
        $quotation->title = 'Test Quote';
        $quotation->status = ClientProjectQuotation::STATUS_DRAFT;
        $quotation->type = ClientProjectQuotation::SOURCE_STANDARD;
        $quotation->source_type = ClientProjectQuotation::SOURCE_STANDARD;
        $quotation->quotation_number = ClientProjectQuotation::generateNumber('standard');
        $quotation->items = [];
        $quotation->version = 1;
        $quotation->save();

        $response = $this->post(route('admin.client-projects.quotation.duplicate', [
            'client_project' => $sourceProject->id,
            'quotation' => $quotation->id,
        ]), [
            'duplicate_target' => 'other',
            'target_project_id' => $closedProject->id,
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('error');

        $this->assertDatabaseCount('client_project_quotations', 1);
    }

    public function test_duplicate_legacy_quotation_fails(): void
    {
        $this->actingAsAdmin();

        $project = ClientProject::factory()->create([
            'status' => ClientProject::STATUS_QUOTE_PENDING,
        ]);

        $quotation = new ClientProjectQuotation();
        $quotation->client_project_id = $project->id;
        $quotation->title = 'Legacy Quote';
        $quotation->status = ClientProjectQuotation::STATUS_DRAFT;
        $quotation->type = ClientProjectQuotation::SOURCE_MANUAL;
        $quotation->quotation_number = 'MN-2024-0001';
        $quotation->items = [];
        $quotation->version = 1;
        $quotation->save();

        $response = $this->post(route('admin.client-projects.quotation.duplicate', [
            'client_project' => $project->id,
            'quotation' => $quotation->id,
        ]));

        $response->assertRedirect();
        $response->assertSessionHas('error', 'Legacy manual documents cannot be duplicated.');

        $this->assertDatabaseCount('client_project_quotations', 1);
    }

    public function test_duplicate_preserves_quotation_fields(): void
    {
        $this->actingAsAdmin();

        $project = ClientProject::factory()->create([
            'status' => ClientProject::STATUS_QUOTE_PENDING,
        ]);

        $quotation = new ClientProjectQuotation();
        $quotation->client_project_id = $project->id;
        $quotation->title = 'Full Quote';
        $quotation->status = ClientProjectQuotation::STATUS_ACCEPTED;
        $quotation->type = ClientProjectQuotation::SOURCE_FENCE;
        $quotation->source_type = ClientProjectQuotation::SOURCE_FENCE;
        $quotation->quotation_number = 'FN-2024-0099';
        $quotation->items = [
            ['description' => 'Fence installation', 'amount' => 2500, 'apply_hst' => true],
            ['description' => 'Posts', 'amount' => 500, 'apply_hst' => true],
        ];
        $quotation->work_description = '<p>Custom fence installation with cedar posts.</p>';
        $quotation->labour = true;
        $quotation->material = true;
        $quotation->payment_installments = 2;
        $quotation->payment_percentages = [50, 50];
        $quotation->version = 3;
        $quotation->save();

        $response = $this->post(route('admin.client-projects.quotation.duplicate', [
            'client_project' => $project->id,
            'quotation' => $quotation->id,
        ]));

        $response->assertRedirect();
        $response->assertSessionHas('success');

        $newQuotation = ClientProjectQuotation::where('id', '!=', $quotation->id)->first();

        $this->assertEquals('Full Quote', $newQuotation->title);
        $this->assertEquals(ClientProjectQuotation::STATUS_DRAFT, $newQuotation->status);
        $this->assertEquals(ClientProjectQuotation::SOURCE_FENCE, $newQuotation->type);
        $this->assertEquals($quotation->items, $newQuotation->items);
        $this->assertEquals($quotation->work_description, $newQuotation->work_description);
        $this->assertTrue($newQuotation->labour);
        $this->assertTrue($newQuotation->material);
        $this->assertEquals(2, $newQuotation->payment_installments);
        $this->assertEquals([50, 50], $newQuotation->payment_percentages);
        $this->assertEquals(1, $newQuotation->version);
    }
}

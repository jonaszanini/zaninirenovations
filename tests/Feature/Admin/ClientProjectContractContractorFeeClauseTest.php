<?php

namespace Tests\Feature\Admin;

use App\Models\AiInstruction;
use App\Models\ClientProject;
use App\Models\ClientProjectContract;
use App\Services\GeminiService;
use App\Support\ContractContractorFeeClause;
use Tests\IntegrationTestCase;
use Tests\Support\ActsAsAdmin;

class ClientProjectContractContractorFeeClauseTest extends IntegrationTestCase
{
    use ActsAsAdmin;

    public function test_ai_draft_always_includes_the_change_order_fee_clause(): void
    {
        $this->actingAsAdmin();
        $project = ClientProject::factory()->create();
        AiInstruction::query()->updateOrCreate(
            ['key' => AiInstruction::KEY_CONTRACT],
            [
                'name' => 'Contract generation',
                'instruction' => 'Write a contract from the supplied project context.',
            ]
        );

        $this->mock(GeminiService::class, function ($mock) {
            $mock->shouldReceive('generate')
                ->once()
                ->andReturn("1. Scope of Work\n\nFraming and drywall as specified.");
        });

        $response = $this->postJson(route('admin.client-projects.contract.ai-draft', $project), [
            'context' => 'Basement quotation with a 10% contractor fee.',
        ])->assertOk();

        $text = (string) $response->json('text');
        $html = (string) $response->json('html');
        $this->assertStringContainsString('Framing and drywall as specified.', $text);
        $this->assertStringContainsString(ContractContractorFeeClause::SENTENCE, $text);
        $this->assertStringContainsString(ContractContractorFeeClause::SENTENCE, $html);
        $this->assertSame(1, substr_count($text, ContractContractorFeeClause::SENTENCE));
    }

    public function test_new_contract_preview_shows_clause_and_old_agreed_contract_does_not(): void
    {
        $this->actingAsAdmin();

        $newProject = ClientProject::factory()->create(['client_name' => 'New Contract Client']);
        ClientProjectContract::query()->create([
            'client_project_id' => $newProject->id,
            'html_content' => '<p>Basement renovation scope.</p>',
            'version' => 1,
            'include_contractor_fee_change_order_clause' => true,
        ]);

        $newHtml = $this->get(route('admin.client-projects.show', $newProject))
            ->assertOk()
            ->getContent();
        $this->assertStringContainsString('cp-contractor-fee-clause', $newHtml);
        $this->assertStringContainsString(ContractContractorFeeClause::SENTENCE, $newHtml);

        $oldProject = ClientProject::factory()->create(['client_name' => 'Agreed Contract Client']);
        ClientProjectContract::query()->create([
            'client_project_id' => $oldProject->id,
            'html_content' => '<p>Scope of work for the renovation.</p>',
            'version' => 1,
            'sent_at' => now(),
            'include_contractor_fee_change_order_clause' => false,
        ]);

        $oldHtml = $this->get(route('admin.client-projects.show', $oldProject))
            ->assertOk()
            ->getContent();
        $this->assertStringNotContainsString('cp-contractor-fee-clause', $oldHtml);
        $this->assertStringNotContainsString(ContractContractorFeeClause::SENTENCE, $oldHtml);
        $this->assertStringContainsString('Scope of work for the renovation.', $oldHtml);
    }
}

<?php

namespace Tests\Unit\Support;

use App\Models\ClientProject;
use App\Models\ClientProjectContract;
use App\Support\ContractContractorFeeClause;
use Tests\TestCase;

class ContractContractorFeeClauseTest extends TestCase
{
    public function test_ensure_methods_append_the_sentence_once(): void
    {
        $markdown = ContractContractorFeeClause::ensureInMarkdown("1. Scope of Work\n\nFraming and drywall.");
        $this->assertStringContainsString('1. Scope of Work', $markdown);
        $this->assertStringContainsString(ContractContractorFeeClause::SENTENCE, $markdown);
        $this->assertSame($markdown, ContractContractorFeeClause::ensureInMarkdown($markdown));

        $html = ContractContractorFeeClause::ensureInHtml('<p>Framing and drywall.</p>');
        $this->assertStringContainsString('<p>Framing and drywall.</p>', $html);
        $this->assertStringContainsString(ContractContractorFeeClause::SENTENCE, $html);
        $this->assertSame($html, ContractContractorFeeClause::ensureInHtml($html));
    }

    public function test_new_contract_pdf_template_renders_the_clause_when_body_omits_it(): void
    {
        $html = view('admin.client_projects.contract.pdf', [
            'project' => new ClientProject(['name' => 'Basement finish', 'client_name' => 'Test Client']),
            'contract' => new ClientProjectContract([
                'html_content' => '<p>Basement renovation scope.</p>',
                'include_contractor_fee_change_order_clause' => true,
            ]),
        ])->render();

        $this->assertStringContainsString('<p class="contractor-fee-clause">', $html);
        $this->assertSame(1, substr_count($html, ContractContractorFeeClause::SENTENCE));
        $this->assertStringContainsString('written change orders', $html);
        $this->assertStringContainsString('Basement renovation scope.', $html);
    }

    public function test_old_agreed_contract_pdf_does_not_gain_the_clause(): void
    {
        $html = view('admin.client_projects.contract.pdf', [
            'project' => new ClientProject(['name' => 'Old basement', 'client_name' => 'Legacy Client']),
            'contract' => new ClientProjectContract([
                'html_content' => '<p>Scope of work for the renovation.</p>',
                'include_contractor_fee_change_order_clause' => false,
                'sent_at' => now(),
            ]),
        ])->render();

        $this->assertStringNotContainsString(ContractContractorFeeClause::SENTENCE, $html);
        $this->assertStringNotContainsString('<p class="contractor-fee-clause">', $html);
        $this->assertStringContainsString('Scope of work for the renovation.', $html);
    }

    public function test_overlay_is_skipped_when_generated_body_already_has_the_clause(): void
    {
        $body = '<p>Framing.</p><p>'.e(ContractContractorFeeClause::SENTENCE).'</p>';
        $this->assertFalse(ContractContractorFeeClause::shouldRenderOverlay(new ClientProjectContract([
            'html_content' => $body,
            'include_contractor_fee_change_order_clause' => true,
        ])));
    }
}

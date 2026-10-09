<?php

namespace Tests\Feature\Admin;

use App\Models\BasementQuotation;
use App\Models\ClientProject;
use App\Models\ClientProjectQuotation;
use App\Models\FenceQuotation;
use App\Models\LowDeckQuotation;
use App\Models\StandardQuotation;
use App\Models\TileQuotation;
use App\Services\QuoteAcceptanceService;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\URL;
use Tests\Feature\QuoteAcceptance\MakesAcceptableQuotations;
use Tests\IntegrationTestCase;
use Tests\Support\ActsAsAdmin;

class QuotationDraftClientFacingGuardTest extends IntegrationTestCase
{
    use ActsAsAdmin;
    use MakesAcceptableQuotations;

    public function test_draft_save_on_published_linked_quotes_does_not_update_client_quotation(): void
    {
        $this->actingAsAdmin();

        $this->assertDraftSaveLeavesClientQuotationUnchanged(
            'basement',
            $this->publishLinkedBasement(...),
            fn (BasementQuotation $quote, array $publish) => array_merge($publish, [
                'save_intent' => 'draft',
                'area_sqft' => 400,
            ]),
            fn (BasementQuotation $quote) => (float) $quote->final_quote_total
        );

        $this->assertDraftSaveLeavesClientQuotationUnchanged(
            'fence',
            $this->publishLinkedFence(...),
            fn (FenceQuotation $quote, array $publish) => array_merge($publish, [
                'save_intent' => 'draft',
                'sides' => [['length_ft' => 48]],
            ]),
            fn (FenceQuotation $quote) => (float) $quote->grand_total
        );

        $this->assertDraftSaveLeavesClientQuotationUnchanged(
            'low-deck',
            $this->publishLinkedLowDeck(...),
            fn (LowDeckQuotation $quote, array $publish) => array_merge($publish, [
                'save_intent' => 'draft',
                'width_ft' => 8,
                'length_ft' => 10,
            ]),
            fn (LowDeckQuotation $quote) => (float) $quote->grand_total
        );

        $this->assertDraftSaveLeavesClientQuotationUnchanged(
            'standard',
            $this->publishLinkedStandard(...),
            fn (StandardQuotation $quote, array $publish) => array_merge($publish, [
                'save_intent' => 'draft',
                'works' => [['title' => 'Labour', 'labour_use_fixed' => '1', 'labour_fixed_amount' => '500']],
            ]),
            fn (StandardQuotation $quote) => (float) $quote->grand_total
        );

        $this->assertDraftSaveLeavesClientQuotationUnchanged(
            'tile',
            $this->publishLinkedTile(...),
            fn (TileQuotation $quote, array $publish) => array_merge($publish, [
                'save_intent' => 'draft',
                'is_draft' => '1',
                'total_area_sqft' => 20,
                'labor_rate' => 40,
            ]),
            fn (TileQuotation $quote) => (float) $quote->final_quote_total
        );
    }

    /**
     * @return array<string, array{0: string, 1: string}>
     */
    public function linkGuardTypes(): array
    {
        return [
            'fence' => ['fence', 'fence'],
            'low_deck' => ['low-deck', 'low deck'],
            'standard' => ['standard', 'standard'],
        ];
    }

    /**
     * @dataProvider linkGuardTypes
     */
    public function test_link_and_create_project_refuse_drafts(string $type, string $label): void
    {
        $this->actingAsAdmin();

        $quote = $this->makeUnlinkedDraft($type);
        $project = ClientProject::factory()->create([
            'status' => ClientProject::STATUS_VISIT_SCHEDULED,
        ]);

        $this->from(route('admin.quotations.'.$type.'.edit', $quote))
            ->post(route('admin.quotations.'.$type.'.link', $quote), [
                'client_project_id' => $project->id,
            ])
            ->assertSessionHas('error', 'Publish the quotation (click Save quotation, not Save as draft) before linking.');

        $quote->refresh();
        $this->assertTrue((bool) $quote->is_draft);
        $this->assertFalse($quote->isLinked());
        $this->assertSame(0, ClientProjectQuotation::query()->where('source_id', $quote->id)->count());

        $projectsBefore = ClientProject::query()->count();
        $this->from(route('admin.quotations.'.$type.'.edit', $quote))
            ->post(route('admin.quotations.'.$type.'.create-project', $quote), [
                'fp_client_name' => 'Draft Project Client',
                'fp_project_name' => 'Should not create '.$label,
            ])
            ->assertSessionHas('error', 'Publish the quotation before creating a project.');

        $quote->refresh();
        $this->assertTrue((bool) $quote->is_draft);
        $this->assertFalse($quote->isLinked());
        $this->assertSame($projectsBefore, ClientProject::query()->count());
    }

    public function test_email_sms_pdf_and_accept_are_blocked_when_source_is_draft(): void
    {
        Mail::fake();
        $this->actingAsAdmin();

        [$project, $quotation, $standard] = $this->makeLinkedQuotation();
        $standard->is_draft = true;
        $standard->save();
        $quotation->refresh();
        $this->assertTrue($quotation->isSourceDraft());

        $this->from(route('admin.client-projects.show', $project))
            ->post(route('admin.client-projects.quotation.send', [$project, $quotation]))
            ->assertRedirect()
            ->assertSessionHas('error', ClientProjectQuotation::SOURCE_DRAFT_SHARE_MESSAGE);
        Mail::assertNothingSent();
        $this->assertNull($quotation->fresh()->sent_at);

        $this->from(route('admin.client-projects.show', $project))
            ->post(route('admin.client-projects.quotation.sms', [$project, $quotation]))
            ->assertRedirect()
            ->assertSessionHas('error');
        $this->assertStringContainsString(
            ClientProjectQuotation::SOURCE_DRAFT_SHARE_MESSAGE,
            (string) session('error')
        );

        $this->from(route('admin.client-projects.show', $project))
            ->get(route('admin.client-projects.quotation.pdf', [$project, $quotation]))
            ->assertRedirect()
            ->assertSessionHas('error', ClientProjectQuotation::SOURCE_DRAFT_SHARE_MESSAGE);

        $this->get(URL::signedRoute('public.client-project.quotation-pdf', [
            'client_project' => $project,
            'quotation' => $quotation,
        ]))->assertNotFound();

        $acceptUrl = $quotation->signedAcceptUrl();
        $this->get($acceptUrl)
            ->assertOk()
            ->assertViewHas('status', QuoteAcceptanceService::STATUS_INVALID_STATE)
            ->assertSee('This quote cannot be accepted', false);
        $this->post($acceptUrl)
            ->assertOk()
            ->assertViewHas('status', QuoteAcceptanceService::STATUS_INVALID_STATE)
            ->assertSee('This quote cannot be accepted', false);
        $this->assertFalse($quotation->fresh()->isAccepted());

        config(['quote_accept.api_token' => 'test-quote-accept-token']);
        $this->postJson('/api/quotations/accept', [
            'project_number' => $project->referenceCode(),
            'quote_number' => $quotation->displayQuoteNumber(),
        ], ['Authorization' => 'Bearer test-quote-accept-token'])
            ->assertStatus(409)
            ->assertExactJson(['status' => QuoteAcceptanceService::STATUS_INVALID_STATE]);
        $this->assertFalse($quotation->fresh()->isAccepted());
    }

    public function test_tile_edit_keeps_draft_flag_and_update_message_says_saved_as_draft(): void
    {
        $this->actingAsAdmin();

        $payload = $this->tilePayload('Tile Draft Re-show', true);
        $this->post(route('admin.quotations.tile.store'), $payload)->assertRedirect();

        $quote = TileQuotation::query()->where('client_name', 'Tile Draft Re-show')->first();
        $this->assertNotNull($quote);
        $this->assertTrue((bool) $quote->is_draft);

        $html = $this->get(route('admin.quotations.tile.edit', $quote))
            ->assertOk()
            ->getContent();
        $this->assertMatchesRegularExpression(
            '/id="tl-is-draft"[^>]*value="1"|value="1"[^>]*id="tl-is-draft"/',
            $html
        );

        $this->from(route('admin.quotations.tile.edit', $quote))
            ->put(route('admin.quotations.tile.update', $quote), $payload)
            ->assertRedirect(route('admin.quotations.tile.edit', $quote))
            ->assertSessionHas('success', 'Tile quotation saved as a draft.');

        $this->assertTrue((bool) $quote->fresh()->is_draft);
    }

    /**
     * @param  callable(): array{0: object, 1: ClientProjectQuotation, 2: array<string, mixed>, 3: string}  $publish
     * @param  callable(object, array<string, mixed>): array<string, mixed>  $draftPayload
     * @param  callable(object): float  $total
     */
    private function assertDraftSaveLeavesClientQuotationUnchanged(
        string $type,
        callable $publish,
        callable $draftPayload,
        callable $total
    ): void {
        [$quote, $cpq, $payload, $updateRoute] = $publish();
        $this->assertFalse((bool) $quote->is_draft, "{$type} should start published");
        $this->assertTrue($quote->isLinked(), "{$type} should start linked");

        $publishedTotal = $total($quote);
        $this->assertGreaterThan(0, $publishedTotal, "{$type} published total");
        $itemsBefore = $cpq->items;
        $cpqTotalBefore = $cpq->grandTotal();
        $snapshotBefore = $cpq->source_snapshot;

        $this->from(route('admin.quotations.'.$type.'.edit', $quote))
            ->put($updateRoute, $draftPayload($quote, $payload))
            ->assertSessionDoesntHaveErrors()
            ->assertRedirect();

        $quote->refresh();
        $cpq->refresh();
        $this->assertTrue((bool) $quote->is_draft, "{$type} must stay a draft after Save as draft");
        $this->assertNotEquals($publishedTotal, $total($quote), "{$type} draft must change the calculator total");
        $this->assertSame($itemsBefore, $cpq->items, "{$type} draft must not rewrite client quotation items");
        $this->assertEqualsWithDelta($cpqTotalBefore, $cpq->grandTotal(), 0.001, "{$type} draft must not change the project PDF total");
        $this->assertSame($snapshotBefore, $cpq->source_snapshot, "{$type} draft must not replace the client quotation snapshot");
        $this->assertEqualsWithDelta($cpqTotalBefore, $cpq->pdfGrandTotal(), 0.001, "{$type} draft must not change the public signed PDF total");
    }

    /**
     * @return array{0: BasementQuotation, 1: ClientProjectQuotation, 2: array<string, mixed>, 3: string}
     */
    private function publishLinkedBasement(): array
    {
        $project = $this->openProject();
        $payload = [
            'save_intent' => 'publish',
            'client_name' => $project->client_name,
            'area_sqft' => 850,
            'ceiling_height_ft' => 8,
            'room_count' => 3,
            'payment_installments' => 3,
            'include_commute' => 0,
            'include_debris_removal' => 0,
            'owner_hourly_rate' => 80,
            'helper_hourly_rate' => 40,
            'ask_reply_to_accept' => '0',
        ];
        $this->post(route('admin.quotations.basement.store', ['project' => $project->id]), $payload)->assertRedirect();
        $quote = BasementQuotation::query()->where('client_name', $project->client_name)->latest('id')->firstOrFail();
        $cpq = $this->clientQuotationFor($quote, ClientProjectQuotation::SOURCE_BASEMENT);

        return [$quote, $cpq, $payload, route('admin.quotations.basement.update', $quote)];
    }

    /**
     * @return array{0: FenceQuotation, 1: ClientProjectQuotation, 2: array<string, mixed>, 3: string}
     */
    private function publishLinkedFence(): array
    {
        $project = $this->openProject();
        $payload = [
            'save_intent' => 'publish',
            'client_name' => $project->client_name,
            'sides' => [['length_ft' => 24]],
            'fence_height_ft' => 6,
            'board_style' => 'standard',
            'gate_count' => 0,
            'labour_rate_per_ft' => 55,
            'labour_rate_8ft_per_ft' => 60,
            'existing_post_labour_credit' => 30,
            'include_labour_hst' => true,
            'payment_installments' => 1,
            'include_commute' => '0',
            'include_debris_removal' => '0',
            'ask_reply_to_accept' => '0',
        ];
        $this->post(route('admin.quotations.fence.store', ['project' => $project->id]), $payload)->assertRedirect();
        $quote = FenceQuotation::query()->where('client_name', $project->client_name)->latest('id')->firstOrFail();
        $cpq = $this->clientQuotationFor($quote, ClientProjectQuotation::SOURCE_FENCE);

        return [$quote, $cpq, $payload, route('admin.quotations.fence.update', $quote)];
    }

    /**
     * @return array{0: LowDeckQuotation, 1: ClientProjectQuotation, 2: array<string, mixed>, 3: string}
     */
    private function publishLinkedLowDeck(): array
    {
        $project = $this->openProject();
        $payload = [
            'save_intent' => 'publish',
            'client_name' => $project->client_name,
            'width_ft' => 12,
            'length_ft' => 16,
            'labour_rate_per_sqft' => 10,
            'payment_installments' => 3,
            'include_commute' => 0,
            'include_debris_removal' => 0,
            'ask_reply_to_accept' => '0',
        ];
        $this->post(route('admin.quotations.low-deck.store', ['project' => $project->id]), $payload)->assertRedirect();
        $quote = LowDeckQuotation::query()->where('client_name', $project->client_name)->latest('id')->firstOrFail();
        $cpq = $this->clientQuotationFor($quote, ClientProjectQuotation::SOURCE_LOW_DECK);

        return [$quote, $cpq, $payload, route('admin.quotations.low-deck.update', $quote)];
    }

    /**
     * @return array{0: StandardQuotation, 1: ClientProjectQuotation, 2: array<string, mixed>, 3: string}
     */
    private function publishLinkedStandard(): array
    {
        $project = $this->openProject();
        $payload = [
            'save_intent' => 'publish',
            'client_name' => $project->client_name,
            'display_title' => 'Kitchen work',
            'document_type' => ClientProjectQuotation::TYPE_QUOTATION,
            'pdf_detail_mode' => StandardQuotation::PDF_DETAIL_SUMMARY,
            'includes_labour' => '1',
            'works' => [['title' => 'Labour', 'labour_use_fixed' => '1', 'labour_fixed_amount' => '100']],
            'payment_installments' => 3,
            'include_commute' => 0,
            'include_debris_removal' => 0,
            'ask_reply_to_accept' => '0',
        ];
        $this->post(route('admin.quotations.standard.store', ['project' => $project->id]), $payload)->assertRedirect();
        $quote = StandardQuotation::query()->where('client_name', $project->client_name)->latest('id')->firstOrFail();
        $cpq = $this->clientQuotationFor($quote, ClientProjectQuotation::SOURCE_STANDARD);

        return [$quote, $cpq, $payload, route('admin.quotations.standard.update', $quote)];
    }

    /**
     * @return array{0: TileQuotation, 1: ClientProjectQuotation, 2: array<string, mixed>, 3: string}
     */
    private function publishLinkedTile(): array
    {
        $project = $this->openProject();
        $payload = $this->tilePayload($project->client_name, false);
        $payload['client_project_id'] = $project->id;
        $this->post(route('admin.quotations.tile.store'), $payload)->assertRedirect();
        $quote = TileQuotation::query()->where('client_name', $project->client_name)->latest('id')->firstOrFail();
        $cpq = $this->clientQuotationFor($quote, ClientProjectQuotation::SOURCE_TILE);

        return [$quote, $cpq, $payload, route('admin.quotations.tile.update', $quote)];
    }

    /**
     * @return array<string, mixed>
     */
    private function tilePayload(string $clientName, bool $draft): array
    {
        return [
            'save_intent' => $draft ? 'draft' : 'publish',
            'is_draft' => $draft ? '1' : '0',
            'client_name' => $clientName,
            'total_area_sqft' => 80,
            'waste_margin_percent' => 10,
            'trowel_size' => '1/4x1/4',
            'joint_width_decimal' => 0.125,
            'labor_rate' => 12,
            'payment_installments' => $draft ? null : 3,
            'include_commute' => 0,
            'include_debris_removal' => $draft ? null : 0,
            'ask_reply_to_accept' => $draft ? null : '0',
        ];
    }

    private function makeUnlinkedDraft(string $type): FenceQuotation|LowDeckQuotation|StandardQuotation
    {
        return match ($type) {
            'fence' => FenceQuotation::query()->create([
                'quote_number' => 'FQ-DRAFT-LINK-1',
                'client_name' => 'Fence Draft Link',
                'is_draft' => true,
                'sides' => [['length_ft' => 20]],
                'calculation' => [],
                'grand_total' => 1500,
                'payment_installments' => null,
                'include_debris_removal' => null,
                'ask_reply_to_accept' => null,
            ]),
            'low-deck' => LowDeckQuotation::query()->create([
                'quote_number' => 'LD-DRAFT-LINK-1',
                'client_name' => 'Deck Draft Link',
                'is_draft' => true,
                'width_ft' => 12,
                'length_ft' => 16,
                'calculation' => [],
                'grand_total' => 1800,
                'payment_installments' => null,
                'include_debris_removal' => null,
                'ask_reply_to_accept' => null,
            ]),
            default => StandardQuotation::query()->create([
                'quote_number' => 'SQ-DRAFT-LINK-1',
                'client_name' => 'Standard Draft Link',
                'display_title' => 'Kitchen work',
                'is_draft' => true,
                'materials' => [],
                'grand_total' => 1130,
                'payment_installments' => null,
                'include_debris_removal' => null,
                'ask_reply_to_accept' => null,
            ]),
        };
    }

    private function openProject(): ClientProject
    {
        return ClientProject::factory()->create([
            'status' => ClientProject::STATUS_VISIT_SCHEDULED,
            'billing_type' => ClientProject::BILLING_FIXED,
        ]);
    }

    private function clientQuotationFor(object $quote, string $sourceType): ClientProjectQuotation
    {
        $cpq = ClientProjectQuotation::query()
            ->where('source_type', $sourceType)
            ->where('source_id', $quote->id)
            ->first();
        $this->assertNotNull($cpq, $sourceType.' client quotation should exist after publish+link');

        return $cpq;
    }
}

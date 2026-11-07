<?php

namespace Tests\Feature\QuoteAcceptance;

use App\Mail\ClientProjectQuotationClient;
use App\Models\ClientProject;
use App\Models\ClientProjectPhoto;
use App\Support\PrivateAppFile;
use App\Support\QuotationReplyToAccept;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Tests\IntegrationTestCase;
use Tests\Support\ActsAsAdmin;

class QuotationEmailAndReplyChoiceTest extends IntegrationTestCase
{
    use ActsAsAdmin;
    use MakesAcceptableQuotations;

    public function test_quotation_email_subject_contains_project_and_quote_numbers(): void
    {
        Mail::fake();
        $this->actingAsAdmin();

        [$project, $quotation] = $this->makeLinkedQuotation([
            'quote_number' => 'SQ-2026-0001',
        ]);

        $this->post(route('admin.client-projects.quotation.send', [$project, $quotation]))
            ->assertRedirect();

        Mail::assertSent(ClientProjectQuotationClient::class, function (ClientProjectQuotationClient $mail) use ($project, $quotation) {
            $expected = ClientProjectQuotationClient::subjectFor($project, $quotation);
            $mail->build();
            $this->assertSame($expected, $mail->subject);
            $this->assertStringContainsString('PRJ', $expected);
            $this->assertStringContainsString('SQ-2026-0001', $expected);
            $html = view($mail->view, $mail->viewData)->render();
            $this->assertStringContainsString('Accept this quote', $html);
            $this->assertStringContainsString('I accept', $html);
            $this->assertStringNotContainsString('also attached to this email', $html);

            return true;
        });
    }

    public function test_quotation_email_attaches_only_the_photos_selected_for_the_pdf(): void
    {
        Storage::fake('local');

        [$project, $quotation] = $this->makeLinkedQuotation();

        $selectedPath = 'client-project-photos/project-'.$project->id.'/selected.webp';
        $otherPath = 'client-project-photos/project-'.$project->id.'/other.webp';
        $missingPath = 'client-project-photos/project-'.$project->id.'/missing.webp';
        PrivateAppFile::put($selectedPath, 'webp-selected');
        PrivateAppFile::put($otherPath, 'webp-other');

        $selected = ClientProjectPhoto::query()->create([
            'client_project_id' => $project->id,
            'path' => $selectedPath,
            'media_type' => ClientProjectPhoto::TYPE_IMAGE,
            'original_filename' => 'Project-image01.webp',
            'sort_order' => 1,
        ]);
        $duplicateName = ClientProjectPhoto::query()->create([
            'client_project_id' => $project->id,
            'path' => $otherPath,
            'media_type' => ClientProjectPhoto::TYPE_IMAGE,
            'original_filename' => 'Project-image01.webp',
            'sort_order' => 2,
        ]);
        $missing = ClientProjectPhoto::query()->create([
            'client_project_id' => $project->id,
            'path' => $missingPath,
            'media_type' => ClientProjectPhoto::TYPE_IMAGE,
            'original_filename' => 'Gone.webp',
            'sort_order' => 3,
        ]);
        ClientProjectPhoto::query()->create([
            'client_project_id' => $project->id,
            'path' => $otherPath,
            'media_type' => ClientProjectPhoto::TYPE_IMAGE,
            'original_filename' => 'Not-selected.webp',
            'sort_order' => 4,
        ]);

        $quotation->pdf_photo_ids = [$selected->id, $duplicateName->id, $missing->id];
        $quotation->save();

        $mail = new ClientProjectQuotationClient($project, $quotation->fresh(), 'pdf-bytes', 'quotation.pdf');
        $mail->build();

        $names = [];
        $mimes = [];
        foreach ($mail->attachments as $attachment) {
            $names[] = $attachment['options']['as'] ?? '';
            $mimes[] = $attachment['options']['mime'] ?? '';
        }

        $this->assertSame([
            'Project-image01.webp',
            'Project-image01-'.$duplicateName->id.'.webp',
        ], $names);
        $this->assertSame(['image/webp', 'image/webp'], $mimes);
        $this->assertNotContains('Not-selected.webp', $names);
        $this->assertNotContains('Gone.webp', $names);
        $this->assertCount(1, $mail->rawAttachments);
        $this->assertSame('application/pdf', $mail->rawAttachments[0]['options']['mime']);

        $html = view($mail->view, $mail->viewData)->render();
        $this->assertStringContainsString('also attached to this email', $html);
    }

    public function test_email_is_blocked_when_reply_to_accept_choice_is_unset(): void
    {
        Mail::fake();
        $this->actingAsAdmin();

        [$project, $quotation] = $this->makeLinkedQuotation([
            'ask_reply_to_accept' => null,
        ]);

        $this->from(route('admin.client-projects.show', $project))
            ->post(route('admin.client-projects.quotation.send', [$project, $quotation]))
            ->assertRedirect()
            ->assertSessionHas('error');

        Mail::assertNothingSent();
        $this->assertNull($quotation->fresh()->sent_at);
    }

    public function test_save_is_blocked_when_reply_to_accept_choice_is_unset(): void
    {
        $this->actingAsAdmin();

        $response = $this->from(route('admin.quotations.basement.create'))
            ->post(route('admin.quotations.basement.store'), [
                'save_intent' => 'publish',
                'client_name' => 'Reply Choice Client',
                'area_sqft' => 850,
                'ceiling_height_ft' => 8,
                'room_count' => 3,
                'payment_installments' => 3,
                'include_demolition' => 0,
                'include_commute' => 0,
                'include_debris_removal' => 0,
                'owner_hourly_rate' => 80,
                'helper_hourly_rate' => 40,
            ]);

        $response->assertRedirect(route('admin.quotations.basement.create'));
        $response->assertSessionHasErrors(QuotationReplyToAccept::FIELD);
        $this->assertSame(0, \App\Models\BasementQuotation::query()->count());
    }

    public function test_save_shows_reply_choice_error_even_when_other_fields_fail(): void
    {
        $this->actingAsAdmin();

        $this->from(route('admin.quotations.standard.create'))
            ->post(route('admin.quotations.standard.store'), [
                'save_intent' => 'publish',
                'client_name' => 'Reply Choice Client',
                'display_title' => 'Kitchen work',
            ])
            ->assertRedirect(route('admin.quotations.standard.create'))
            ->assertSessionHasErrors(QuotationReplyToAccept::FIELD);
    }

    public function test_editor_shows_required_yes_no_choice(): void
    {
        $this->actingAsAdmin();

        $this->get(route('admin.quotations.standard.create'))
            ->assertOk()
            ->assertSee('Ask client to reply', false)
            ->assertSee('name="ask_reply_to_accept"', false)
            ->assertSee('value="1"', false)
            ->assertSee('value="0"', false);
    }

    public function test_existing_quotation_with_unset_choice_blocks_save_only(): void
    {
        $this->actingAsAdmin();

        $this->post(route('admin.quotations.basement.store'), [
            'save_intent' => 'publish',
            'client_name' => 'Existing Reply Client',
            'area_sqft' => 850,
            'ceiling_height_ft' => 8,
            'room_count' => 3,
            'payment_installments' => 3,
            'include_demolition' => 0,
            'include_commute' => 0,
            'include_debris_removal' => 0,
            'owner_hourly_rate' => 80,
            'helper_hourly_rate' => 40,
            'ask_reply_to_accept' => '0',
        ])->assertRedirect();

        $quote = \App\Models\BasementQuotation::query()
            ->where('client_name', 'Existing Reply Client')
            ->first();
        $this->assertNotNull($quote);

        $quote->ask_reply_to_accept = null;
        $quote->saveQuietly();

        $before = $quote->fresh();
        $this->assertNull($before->ask_reply_to_accept);

        $html = $this->get(route('admin.quotations.basement.edit', $quote))
            ->assertOk()
            ->assertSee('Ask client to reply', false)
            ->getContent();

        preg_match_all('/<input[^>]*name="ask_reply_to_accept"[^>]*>/i', $html, $radioTags);
        $this->assertCount(2, $radioTags[0]);
        foreach ($radioTags[0] as $tag) {
            $this->assertStringNotContainsString('checked', strtolower($tag));
        }

        [$project, , $standard] = $this->makeLinkedQuotation(['ask_reply_to_accept' => null]);
        $standardHtml = $this->get(route('admin.quotations.standard.edit', $standard))
            ->assertOk()
            ->assertSee('Ask client to reply', false)
            ->getContent();
        preg_match_all('/<input[^>]*name="ask_reply_to_accept"[^>]*>/i', $standardHtml, $standardRadios);
        $this->assertCount(2, $standardRadios[0]);
        foreach ($standardRadios[0] as $tag) {
            $this->assertStringNotContainsString('checked', strtolower($tag));
        }
        $this->assertSame(ClientProject::STATUS_QUOTE_SENT, $project->fresh()->status);

        $payload = [
            'save_intent' => 'publish',
            'client_name' => 'Existing Reply Client',
            'area_sqft' => 850,
            'ceiling_height_ft' => 8,
            'room_count' => 3,
            'payment_installments' => 3,
            'include_demolition' => 0,
            'include_commute' => 0,
            'include_debris_removal' => 0,
            'owner_hourly_rate' => 80,
            'helper_hourly_rate' => 40,
        ];

        $this->from(route('admin.quotations.basement.edit', $quote))
            ->put(route('admin.quotations.basement.update', $quote), $payload)
            ->assertRedirect(route('admin.quotations.basement.edit', $quote))
            ->assertSessionHasErrors(QuotationReplyToAccept::FIELD);

        $blocked = $quote->fresh();
        $this->assertNull($blocked->ask_reply_to_accept);
        $this->assertSame((int) $before->version, (int) $blocked->version);
        $this->assertSame((string) $before->final_quote_total, (string) $blocked->final_quote_total);
        $this->assertSame($before->client_name, $blocked->client_name);

        $payload['ask_reply_to_accept'] = '1';
        $this->put(route('admin.quotations.basement.update', $quote), $payload)
            ->assertSessionDoesntHaveErrors()
            ->assertRedirect();

        $saved = $quote->fresh();
        $this->assertSame(1, (int) $saved->ask_reply_to_accept);
        $this->assertSame((int) $before->version + 1, (int) $saved->version);
        $this->assertSame($before->client_name, $saved->client_name);
        $this->assertFalse((bool) $saved->include_commute);
        $this->assertSame(ClientProject::STATUS_QUOTE_SENT, $project->fresh()->status);
    }
}

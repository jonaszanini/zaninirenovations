<?php

namespace Tests\Feature\Admin;

use App\Models\Client;
use App\Models\ClientProject;
use Illuminate\Support\Facades\Mail;
use Tests\IntegrationTestCase;
use Tests\Support\ActsAsAdmin;

class ClientProjectOverviewInlineEditTest extends IntegrationTestCase
{
    use ActsAsAdmin;

    public function test_overview_renders_compact_grid_and_inline_visit_schedule_controls(): void
    {
        $this->actingAsAdmin();

        $project = ClientProject::factory()->create([
            'name' => 'Kitchen renovation',
            'client_name' => 'Jane Chen',
            'visit_required' => true,
            'visit_date' => '2026-11-12',
            'visit_time' => '10:00:00',
            'start_date' => '2026-11-20',
            'end_date' => '2026-11-22',
        ]);

        $html = $this->get(route('admin.client-projects.show', $project))
            ->assertOk()
            ->assertSee('class="cp-overview-grid"', false)
            ->assertSee('data-cp-overview-edit="visit"', false)
            ->assertSee('data-cp-overview-edit="schedule"', false)
            ->assertSee('name="return_tab"', false)
            ->assertSee('id="cp-overview-visit-form"', false)
            ->assertSee('id="cp-overview-schedule-form"', false)
            ->assertSee('aria-label="Edit visit"', false)
            ->assertSee('aria-label="Edit schedule"', false)
            ->assertDontSee('Required — date not set yet', false)
            ->getContent();

        $this->assertStringContainsString('Save visit', $html);
        $this->assertStringContainsString('Save schedule', $html);
        $this->assertStringContainsString('On site visit before quoting', $html);
        $this->assertStringContainsString('Project start and end as shown on the Project Calendar', $html);

        $pendingVisit = ClientProject::factory()->create([
            'visit_required' => true,
            'visit_date' => null,
            'start_date' => null,
            'end_date' => null,
        ]);
        $pendingHtml = $this->get(route('admin.client-projects.show', $pendingVisit))
            ->assertOk()
            ->assertSee('Required, date not set yet', false)
            ->assertDontSee('Required — date not set yet', false)
            ->getContent();
        $this->assertMatchesRegularExpression(
            '/data-cp-overview-schedule-value>\s*Not set/',
            $pendingHtml
        );

        $css = (string) file_get_contents(public_path('css/admin-client-projects.css'));
        $this->assertMatchesRegularExpression(
            '/\.cp-overview-grid\s*\{[^}]*grid-template-columns:\s*1fr/s',
            $css
        );
        $this->assertMatchesRegularExpression(
            '/@media\s*\(min-width:\s*1024px\)\s*\{[^}]*\.cp-overview-grid\s*\{[^}]*grid-template-columns:\s*minmax\(0,\s*1fr\)\s+minmax\(0,\s*1fr\)/s',
            $css
        );
        $this->assertMatchesRegularExpression(
            '/\.cp-overview-inline-fields\s*\{[^}]*grid-template-columns:\s*minmax\(0,\s*1fr\)/s',
            $css
        );
        $this->assertDoesNotMatchRegularExpression(
            '/\.cp-overview-inline-fields\s*\{[^}]*grid-template-columns:\s*minmax\(0,\s*1fr\)\s+minmax\(0,\s*1fr\)/s',
            $css
        );
        $this->assertMatchesRegularExpression(
            '/\.cp-overview-inline-form\s*\{[^}]*grid-column:\s*1\s*\/\s*-1/s',
            $css
        );
    }

    public function test_overview_inline_visit_save_updates_date_and_time(): void
    {
        Mail::fake();
        $this->actingAsAdmin();

        $project = ClientProject::factory()->create([
            'name' => 'Deck build',
            'client_name' => 'Alex Rivera',
            'visit_required' => true,
            'visit_date' => '2026-11-10',
            'visit_time' => '09:00:00',
            'start_date' => '2026-12-01',
            'end_date' => '2026-12-05',
            'status' => ClientProject::STATUS_VISIT_SCHEDULED,
        ]);

        $response = $this->put(route('admin.client-projects.update', $project), [
            'name' => 'Deck build',
            'client_name' => 'Alex Rivera',
            'return_tab' => 'overview',
            'visit_required' => '1',
            'visit_date' => '2026-11-18',
            'visit_time' => '14:30',
        ]);

        $response->assertRedirect(route('admin.client-projects.show', $project).'#overview');
        $response->assertSessionHas('success');
        $response->assertSessionDoesntHaveErrors();

        $project->refresh();
        $this->assertSame('2026-11-18', $project->visit_date->toDateString());
        $this->assertStringStartsWith('14:30', (string) $project->visit_time);
        $this->assertTrue((bool) $project->visit_required);
        $this->assertSame('2026-12-01', $project->start_date->toDateString());
        $this->assertSame('2026-12-05', $project->end_date->toDateString());

        $this->get(route('admin.client-projects.show', $project))
            ->assertOk()
            ->assertSee('data-cp-overview-visit-value', false)
            ->assertSee('18/11/2026', false)
            ->assertSee('2:30 PM EST', false);
    }

    public function test_overview_inline_visit_save_promotes_visit_required_status(): void
    {
        Mail::fake();
        $this->actingAsAdmin();

        $project = ClientProject::factory()->create([
            'name' => 'Bath remodel',
            'client_name' => 'Sam Patel',
            'visit_required' => true,
            'visit_date' => null,
            'visit_time' => null,
            'status' => ClientProject::STATUS_VISIT_REQUIRED,
        ]);

        $this->put(route('admin.client-projects.update', $project), [
            'name' => 'Bath remodel',
            'client_name' => 'Sam Patel',
            'return_tab' => 'overview',
            'visit_required' => '1',
            'visit_date' => '2026-11-19',
            'visit_time' => '11:00',
        ])->assertSessionDoesntHaveErrors();

        $project->refresh();
        $this->assertSame(ClientProject::STATUS_VISIT_SCHEDULED, $project->status);
        $this->assertTrue(
            $project->statusHistory()->where('to_status', ClientProject::STATUS_VISIT_SCHEDULED)->exists()
        );
    }

    public function test_overview_inline_schedule_save_updates_start_and_end(): void
    {
        Mail::fake();
        $this->actingAsAdmin();

        $project = ClientProject::factory()->create([
            'name' => 'Fence install',
            'client_name' => 'Riley Ng',
            'visit_required' => true,
            'visit_date' => '2026-11-08',
            'visit_time' => '09:15:00',
            'start_date' => null,
            'end_date' => null,
        ]);

        $response = $this->put(route('admin.client-projects.update', $project), [
            'name' => 'Fence install',
            'client_name' => 'Riley Ng',
            'return_tab' => 'overview',
            'start_date' => '2026-11-24',
            'end_date' => '2026-11-26',
        ]);

        $response->assertRedirect(route('admin.client-projects.show', $project).'#overview');
        $response->assertSessionHas('success');
        $response->assertSessionDoesntHaveErrors();

        $project->refresh();
        $this->assertSame('2026-11-24', $project->start_date->toDateString());
        $this->assertSame('2026-11-26', $project->end_date->toDateString());
        $this->assertSame('2026-11-08', $project->visit_date->toDateString());
        $this->assertStringStartsWith('09:15', (string) $project->visit_time);

        $this->get(route('admin.client-projects.show', $project))
            ->assertOk()
            ->assertSee('24/11/2026', false)
            ->assertSee('26/11/2026', false);
    }

    public function test_overview_inline_schedule_rejects_end_before_start(): void
    {
        Mail::fake();
        $this->actingAsAdmin();

        $project = ClientProject::factory()->create([
            'name' => 'Patio',
            'client_name' => 'Morgan Lee',
            'start_date' => '2026-11-01',
            'end_date' => '2026-11-03',
        ]);

        $response = $this->from(route('admin.client-projects.show', $project))
            ->put(route('admin.client-projects.update', $project), [
                'name' => 'Patio',
                'client_name' => 'Morgan Lee',
                'return_tab' => 'overview',
                'start_date' => '2026-11-20',
                'end_date' => '2026-11-19',
            ]);

        $response->assertRedirect(route('admin.client-projects.show', $project).'#overview');
        $response->assertSessionHasErrors('end_date');

        $project->refresh();
        $this->assertSame('2026-11-01', $project->start_date->toDateString());
        $this->assertSame('2026-11-03', $project->end_date->toDateString());
    }

    public function test_overview_inline_visit_rejects_invalid_date(): void
    {
        Mail::fake();
        $this->actingAsAdmin();

        $project = ClientProject::factory()->create([
            'name' => 'Siding',
            'client_name' => 'Chris Wu',
            'visit_required' => true,
            'visit_date' => '2026-11-05',
            'visit_time' => '08:00:00',
        ]);

        $response = $this->from(route('admin.client-projects.show', $project))
            ->put(route('admin.client-projects.update', $project), [
                'name' => 'Siding',
                'client_name' => 'Chris Wu',
                'return_tab' => 'overview',
                'visit_required' => '1',
                'visit_date' => 'not-a-date',
                'visit_time' => '10:00',
            ]);

        $response->assertRedirect(route('admin.client-projects.show', $project).'#overview');
        $response->assertSessionHasErrors('visit_date');

        $project->refresh();
        $this->assertSame('2026-11-05', $project->visit_date->toDateString());
        $this->assertStringStartsWith('08:00', (string) $project->visit_time);
    }

    public function test_guest_cannot_save_overview_inline_visit_or_schedule(): void
    {
        $project = ClientProject::factory()->create([
            'visit_required' => true,
            'visit_date' => '2026-11-05',
            'start_date' => '2026-11-20',
            'end_date' => '2026-11-21',
        ]);

        $this->put(route('admin.client-projects.update', $project), [
            'name' => $project->name,
            'client_name' => $project->client_name,
            'return_tab' => 'overview',
            'visit_required' => '1',
            'visit_date' => '2026-11-30',
            'visit_time' => '15:00',
        ])->assertRedirect(route('login'));

        $this->put(route('admin.client-projects.update', $project), [
            'name' => $project->name,
            'client_name' => $project->client_name,
            'return_tab' => 'overview',
            'start_date' => '2026-12-01',
            'end_date' => '2026-12-02',
        ])->assertRedirect(route('login'));

        $project->refresh();
        $this->assertSame('2026-11-05', $project->visit_date->toDateString());
        $this->assertSame('2026-11-20', $project->start_date->toDateString());
        $this->assertSame('2026-11-21', $project->end_date->toDateString());
    }

    public function test_overview_inline_save_does_not_touch_directory_when_contact_differs(): void
    {
        Mail::fake();
        $this->actingAsAdmin();

        $client = Client::create([
            'name' => 'Directory Name',
            'email' => 'directory@example.com',
            'phone' => '4165550100',
            'address' => '1 Directory Street, Toronto, ON',
        ]);

        $project = ClientProject::factory()->create([
            'name' => 'Kitchen renovation',
            'client_id' => $client->id,
            'client_name' => 'Project Contact',
            'client_email' => 'project-contact@example.com',
            'client_phone' => '6475550199',
            'client_address' => '99 Project Avenue, Vaughan, ON',
            'visit_required' => true,
            'visit_date' => '2026-11-10',
            'visit_time' => '09:00:00',
            'start_date' => '2026-12-01',
            'end_date' => '2026-12-05',
        ]);

        $directoryBefore = $this->directorySnapshot($client);
        $projectContactBefore = $this->projectContactSnapshot($project);
        $clientsBefore = Client::query()->count();

        $this->put(route('admin.client-projects.update', $project), [
            'name' => 'Kitchen renovation',
            'client_name' => 'Project Contact',
            'return_tab' => 'overview',
            'visit_required' => '1',
            'visit_date' => '2026-11-18',
            'visit_time' => '14:30',
        ])->assertSessionDoesntHaveErrors()
            ->assertRedirect(route('admin.client-projects.show', $project).'#overview');

        $this->assertDirectoryUntouched($client, $project, $directoryBefore, $projectContactBefore, $clientsBefore);
        $project->refresh();
        $this->assertSame('2026-11-18', $project->visit_date->toDateString());
        $this->assertStringStartsWith('14:30', (string) $project->visit_time);

        $this->put(route('admin.client-projects.update', $project), [
            'name' => 'Kitchen renovation',
            'client_name' => 'Project Contact',
            'return_tab' => 'overview',
            'start_date' => '2026-12-08',
            'end_date' => '2026-12-10',
        ])->assertSessionDoesntHaveErrors()
            ->assertRedirect(route('admin.client-projects.show', $project).'#overview');

        $this->assertDirectoryUntouched($client, $project, $directoryBefore, $projectContactBefore, $clientsBefore);
        $project->refresh();
        $this->assertSame('2026-12-08', $project->start_date->toDateString());
        $this->assertSame('2026-12-10', $project->end_date->toDateString());
    }

    public function test_edit_details_posted_client_id_still_syncs_directory(): void
    {
        Mail::fake();
        $this->actingAsAdmin();

        $client = Client::create([
            'name' => 'Directory Name',
            'email' => 'directory@example.com',
            'phone' => '4165550100',
        ]);

        $project = ClientProject::factory()->create([
            'name' => 'Porch rebuild',
            'client_id' => $client->id,
            'client_name' => 'Project Contact',
            'client_email' => 'project-contact@example.com',
            'client_phone' => '6475550199',
        ]);

        $this->put(route('admin.client-projects.update', $project), [
            'name' => 'Porch rebuild',
            'client_id' => $client->id,
            'client_name' => 'Project Contact',
            'client_email' => 'project-contact@example.com',
            'client_phone' => '6475550199',
            'billing_type' => ClientProject::BILLING_FIXED,
            'sync_client_directory' => '1',
        ])->assertSessionDoesntHaveErrors();

        $project->refresh();
        $client->refresh();
        $this->assertSame($client->id, (int) $project->client_id);
        $this->assertSame('Project Contact', $client->name);
        $this->assertSame('project-contact@example.com', $client->email);
        $this->assertSame('6475550199', $client->phone);
        $this->assertSame(1, Client::query()->count());
    }

    public function test_edit_details_empty_client_id_still_creates_directory_row_from_contact(): void
    {
        Mail::fake();
        $this->actingAsAdmin();

        $client = Client::create([
            'name' => 'Directory Name',
            'email' => 'directory@example.com',
            'phone' => '4165550100',
        ]);

        $project = ClientProject::factory()->create([
            'name' => 'Porch rebuild',
            'client_id' => $client->id,
            'client_name' => 'Project Contact',
            'client_email' => 'project-contact@example.com',
            'client_phone' => '6475550199',
        ]);

        $this->put(route('admin.client-projects.update', $project), [
            'name' => 'Porch rebuild',
            'client_id' => '',
            'client_name' => 'Project Contact',
            'client_email' => 'project-contact@example.com',
            'client_phone' => '6475550199',
            'billing_type' => ClientProject::BILLING_FIXED,
            'sync_client_directory' => '1',
        ])->assertSessionDoesntHaveErrors();

        $project->refresh();
        $client->refresh();
        $this->assertNotSame($client->id, (int) $project->client_id);
        $this->assertSame('Directory Name', $client->name);
        $this->assertSame('directory@example.com', $client->email);
        $this->assertSame(2, Client::query()->count());
        $created = Client::query()->whereKey($project->client_id)->first();
        $this->assertNotNull($created);
        $this->assertSame('Project Contact', $created->name);
        $this->assertSame('project-contact@example.com', $created->email);
    }

    public function test_overview_real_render_visit_form_date_inputs_fit_at_1280_and_1024(): void
    {
        $this->actingAsAdmin();

        $project = ClientProject::factory()->create([
            'name' => 'Kitchen renovation',
            'client_name' => 'Jane Chen',
            'client_email' => 'jane.chen@example.com',
            'client_phone' => '4165550142',
            'client_address' => '14 Maple Avenue, Richmond Hill, ON',
            'visit_required' => true,
            'visit_date' => '2026-11-18',
            'visit_time' => '10:00:00',
            'start_date' => '2026-11-20',
            'end_date' => '2026-11-22',
            'notes' => 'Client wants quartz counters and a pot filler.',
        ]);

        $html = $this->get(route('admin.client-projects.show', $project))
            ->assertOk()
            ->assertDontSee('name="client_id"', false)
            ->getContent();

        $this->assertStringContainsString('id="cp-overview-visit-form"', $html);
        $this->assertStringContainsString('id="cp-overview-visit-date"', $html);

        if (! is_dir(base_path('node_modules/puppeteer'))) {
            $this->markTestSkipped('Puppeteer is not installed');
        }

        $outDir = storage_path('app/qa-screenshots');
        if (! is_dir($outDir)) {
            mkdir($outDir, 0777, true);
        }
        $htmlPath = $outDir.'/overview-real-show.html';
        $appUrl = rtrim((string) config('app.url'), '/');
        $html = str_replace($appUrl, '', $html);
        file_put_contents($htmlPath, $html);

        $script = base_path('tests/js/client-projects-overview-real-screenshots.js');
        $reportPath = $outDir.'/overview-real-report.json';
        @unlink($reportPath);

        $cmd = 'OVERVIEW_HTML='.escapeshellarg($htmlPath)
            .' OVERVIEW_OUT='.escapeshellarg($outDir)
            .' OVERVIEW_REPORT='.escapeshellarg($reportPath)
            .' node '.escapeshellarg($script);
        $output = [];
        $code = 0;
        exec($cmd.' 2>&1', $output, $code);
        $this->assertSame(0, $code, "Overview real-render screenshots failed:\n".implode("\n", $output));

        $report = json_decode((string) file_get_contents($reportPath), true);
        $this->assertIsArray($report);
        $this->assertGreaterThanOrEqual(200, (float) ($report['width1280'] ?? 0), '1280 date input is clipped');
        $this->assertGreaterThanOrEqual(200, (float) ($report['width1024'] ?? 0), '1024 date input is clipped');
        $this->assertFileExists($outDir.'/client-project-overview-visit-edit-real-1280.png');
        $this->assertFileExists($outDir.'/client-project-overview-visit-edit-real-390.png');
    }

    public function test_edit_details_and_calendar_tab_behavior_is_unchanged(): void
    {
        Mail::fake();
        $this->actingAsAdmin();

        $project = ClientProject::factory()->create([
            'name' => 'Windows',
            'client_name' => 'Taylor Brooks',
            'start_date' => '2026-11-01',
            'end_date' => '2026-11-02',
        ]);

        $this->get(route('admin.client-projects.edit', $project))
            ->assertOk()
            ->assertSee('id="cp-edit-project-form"', false)
            ->assertSee('id="cp-edit-visit-date"', false)
            ->assertSee('id="cp-start-date"', false)
            ->assertDontSee('cp-overview-grid', false)
            ->assertDontSee('cp-overview-visit-form', false);

        $this->get(route('admin.client-projects.show', $project))
            ->assertOk()
            ->assertSee('id="cp-schedule-dates-form"', false)
            ->assertSee('id="cp-visit-date"', false)
            ->assertSee('id="cp-start"', false);

        $this->from(route('admin.client-projects.edit', $project))
            ->put(route('admin.client-projects.update', $project), [
                'name' => '',
                'client_name' => 'Taylor Brooks',
                'billing_type' => ClientProject::BILLING_FIXED,
            ])
            ->assertRedirect(route('admin.client-projects.edit', $project));

        $calendarFail = $this->from(route('admin.client-projects.show', $project))
            ->put(route('admin.client-projects.update', $project), [
                'name' => 'Windows',
                'client_name' => 'Taylor Brooks',
                'start_date' => '2026-11-20',
                'end_date' => '2026-11-19',
            ]);
        $calendarFail->assertSessionHasErrors('end_date');
        $this->assertStringContainsString('#schedule', (string) $calendarFail->headers->get('Location'));
        $this->assertStringNotContainsString('#overview', (string) $calendarFail->headers->get('Location'));
    }

    public function test_edit_details_keeps_visit_scheduled_when_the_posted_status_is_unchanged(): void
    {
        Mail::fake();
        $this->actingAsAdmin();

        $project = ClientProject::factory()->create([
            'name' => 'Kitchen visit',
            'client_name' => 'Ada Client',
            'visit_required' => true,
            'visit_date' => null,
            'status' => ClientProject::STATUS_VISIT_REQUIRED,
        ]);

        $this->put(route('admin.client-projects.update', $project), [
            'name' => 'Kitchen visit',
            'client_name' => 'Ada Client',
            'visit_required' => '1',
            'visit_date' => '2026-11-19',
            'status' => ClientProject::STATUS_VISIT_REQUIRED,
        ])->assertSessionDoesntHaveErrors();

        $project->refresh();
        $this->assertSame(ClientProject::STATUS_VISIT_SCHEDULED, $project->status);
        $this->assertSame('2026-11-19', $project->visit_date->toDateString());
        $this->assertFalse(
            $project->statusHistory()->where('note', 'Status set from project details.')->exists()
        );
    }

    public function test_edit_details_keeps_visit_required_when_clearing_the_date_without_changing_status(): void
    {
        Mail::fake();
        $this->actingAsAdmin();

        $project = ClientProject::factory()->create([
            'name' => 'Kitchen visit',
            'client_name' => 'Ada Client',
            'visit_required' => true,
            'visit_date' => '2026-11-19',
            'status' => ClientProject::STATUS_VISIT_SCHEDULED,
        ]);

        $this->put(route('admin.client-projects.update', $project), [
            'name' => 'Kitchen visit',
            'client_name' => 'Ada Client',
            'visit_required' => '1',
            'visit_date' => '',
            'status' => ClientProject::STATUS_VISIT_SCHEDULED,
        ])->assertSessionDoesntHaveErrors();

        $project->refresh();
        $this->assertSame(ClientProject::STATUS_VISIT_REQUIRED, $project->status);
        $this->assertNull($project->visit_date);
        $this->assertFalse(
            $project->statusHistory()->where('note', 'Status set from project details.')->exists()
        );
    }

    /**
     * @return array{name: ?string, email: ?string, phone: ?string, address: ?string}
     */
    private function directorySnapshot(Client $client): array
    {
        $client->refresh();

        return [
            'name' => $client->name,
            'email' => $client->email,
            'phone' => $client->phone,
            'address' => $client->address,
        ];
    }

    /**
     * @return array{client_name: mixed, client_email: mixed, client_phone: mixed, client_address: mixed}
     */
    private function projectContactSnapshot(ClientProject $project): array
    {
        $attrs = $project->getAttributes();

        return [
            'client_name' => $attrs['client_name'] ?? null,
            'client_email' => $attrs['client_email'] ?? null,
            'client_phone' => $attrs['client_phone'] ?? null,
            'client_address' => $attrs['client_address'] ?? null,
        ];
    }

    public function test_overview_details_conditional_rows_travel_and_edit_card_move(): void
    {
        $this->actingAsAdmin();

        $emptyProject = ClientProject::factory()->create([
            'name' => 'Bathroom Update',
            'client_name' => 'Jordan Lee',
            'client_email' => 'jordan@example.com',
            'client_email_secondary' => null,
            'client_phone' => null,
            'client_address' => null,
            'created_at' => '2026-10-01 09:00:00',
            'status_changed_at' => '2026-10-02 10:00:00',
        ]);

        $html = $this->get(route('admin.client-projects.show', $emptyProject))
            ->assertOk()
            ->assertDontSee('<dt>Secondary email</dt>', false)
            ->assertDontSee('<dt>Address</dt>', false)
            ->assertDontSee('<dt>Created</dt>', false)
            ->assertDontSee('<dt>Status changed</dt>', false)
            ->assertDontSee('id="cp-travel-cost"', false)
            ->assertSee('data-cp-overview-travel', false)
            ->assertSee('data-cp-overview-more-details', false)
            ->assertSee(route('admin.client-projects.edit', $emptyProject), false)
            ->getContent();

        $this->assertMatchesRegularExpression('/<dt>Phone<\/dt><dd>\s*Not set\s*<\/dd>/', $html);
        $this->assertMatchesRegularExpression('/<dt>\s*<span class="cp-overview-dt">\s*Travel/s', $html);
        $this->assertMatchesRegularExpression('/data-cp-overview-travel-cost>\s*Not set\s*<\/span>/', $html);

        // Full Estimated travel cost card now lives on the Edit page with Refresh button.
        $this->get(route('admin.client-projects.edit', $emptyProject))
            ->assertOk()
            ->assertSee('id="cp-travel-cost"', false)
            ->assertSee('data-cp-travel-refresh', false)
            ->assertSee('Estimated travel cost', false)
            ->assertSee('admin-vehicles.js', false);

        // When optional contact + travel estimate data exist, Details displays them cleanly.
        \Illuminate\Support\Facades\DB::table('company_settings')->updateOrInsert(
            ['id' => 1],
            [
                'home_address_latitude' => 45.4215,
                'home_address_longitude' => -75.6972,
                'gas_price_per_liter' => 1.50,
            ]
        );
        \App\Models\Vehicle::query()->create([
            'name' => 'Work Van',
            'fuel_consumption_l100km' => 14.0,
            'is_active' => true,
        ]);

        $fullProject = ClientProject::factory()->create([
            'name' => 'Basement Framing',
            'client_name' => 'Taylor Reed',
            'client_email' => 'taylor@example.com',
            'client_email_secondary' => 'partner@example.com',
            'client_phone' => '6135550194',
            'client_address' => '142 Maplewood Ave, Ottawa, ON',
            'client_address_latitude' => 45.35,
            'client_address_longitude' => -75.75,
        ]);

        config([
            'google_maps.api_key' => 'test-browser-key',
            'google_maps.server_api_key' => 'test-server-key',
        ]);

        $mapsMock = $this->mock(\App\Services\GoogleMapsService::class);
        $mapsMock->shouldReceive('isConfigured')->andReturn(true);
        $mapsMock->shouldReceive('isDistanceMatrixConfigured')->andReturn(true);
        $mapsMock->shouldReceive('roundTripDrivingDistance')->andReturn([
            'ok' => true,
            'one_way_km' => 38.7,
            'return_km' => 38.7,
            'round_trip_km' => 77.4,
            'outbound_duration_text' => '~27 min',
            'return_duration_text' => '~27 min',
            'duration_text' => '~54 min round trip',
            'message' => null,
        ]);

        $this->getJson(route('admin.client-projects.travel-estimate', $fullProject))
            ->assertOk()
            ->assertJson(['ok' => true, 'fuel_cost_formatted' => 'CA$16.25']);

        $fullHtml = $this->get(route('admin.client-projects.show', $fullProject))
            ->assertOk()
            ->assertSee('<dt>Secondary email</dt><dd>partner@example.com</dd>', false)
            ->assertSee('(613) 555-0194', false)
            ->assertSee('142 Maplewood Ave, Ottawa, ON', false)
            ->assertSee('CA$16.25', false)
            ->assertSee('77.4 km, about 54 min', false)
            ->getContent();
        $this->assertStringContainsString('data-cp-overview-more-details', $fullHtml);
    }

    public function test_overview_quick_actions_empty_sent_and_paid_states(): void
    {
        $this->actingAsAdmin();

        $emptyProject = ClientProject::factory()->create([
            'name' => 'Quick Actions Empty',
            'client_name' => 'Morgan Fox',
            'billing_type' => ClientProject::BILLING_FIXED,
            'status' => ClientProject::STATUS_QUOTE_PENDING,
        ]);

        $this->get(route('admin.client-projects.show', $emptyProject))
            ->assertOk()
            ->assertSee('data-cp-overview-quick-actions', false)
            ->assertSee('Create quotation', false)
            ->assertSee(route('admin.quotations.index', ['project' => $emptyProject->id]), false)
            ->assertSee('data-cp-jump-tab="invoice"', false);

        $activeProject = ClientProject::factory()->create([
            'name' => 'Quick Actions Active',
            'client_name' => 'Alex Rivera',
            'client_email' => 'alex@example.com',
            'billing_type' => ClientProject::BILLING_FIXED,
            'status' => ClientProject::STATUS_IN_PROGRESS,
        ]);

        $quotation = \App\Models\ClientProjectQuotation::query()->create([
            'client_project_id' => $activeProject->id,
            'quotation_number' => 'QUO-2026-7701',
            'source_type' => \App\Models\ClientProjectQuotation::SOURCE_BASEMENT,
            'source_id' => 999,
            'title' => 'Basement Scope',
            'status' => \App\Models\ClientProjectQuotation::STATUS_SENT,
            'items' => [
                ['description' => 'Framing & drywall', 'amount' => 1000.00, 'apply_hst' => true],
            ],
            'subtotal' => 1000.00,
            'hst' => 130.00,
            'total' => 1130.00,
        ]);

        \App\Models\ClientProjectExpense::query()->create([
            'client_project_id' => $activeProject->id,
            'occurred_on' => '2026-11-10',
            'description' => 'Lumber receipt',
            'amount' => 50.00,
            'receipt_path' => 'receipts/test.webp',
        ]);

        $sentInvoice = \App\Models\ClientProjectInvoice::query()->create([
            'client_project_id' => $activeProject->id,
            'installment_number' => 1,
            'installment_description' => 'Deposit',
            'invoice_number' => 'INV-2026-7701',
            'items' => [
                ['description' => 'Deposit milestone', 'amount' => 1000.00, 'line_kind' => 'labour'],
            ],
            'subtotal' => 1000.00,
            'hst' => 130.00,
            'total' => 1130.00,
            'status' => \App\Models\ClientProjectInvoice::STATUS_SENT,
            'sent_at' => now(),
            'due_on' => '2026-12-01',
        ]);

        $sentHtml = $this->get(route('admin.client-projects.show', $activeProject))
            ->assertOk()
            ->assertSee('QUO-2026-7701', false)
            ->assertSee('CA$1,130.00', false)
            ->assertSee("cpOpenQuotationDrawer('qd-{$quotation->id}')", false)
            ->assertSee(route('admin.client-projects.quotation.edit', [$activeProject, $quotation]), false)
            ->assertSee('INV-2026-7701', false)
            ->assertSee(route('admin.client-projects.invoices.edit', [$activeProject, $sentInvoice]), false)
            ->assertSee(route('admin.client-projects.invoices.pdf', [$activeProject, $sentInvoice]), false)
            ->assertSee('attach_expense_receipts', false)
            ->assertSee(route('admin.client-projects.invoices.mark-paid', [$activeProject, $sentInvoice]), false)
            ->assertSee(route('admin.client-projects.invoices.reminders.toggle', [$activeProject, $sentInvoice]), false)
            ->getContent();

        $this->assertMatchesRegularExpression('/Amount paid.*?CA\$0\.00/s', $sentHtml);
        $this->assertMatchesRegularExpression('/Balance due.*?CA\$1,130\.00/s', $sentHtml);

        $sentInvoice->update([
            'status' => \App\Models\ClientProjectInvoice::STATUS_PAID,
            'paid_at' => now(),
            'paid_amount' => 1130.00,
            'payment_method' => \App\Models\ClientProjectInvoice::PAYMENT_METHOD_ETRANSFER,
        ]);

        $paidHtml = $this->get(route('admin.client-projects.show', $activeProject))
            ->assertOk()
            ->assertSee('INV-2026-7701', false)
            ->assertSee(route('admin.client-projects.invoices.pdf', [$activeProject, $sentInvoice]), false)
            ->getContent();

        $qaInvoiceStart = strpos($paidHtml, 'data-cp-qa-section="invoice"');
        $this->assertNotFalse($qaInvoiceStart);
        $qaInvoiceSection = substr($paidHtml, (int) $qaInvoiceStart, 4500);
        $this->assertStringNotContainsString('Send invoice', $qaInvoiceSection);
        $this->assertStringNotContainsString('Mark paid', $qaInvoiceSection);
        $this->assertStringNotContainsString('Stop reminders', $qaInvoiceSection);
        $this->assertMatchesRegularExpression('/Balance due.*?CA\$0\.00/s', $qaInvoiceSection);
    }

    /**
     * @param  array{name: ?string, email: ?string, phone: ?string, address: ?string}  $directoryBefore
     * @param  array{client_name: mixed, client_email: mixed, client_phone: mixed, client_address: mixed}  $projectContactBefore
     */
    private function assertDirectoryUntouched(
        Client $client,
        ClientProject $project,
        array $directoryBefore,
        array $projectContactBefore,
        int $clientsBefore
    ): void {
        $project->refresh();
        $this->assertSame($client->id, (int) $project->client_id);
        $this->assertSame($clientsBefore, Client::query()->count());
        $this->assertSame($directoryBefore, $this->directorySnapshot($client));
        $this->assertSame($projectContactBefore, $this->projectContactSnapshot($project));
    }
}

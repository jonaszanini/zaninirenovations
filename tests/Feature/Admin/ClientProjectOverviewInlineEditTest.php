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

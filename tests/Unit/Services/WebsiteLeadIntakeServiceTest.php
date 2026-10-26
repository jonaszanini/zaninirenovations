<?php

namespace Tests\Unit\Services;

use App\Models\ClientProject;
use App\Services\WebsiteLeadIntakeService;
use ReflectionMethod;
use Tests\IntegrationTestCase;

class WebsiteLeadIntakeServiceTest extends IntegrationTestCase
{
    public function test_create_lead_names_project_with_website_client_and_subject(): void
    {
        $service = app(WebsiteLeadIntakeService::class);

        $project = $service->createLeadFromSubmission([
            'user_name' => 'Shawn Millman',
            'user_email' => 'shawn@example.test',
            'email_subject' => 'Fence quote',
            'email_message' => 'Need a quote for a cedar fence along the side yard.',
        ]);

        $this->assertNotNull($project);
        $this->assertSame('Website — Shawn Millman — Fence quote', $project->name);
        $this->assertSame(ClientProject::CONTACTED_VIA_WEBSITE_FORM, $project->contacted_via);
        $this->assertStringContainsString('Need a quote for a cedar fence along the side yard.', (string) $project->notes);
    }

    public function test_create_lead_falls_back_to_inquiry_when_subject_empty(): void
    {
        $service = app(WebsiteLeadIntakeService::class);

        $project = $service->createLeadFromSubmission([
            'user_name' => 'Shawn Millman',
            'user_email' => 'shawn@example.test',
            'email_subject' => '   ',
            'email_message' => 'General question about availability.',
        ]);

        $this->assertNotNull($project);
        $this->assertSame('Website — Shawn Millman — Inquiry', $project->name);
    }

    public function test_build_project_name_truncates_long_subject_without_exceeding_column_limit(): void
    {
        $service = app(WebsiteLeadIntakeService::class);
        $longSubject = str_repeat('A', 300);

        $name = $this->invokeBuildProjectName($service, 'Shawn Millman', [
            'email_subject' => $longSubject,
        ]);

        $this->assertLessThanOrEqual(255, strlen($name));
        $this->assertStringStartsWith('Website — Shawn Millman — ', $name);
        $this->assertSame(
            255,
            strlen($name),
            'Expected the subject portion to be trimmed so the full name fits in 255 characters.'
        );
    }

    public function test_create_lead_returns_null_when_client_name_missing(): void
    {
        $service = app(WebsiteLeadIntakeService::class);

        $project = $service->createLeadFromSubmission([
            'user_name' => '',
            'user_email' => 'nobody@example.test',
            'email_subject' => 'Hello',
            'email_message' => 'Hi',
        ]);

        $this->assertNull($project);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function invokeBuildProjectName(WebsiteLeadIntakeService $service, string $clientName, array $data): string
    {
        $method = new ReflectionMethod(WebsiteLeadIntakeService::class, 'buildProjectName');
        $method->setAccessible(true);

        return $method->invoke($service, $clientName, $data);
    }
}

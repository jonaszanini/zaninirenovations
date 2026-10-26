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

        $this->assertLessThanOrEqual(255, mb_strlen($name, 'UTF-8'));
        $this->assertStringStartsWith('Website — Shawn Millman — ', $name);
        $this->assertSame(
            255,
            mb_strlen($name, 'UTF-8'),
            'Expected the subject portion to be trimmed so the full name fits in 255 characters.'
        );
    }

    public function test_build_project_name_squishes_subject_line_breaks(): void
    {
        $service = app(WebsiteLeadIntakeService::class);

        $name = $this->invokeBuildProjectName($service, 'Shawn Millman', [
            'email_subject' => "Fence\r\nquote",
        ]);

        $this->assertSame('Website — Shawn Millman — Fence quote', $name);
        $this->assertStringNotContainsString("\r", $name);
        $this->assertStringNotContainsString("\n", $name);
    }

    public function test_build_project_name_truncates_multibyte_subject_by_character_count(): void
    {
        $service = app(WebsiteLeadIntakeService::class);
        $subject = str_repeat('字', 120).str_repeat('😀', 120);

        $name = $this->invokeBuildProjectName($service, 'Shawn Millman', [
            'email_subject' => $subject,
        ]);

        $this->assertSame(255, mb_strlen($name, 'UTF-8'));
        $this->assertStringStartsWith('Website — Shawn Millman — ', $name);
        $this->assertTrue(mb_check_encoding($name, 'UTF-8'));
        $tail = mb_substr($name, mb_strlen('Website — Shawn Millman — ', 'UTF-8'), null, 'UTF-8');
        $this->assertSame(mb_substr($subject, 0, mb_strlen($tail, 'UTF-8'), 'UTF-8'), $tail);
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

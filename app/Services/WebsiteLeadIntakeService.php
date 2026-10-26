<?php

namespace App\Services;

use App\Models\ClientProject;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Turns a validated public contact-form submission into a CRM lead
 * (ClientProject in "Visit required" status) so inbound inquiries show up
 * in the admin pipeline instead of living only in an email inbox.
 */
class WebsiteLeadIntakeService
{
    public function __construct(
        protected ClientProjectContactService $contactService,
        protected ClientProjectStatusService $statusService,
    ) {
    }

    /**
     * @param  array<string, mixed>  $data  The validated contact-form payload
     *                                      (see ContactFormMailer::handle()).
     */
    public function createLeadFromSubmission(array $data): ?ClientProject
    {
        $clientName = trim((string) ($data['user_name'] ?? ''));
        if ($clientName === '') {
            return null;
        }

        try {
            // location_city (from the "extended" contact form variant) is a
            // free-text city name, not a full street address — it goes into
            // notes rather than client_address, which downstream billing/PDF
            // views treat as the project's full service address.
            $projectData = [
                'client_name' => $clientName,
                'client_email' => $data['user_email'] ?? null,
                'client_phone' => $this->normalizePhoneDigits($data['user_phone'] ?? null),
            ];

            // Resolves/creates the directory Client and sets client_id on $projectData.
            $this->contactService->applyForCreate($projectData);

            $project = ClientProject::create(array_merge($projectData, [
                'name' => $this->buildProjectName($clientName, $data),
                'contacted_via' => ClientProject::CONTACTED_VIA_WEBSITE_FORM,
                'billing_type' => ClientProject::BILLING_FIXED,
                'hourly_rate' => null,
                'visit_required' => true,
                'visit_date' => null,
                'visit_done' => false,
                'notes' => $this->buildNotes($data),
                'status' => ClientProject::STATUS_VISIT_REQUIRED,
                'status_changed_at' => now(),
            ]));

            // false: never send the "quote received" project email from here —
            // ContactFormMailer already sent the visitor its own auto-reply.
            $this->statusService->onCreated($project, false);

            return $project;
        } catch (\Throwable $e) {
            // A CRM-side hiccup must never surface to the website visitor —
            // the contact email itself is the critical path here.
            Log::error('Website contact form: failed to auto-create CRM lead.', [
                'message' => $e->getMessage(),
            ]);

            return null;
        }
    }

    /**
     * @param  array<string, mixed>  $data
     */
    protected function buildProjectName(string $clientName, array $data): string
    {
        $clientName = trim($clientName);
        $subject = trim((string) ($data['email_subject'] ?? ''));
        $tail = $subject !== '' ? $subject : 'Inquiry';

        $prefix = 'Website — '.$clientName.' — ';
        $maxTailLength = 255 - strlen($prefix);
        if ($maxTailLength < 1) {
            return Str::limit($prefix, 255, '');
        }

        if (strlen($tail) > $maxTailLength) {
            $tail = Str::limit($tail, $maxTailLength, '');
        }

        return $prefix.$tail;
    }

    /** @param  array<string, mixed>  $data */
    protected function buildNotes(array $data): string
    {
        $lines = ['Auto-created from the website contact form.', ''];

        $lines[] = 'Subject: '.trim((string) ($data['email_subject'] ?? ''));

        $city = trim((string) ($data['location_city'] ?? ''));
        if ($city !== '') {
            $lines[] = 'Location / city: '.$city;
        }

        $budget = trim((string) ($data['budget'] ?? ''));
        if ($budget !== '') {
            $lines[] = 'Budget: '.$budget;
        }

        $startingDate = trim((string) ($data['starting_date'] ?? ''));
        if ($startingDate !== '') {
            $lines[] = 'Preferred start: '.$startingDate;
        }

        if (array_key_exists('sms_opt_in', $data)) {
            $optIn = filter_var($data['sms_opt_in'], FILTER_VALIDATE_BOOLEAN);
            $lines[] = 'SMS opt-in: '.($optIn ? 'Yes' : 'No');
        }

        $lines[] = '';
        $lines[] = 'Message:';
        $lines[] = trim((string) ($data['email_message'] ?? ''));

        return implode("\n", $lines);
    }

    protected function normalizePhoneDigits(mixed $phone): ?string
    {
        $digits = preg_replace('/\D/', '', (string) $phone);

        return $digits !== '' && strlen($digits) === 10 ? $digits : null;
    }
}

<?php

namespace App\Services;

use App\Models\ClientProject;
use Illuminate\Support\Carbon;

/**
 * Builds a "Add to Google Calendar" URL for a ClientProject using Google's
 * public event-template endpoint. Works without any auth.
 */
class GoogleCalendarLink
{
    /**
     * Admin-facing "Add to Google Calendar" link shown on the project
     * detail page. Contains internal fields (Status, Notes) so Jonas
     * can see everything in one place.
     */
    public static function forProject(ClientProject $project): ?string
    {
        return self::build($project, self::buildAdminDetails($project));
    }

    /**
     * Client-facing "Add to Google Calendar" link sent with the
     * contract email. Only exposes information the client already
     * has (project name, address, contractor contact details) — no
     * internal status or admin notes.
     */
    public static function forClient(ClientProject $project): ?string
    {
        return self::build($project, self::buildClientDetails($project));
    }

    /**
     * Shared URL builder for both variants. Returns null when the
     * project doesn't have both a start and end date set, because
     * Google's template endpoint requires the `dates` parameter.
     */
    private static function build(ClientProject $project, string $details): ?string
    {
        if (!$project->start_date || !$project->end_date) {
            return null;
        }

        $start = Carbon::parse($project->start_date)->startOfDay();
        // Google Calendar all-day events are exclusive on the end date.
        $end = Carbon::parse($project->end_date)->startOfDay()->addDay();

        $params = [
            'action' => 'TEMPLATE',
            'text' => $project->name.' — '.$project->client_name,
            'dates' => $start->format('Ymd').'/'.$end->format('Ymd'),
            'details' => $details,
            'location' => (string) ($project->client_address ?? ''),
        ];

        return 'https://calendar.google.com/calendar/render?'.http_build_query($params);
    }

    private static function buildAdminDetails(ClientProject $project): string
    {
        $lines = [
            'Project: '.$project->name,
            'Client: '.$project->client_name,
        ];

        if ($project->client_phone) {
            $lines[] = 'Phone: '.\App\Models\ClientProject::formatPhone($project->client_phone);
        }
        if ($project->client_email) {
            $lines[] = 'Email: '.$project->client_email;
        }
        if ($project->client_address) {
            $lines[] = 'Address: '.$project->client_address;
        }
        $lines[] = '';
        $lines[] = 'Status: '.$project->statusLabel();

        if ($project->notes) {
            $lines[] = '';
            $lines[] = 'Notes:';
            $lines[] = (string) $project->notes;
        }

        return implode("\n", $lines);
    }

    /**
     * Client-friendly description: a short confirmation blurb + our
     * contact info so they can reach out directly from the event.
     * Intentionally omits Status and Notes.
     */
    private static function buildClientDetails(ClientProject $project): string
    {
        $lines = [
            'Work scheduled by Zanini Renovations for project "'.$project->name.'".',
            '',
        ];

        if ($project->client_address) {
            $lines[] = 'Location: '.$project->client_address;
        }

        $lines[] = '';
        $lines[] = 'Contractor: Jonas Zanini — Zanini Renovations';
        $lines[] = 'Phone: (647) 834-7570';
        $lines[] = 'Email: zaninirenovations@gmail.com';
        $lines[] = 'Website: https://zaninirenovation.ca';

        return implode("\n", $lines);
    }
}

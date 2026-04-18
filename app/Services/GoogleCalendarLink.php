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
    public static function forProject(ClientProject $project): ?string
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
            'details' => self::buildDetails($project),
            'location' => (string) ($project->client_address ?? ''),
        ];

        return 'https://calendar.google.com/calendar/render?'.http_build_query($params);
    }

    private static function buildDetails(ClientProject $project): string
    {
        $lines = [
            'Project: '.$project->name,
            'Client: '.$project->client_name,
        ];

        if ($project->client_phone) {
            $lines[] = 'Phone: '.$project->client_phone;
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
}

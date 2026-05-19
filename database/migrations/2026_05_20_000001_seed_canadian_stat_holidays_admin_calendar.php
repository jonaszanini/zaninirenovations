<?php

use App\Models\AdminCalendarEvent;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration {
    /**
     * Canadian stat holidays for 2026–2027 as standalone admin calendar events.
     *
     * @return list<array{title: string, start: string, end?: string, description?: string}>
     */
    private function holidays(): array
    {
        return [
            // 2026
            ['title' => 'Canada Day', 'start' => '2026-07-01'],
            ['title' => 'Labour Day', 'start' => '2026-09-07'],
            ['title' => 'Thanksgiving Day', 'start' => '2026-10-12'],
            ['title' => 'Christmas Day', 'start' => '2026-12-25'],
            ['title' => 'Boxing Day', 'start' => '2026-12-26'],
            [
                'title' => 'Boxing Day (observed)',
                'start' => '2026-12-28',
                'description' => 'Observed / paid day when Boxing Day falls on a weekend.',
            ],
            // 2027
            ['title' => "New Year's Day", 'start' => '2027-01-01'],
            ['title' => 'Family Day', 'start' => '2027-02-15'],
            ['title' => 'Good Friday', 'start' => '2027-03-26'],
            ['title' => 'Easter Monday (Optional)', 'start' => '2027-03-29'],
            ['title' => 'Victoria Day', 'start' => '2027-05-24'],
            ['title' => 'Canada Day', 'start' => '2027-07-01'],
            ['title' => 'Civic Holiday (Optional)', 'start' => '2027-08-02'],
            ['title' => 'Labour Day', 'start' => '2027-09-06'],
            ['title' => 'Thanksgiving Day', 'start' => '2027-10-11'],
            ['title' => 'Remembrance Day (Optional)', 'start' => '2027-11-11'],
            ['title' => 'Christmas Day', 'start' => '2027-12-25'],
            ['title' => 'Boxing Day', 'start' => '2027-12-26'],
        ];
    }

    public function up(): void
    {
        foreach ($this->holidays() as $holiday) {
            $start = $holiday['start'];
            $end = $holiday['end'] ?? $start;

            AdminCalendarEvent::query()->firstOrCreate(
                [
                    'title' => $holiday['title'],
                    'start_date' => $start,
                ],
                [
                    'end_date' => $end,
                    'description' => $holiday['description'] ?? null,
                ]
            );
        }
    }

    public function down(): void
    {
        foreach ($this->holidays() as $holiday) {
            AdminCalendarEvent::query()
                ->where('title', $holiday['title'])
                ->whereDate('start_date', $holiday['start'])
                ->delete();
        }
    }
};

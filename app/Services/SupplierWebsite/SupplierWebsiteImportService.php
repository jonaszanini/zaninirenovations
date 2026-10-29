<?php

namespace App\Services\SupplierWebsite;

use App\Models\ClientProject;
use App\Services\SupplierSiteDistanceService;

class SupplierWebsiteImportService
{
    public function __construct(
        private SupplierWebsitePageFetcher $fetcher,
        private SupplierWebsiteDataExtractor $extractor,
        private SupplierSiteDistanceService $distances,
    ) {}

    /**
     * @return array{ok: bool, message?: string, data?: array<string, mixed>}
     */
    public function preview(string $url): array
    {
        try {
            $pages = $this->fetcher->fetchSitePages($url);
        } catch (\InvalidArgumentException $e) {
            return ['ok' => false, 'message' => $e->getMessage()];
        } catch (\RuntimeException $e) {
            return ['ok' => false, 'message' => $e->getMessage()];
        }

        $extracted = $this->extractor->extract($pages);

        $chosenAddress = null;
        $otherAddresses = [];
        $distanceNote = null;

        if (count($extracted['addresses']) > 0) {
            if (count($extracted['addresses']) === 1) {
                $chosenAddress = $extracted['addresses'][0];
            } else {
                $closest = $this->distances->pickClosestAddress($extracted['addresses']);
                if ($closest !== null) {
                    $chosenAddress = $closest['address'];
                    $otherAddresses = array_values(array_filter(
                        $extracted['addresses'],
                        fn (string $a) => mb_strtolower(trim($a)) !== mb_strtolower(trim($chosenAddress))
                    ));
                    $distanceNote = sprintf(
                        'Closest to home: %s km (%s min drive).',
                        number_format($closest['distance_km'], 1),
                        (string) ($closest['drive_minutes'] ?? '—')
                    );
                } else {
                    $chosenAddress = $extracted['addresses'][0];
                    $otherAddresses = array_slice($extracted['addresses'], 1);
                }
            }
        }

        $notes = [];
        if (filled($extracted['description'])) {
            $notes[] = $extracted['description'];
        }
        if (filled($extracted['hours'])) {
            $notes[] = 'Hours: '.$extracted['hours'];
        }
        if ($extracted['social_links'] !== []) {
            $notes[] = 'Social: '.implode(', ', $extracted['social_links']);
        }

        $phone = isset($extracted['phones'][0])
            ? ClientProject::formatPhone($extracted['phones'][0])
            : null;
        $email = $extracted['emails'][0] ?? null;
        $tags = $extracted['tags'] ?? [];

        return [
            'ok' => true,
            'data' => [
                'name' => $extracted['name'],
                'url' => $extracted['website_url'],
                'phone' => $phone,
                'email' => $email,
                'address' => $chosenAddress,
                'other_addresses' => $otherAddresses,
                'distance_note' => $distanceNote,
                'notes' => $notes !== [] ? implode("\n\n", $notes) : null,
                'logo_url' => $extracted['logo_url'],
                'tags' => $tags !== [] ? implode(', ', $tags) : null,
                'phones_found' => $extracted['phones'],
                'emails_found' => $extracted['emails'],
            ],
        ];
    }
}

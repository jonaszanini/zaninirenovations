<?php

namespace App\Support;

class GooglePickerItems
{
    /**
     * @param  list<mixed>  $rawItems
     * @return list<array{baseUrl: string, mimeType: ?string, filename: ?string, id: ?string}>
     */
    public static function parse(array $rawItems): array
    {
        $items = [];

        foreach ($rawItems as $raw) {
            if (! is_string($raw) || $raw === '') {
                continue;
            }

            $decoded = json_decode($raw, true);
            if (! is_array($decoded)) {
                continue;
            }

            $baseUrl = isset($decoded['baseUrl']) ? trim((string) $decoded['baseUrl']) : '';
            if ($baseUrl === '') {
                continue;
            }

            $items[] = [
                'baseUrl' => $baseUrl,
                'mimeType' => isset($decoded['mimeType']) ? trim((string) $decoded['mimeType']) : null,
                'filename' => isset($decoded['filename']) ? trim((string) $decoded['filename']) : null,
                'id' => isset($decoded['id']) ? trim((string) $decoded['id']) : null,
            ];
        }

        return array_values(array_unique($items, SORT_REGULAR));
    }

    /**
     * @return list<array{baseUrl: string, mimeType: ?string, filename: ?string, id: ?string}>
     */
    public static function parseFromRequest(mixed $rawItems): array
    {
        if (! is_array($rawItems)) {
            return [];
        }

        return self::parse(array_values($rawItems));
    }
}

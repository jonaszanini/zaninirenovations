<?php

namespace App\Support;

class GoogleReviews
{
    /**
     * @return array<string, mixed>
     */
    public static function config(): array
    {
        return (array) config('google_reviews', []);
    }

    public static function ratingValue(): string
    {
        return (string) (self::config()['rating_value'] ?? '5.0');
    }

    public static function reviewCount(): int
    {
        return (int) (self::config()['review_count'] ?? 0);
    }

    public static function profileUrl(): string
    {
        return (string) (self::config()['profile_url'] ?? SeoStructuredData::googleBusinessUrl());
    }

    /**
     * Reviews that have quote text (Bianca C. left a star rating only).
     *
     * @return list<array{author: string, text: string, project?: string}>
     */
    public static function textualReviews(): array
    {
        $out = [];
        foreach ((array) (self::config()['reviews'] ?? []) as $row) {
            if (! is_array($row)) {
                continue;
            }
            $text = trim((string) ($row['text'] ?? ''));
            $author = trim((string) ($row['author'] ?? ''));
            if ($text === '' || $author === '') {
                continue;
            }
            $out[] = $row;
        }

        return $out;
    }
}

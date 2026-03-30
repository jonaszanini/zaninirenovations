<?php

namespace App\Support;

final class ProjectCategory
{
    /**
     * Slug => display label (matches your site categories).
     *
     * @return array<string, string>
     */
    public static function options(): array
    {
        return [
            'deck' => 'Deck',
            'fencing' => 'Fencing',
            'basement_renovation' => 'Basement renovation',
            'tile' => 'Tile',
            'flooring' => 'Flooring',
            'forniture' => 'Forniture',
        ];
    }

    /**
     * @return list<string>
     */
    public static function slugs(): array
    {
        return array_keys(self::options());
    }

    public static function label(string $slug): string
    {
        return self::options()[$slug] ?? $slug;
    }
}

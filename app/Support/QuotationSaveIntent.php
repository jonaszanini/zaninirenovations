<?php

namespace App\Support;

use Illuminate\Http\Request;

/**
 * Draft vs publish for calculator quotation saves.
 *
 * Tile posts {@see self::TILE_DRAFT_FIELD}; every other type uses save_intent.
 */
class QuotationSaveIntent
{
    public const DRAFT = 'draft';

    public const PUBLISH = 'publish';

    public const TILE_DRAFT_FIELD = 'is_draft';

    public static function isDraft(?Request $request = null): bool
    {
        $request ??= request();

        if ($request->input('save_intent') === self::DRAFT) {
            return true;
        }

        return $request->input(self::TILE_DRAFT_FIELD) === '1'
            || $request->input(self::TILE_DRAFT_FIELD) === 1
            || $request->input(self::TILE_DRAFT_FIELD) === true;
    }
}

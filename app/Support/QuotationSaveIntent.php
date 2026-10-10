<?php

namespace App\Support;

use Illuminate\Http\Request;

/**
 * Single source of truth for draft vs publish on every quotation type.
 *
 * Validation and persisted {@see $is_draft} must both call this helper so a
 * crafted mix of {@code save_intent} and {@code is_draft} cannot skip
 * publish-only fields and still land as published.
 *
 * Rule: when {@code save_intent} is present it wins (publish unless it is
 * exactly "draft"). Otherwise tile's legacy {@code is_draft=1} is a draft.
 */
class QuotationSaveIntent
{
    public const DRAFT = 'draft';

    public const PUBLISH = 'publish';

    public const TILE_DRAFT_FIELD = 'is_draft';

    public static function fromRequest(?Request $request = null): string
    {
        $request ??= request();

        if ($request->exists('save_intent')) {
            return $request->input('save_intent') === self::DRAFT
                ? self::DRAFT
                : self::PUBLISH;
        }

        $tile = $request->input(self::TILE_DRAFT_FIELD);

        return ($tile === '1' || $tile === 1 || $tile === true)
            ? self::DRAFT
            : self::PUBLISH;
    }

    public static function isDraft(?Request $request = null): bool
    {
        return self::fromRequest($request) === self::DRAFT;
    }
}

<?php

namespace App\Support;

use InvalidArgumentException;

class AdminInfoTipInclude
{
    public const MESSAGE = "admin.partials.info_tip no longer accepts 'text' or 'html'; pass tipText/tipHtml.";

    /**
     * @param  array<string, mixed>  $data
     */
    public static function assertNoLegacyKeys(array $data): void
    {
        if (! array_key_exists('text', $data) && ! array_key_exists('html', $data)) {
            return;
        }

        if (app()->environment('production')) {
            logger()->error(self::MESSAGE);

            return;
        }

        throw new InvalidArgumentException(self::MESSAGE);
    }

    /**
     * @param  array<string, mixed>  $defined
     * @return array{tipId: mixed, tipLabel: mixed, tipText: mixed, tipHtml: mixed}
     */
    public static function resolve(array $defined): array
    {
        self::assertNoLegacyKeys($defined);

        return [
            'tipId' => array_key_exists('tipId', $defined) ? $defined['tipId'] : null,
            'tipLabel' => array_key_exists('tipLabel', $defined) ? $defined['tipLabel'] : null,
            'tipText' => array_key_exists('tipText', $defined) ? $defined['tipText'] : null,
            'tipHtml' => array_key_exists('tipHtml', $defined) ? $defined['tipHtml'] : null,
        ];
    }
}

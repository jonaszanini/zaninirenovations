<?php

namespace App\Support;

use App\Models\LowDeckFormulaSetting;

/**
 * Builds client-facing low deck work descriptions from the admin-editable
 * template and live calculator geometry (dimensions, area, joist spacing).
 */
final class LowDeckWorkDescriptionGenerator
{
    public const PLACEHOLDER_DIMENSIONS = '{{dimensions}}';

    public const PLACEHOLDER_AREA = '{{area}}';

    public const PLACEHOLDER_JOIST_SPACING = '{{joist_spacing}}';

    public static function defaultTemplate(): string
    {
        return 'This project involves constructing a '.self::PLACEHOLDER_DIMENSIONS.' low-level deck, totaling '.self::PLACEHOLDER_AREA.' square feet in area. The deck will be built on concrete blocks, utilizing 4x4 pressure-treated posts spaced a maximum of 5 feet apart for optimal structural support. The framing will consist of 2x6 pressure-treated joists spaced '.self::PLACEHOLDER_JOIST_SPACING.' on center, and the decking surface will feature 5/4 x 6 pressure-treated boards.'."\n\n"
            .'Note: No railing is included in this scope. Please let us know if a railing system is required.';
    }

    public static function template(?LowDeckFormulaSetting $settings = null): string
    {
        $settings ??= LowDeckFormulaSetting::current();
        $stored = trim((string) ($settings->work_description_template ?? ''));

        return $stored !== '' ? $stored : self::defaultTemplate();
    }

    /**
     * @param  array<string, mixed>  $geometry  Calculator geometry payload.
     */
    public static function generate(array $geometry, ?LowDeckFormulaSetting $settings = null): string
    {
        return self::generateFromTemplate($geometry, self::template($settings));
    }

    /**
     * @param  array<string, mixed>  $geometry
     */
    public static function generateFromTemplate(array $geometry, string $template): string
    {
        $replacements = [
            self::PLACEHOLDER_DIMENSIONS => self::formatDimensions(
                (float) ($geometry['width_ft'] ?? 0),
                (float) ($geometry['length_ft'] ?? 0)
            ),
            self::PLACEHOLDER_AREA => self::formatArea((float) ($geometry['area_sq_ft'] ?? 0)),
            self::PLACEHOLDER_JOIST_SPACING => self::formatJoistSpacingInches(
                (float) (($geometry['joists']['field_spacing_in'] ?? $geometry['joist_spacing_in'] ?? 0))
            ),
        ];

        return str_replace(
            array_keys($replacements),
            array_values($replacements),
            $template
        );
    }

    public static function formatFoot(float $feet): string
    {
        if ($feet <= 0) {
            return "0'";
        }

        if (abs($feet - round($feet)) < 1e-6) {
            return (int) round($feet)."'";
        }

        return rtrim(rtrim(number_format($feet, 2, '.', ''), '0'), '.')."'";
    }

    public static function formatDimensions(float $widthFt, float $lengthFt): string
    {
        return self::formatFoot($widthFt).'x'.self::formatFoot($lengthFt);
    }

    public static function formatArea(float $sqFt): string
    {
        if ($sqFt <= 0) {
            return '0';
        }

        if (abs($sqFt - round($sqFt)) < 0.01) {
            return (string) (int) round($sqFt);
        }

        return rtrim(rtrim(number_format($sqFt, 2, '.', ''), '0'), '.');
    }

    public static function formatJoistSpacingInches(float $spacingIn): string
    {
        if ($spacingIn <= 0) {
            return '16 inches';
        }

        if (abs($spacingIn - round($spacingIn)) < 0.01) {
            return (int) round($spacingIn).' inches';
        }

        return rtrim(rtrim(number_format($spacingIn, 2, '.', ''), '0'), '.').' inches';
    }
}

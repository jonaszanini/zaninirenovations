<?php

namespace App\Support;

use App\Models\FenceFormulaSetting;
use App\Models\FenceQuotation;

/**
 * Deterministic fence work description from an admin-editable template
 * and the live calculator snapshot (runs, height, style, gates).
 */
final class FenceWorkDescriptionGenerator
{
    public const PLACEHOLDER_LINEAR_FT = '{{linear_ft}}';

    public const PLACEHOLDER_HEIGHT = '{{height}}';

    public const PLACEHOLDER_STYLE = '{{style}}';

    public const PLACEHOLDER_RUNS = '{{runs}}';

    public const PLACEHOLDER_POST_SIZE = '{{post_size}}';

    public const PLACEHOLDER_GATES = '{{gates}}';

    public const PLACEHOLDER_POSTS = '{{posts}}';

    public static function defaultTemplate(): string
    {
        return 'Install about '.self::PLACEHOLDER_LINEAR_FT.' linear feet of '
            .self::PLACEHOLDER_HEIGHT.' ft '.self::PLACEHOLDER_STYLE
            .' fence in '.self::PLACEHOLDER_RUNS.' runs. '
            .self::PLACEHOLDER_POST_SIZE.' posts set in concrete. '
            .self::PLACEHOLDER_GATES
            .'. Includes pickets, pressure-treated rails and post caps.';
    }

    public static function template(?FenceFormulaSetting $settings = null): string
    {
        $settings ??= FenceFormulaSetting::current();
        $stored = trim((string) ($settings->work_description_template ?? ''));

        return $stored !== '' ? $stored : self::defaultTemplate();
    }

    /**
     * @param  array<string, mixed>  $calc
     */
    public static function generate(array $calc, ?FenceFormulaSetting $settings = null): string
    {
        return self::generateFromTemplate($calc, self::template($settings));
    }

    /**
     * @param  array<string, mixed>  $calc
     */
    public static function generateFromTemplate(array $calc, string $template): string
    {
        $text = str_replace(
            array_keys(self::replacements($calc)),
            array_values(self::replacements($calc)),
            $template
        );

        return preg_replace('/\b1 runs\b/', '1 run', $text) ?? $text;
    }

    /**
     * @param  array<string, mixed>  $calc
     * @return array<string, string>
     */
    public static function replacements(array $calc): array
    {
        $inputs = is_array($calc['inputs'] ?? null) ? $calc['inputs'] : [];
        $geom = is_array($calc['geometry'] ?? null) ? $calc['geometry'] : [];

        $linearFt = (float) ($geom['total_linear_ft'] ?? 0);
        $height = (int) round((float) ($inputs['fence_height_ft'] ?? 6));
        $style = ($inputs['board_style'] ?? FenceQuotation::BOARD_STYLE_STANDARD) === FenceQuotation::BOARD_STYLE_OVERLAPPING
            ? 'overlapping board'
            : 'standard board';
        $runs = count($geom['sides_breakdown'] ?? $inputs['sides'] ?? []);
        $postSize = ($inputs['post_size'] ?? FenceQuotation::POST_SIZE_4X4) === FenceQuotation::POST_SIZE_6X6
            ? '6×6'
            : '4×4';
        $posts = (int) ($geom['posts_before_waste'] ?? 0);

        return [
            self::PLACEHOLDER_LINEAR_FT => self::formatNumber($linearFt),
            self::PLACEHOLDER_HEIGHT => (string) $height,
            self::PLACEHOLDER_STYLE => $style,
            self::PLACEHOLDER_RUNS => (string) max(0, $runs),
            self::PLACEHOLDER_POST_SIZE => $postSize,
            self::PLACEHOLDER_GATES => self::gatesPhrase($calc),
            self::PLACEHOLDER_POSTS => (string) max(0, $posts),
        ];
    }

    public static function runCountPhrase(int $runs): string
    {
        $n = max(0, $runs);

        return $n === 1 ? '1 run' : $n.' runs';
    }

    /**
     * @param  array<string, mixed>  $calc
     */
    public static function gatesPhrase(array $calc): string
    {
        $inputs = is_array($calc['inputs'] ?? null) ? $calc['inputs'] : [];
        $geom = is_array($calc['geometry'] ?? null) ? $calc['geometry'] : [];
        $labels = [];

        foreach ($geom['sides_breakdown'] ?? [] as $side) {
            if (! is_array($side) || empty($side['gate_goes_here'])) {
                continue;
            }
            $spec = FenceGateSpec::fromSide($side);
            $labels[] = $spec->shortLabel();
        }

        $placed = count($labels);
        $stated = max(0, (int) ($inputs['gate_count'] ?? $placed));
        $extra = max(0, $stated - $placed);
        $extraSpec = FenceGateSpec::fromKey($inputs['extra_gate_preset'] ?? FenceGateSpec::DEFAULT_KEY);
        for ($i = 0; $i < $extra; $i++) {
            $labels[] = $extraSpec->shortLabel();
        }

        if ($labels === []) {
            return 'No gates';
        }

        if (count($labels) === 1) {
            return 'One '.$labels[0].' gate';
        }

        return count($labels).' gates ('.implode(', ', $labels).')';
    }

    public static function formatNumber(float $value): string
    {
        if (abs($value - round($value)) < 0.01) {
            return (string) (int) round($value);
        }

        return rtrim(rtrim(number_format($value, 2, '.', ''), '0'), '.');
    }
}

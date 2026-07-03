<?php

namespace App\Support;

/**
 * LowDeckDiagramRenderer — builds the three print-ready SVG layout diagrams
 * (foundations, framing, decking) from a stored calculation geometry array.
 *
 * The live calculator form draws the same diagrams in JavaScript; this PHP
 * version is used for static contexts (purchase list, client PDF) where no
 * browser scripting runs. Both read the identical geometry shape produced
 * by {@see \App\Services\LowDeckQuotationCalculator}.
 */
final class LowDeckDiagramRenderer
{
    /** Drawing area in user units; the deck is scaled to fit inside. */
    private const CANVAS_W = 640.0;

    private const CANVAS_H = 380.0;

    private const PAD = 46.0;

    /** 12 in. concrete pier hole (plan view). */
    private const HOLE_FT = 1.0;

    /** Nominal 2× lumber thickness (rim joist / joist width in plan). */
    private const RIM_JOIST_FT = 0.125;

    /** Actual 4×4 post width in plan. */
    private const POST_FT = 3.5 / 12.0;

    /**
     * @param  array<string, mixed>  $geometry
     * @return array{foundations: string, framing: string, decking: string}
     */
    public static function render(array $geometry): array
    {
        $runFt = (float) ($geometry['run_ft'] ?? 0);
        $spanFt = (float) ($geometry['span_ft'] ?? 0);

        if ($runFt <= 0 || $spanFt <= 0) {
            $empty = '<svg viewBox="0 0 10 10" xmlns="http://www.w3.org/2000/svg"></svg>';

            return ['foundations' => $empty, 'framing' => $empty, 'decking' => $empty];
        }

        $scale = min(
            (self::CANVAS_W - 2 * self::PAD) / $runFt,
            (self::CANVAS_H - 2 * self::PAD) / $spanFt
        );
        $w = $runFt * $scale;
        $h = $spanFt * $scale;
        $x0 = (self::CANVAS_W - $w) / 2;
        $y0 = (self::CANVAS_H - $h) / 2;

        $ctx = [
            'scale' => $scale,
            'x0' => $x0,
            'y0' => $y0,
            'w' => $w,
            'h' => $h,
            'runFt' => $runFt,
            'spanFt' => $spanFt,
        ];

        return [
            'foundations' => self::foundations($geometry, $ctx),
            'framing' => self::framing($geometry, $ctx),
            'decking' => self::decking($geometry, $ctx),
        ];
    }

    private static function foundations(array $g, array $c): string
    {
        $blocks = $g['blocks'] ?? [];
        $xs = $blocks['positions_x_ft'] ?? [];
        $ys = $blocks['positions_y_ft'] ?? [];

        $body = self::deckRect($c);
        $body .= self::rimJoistBands($c);

        foreach ($xs as $fx) {
            foreach ($ys as $fy) {
                $body .= self::foundationBlock((float) $fx, (float) $fy, $c);
            }
        }

        $body .= self::holeSpacingDimensions($xs, $ys, $c);
        $body .= self::dimensions($c);
        $body .= self::caption(sprintf(
            '%d concrete deck blocks, %d x %d grid · 12 in. pier holes · posts inset 1.5 in. for rim joist',
            (int) ($blocks['count'] ?? 0),
            (int) ($blocks['grid_lines_run'] ?? 0),
            (int) ($blocks['grid_lines_span'] ?? 0)
        ));

        return self::wrap($body);
    }

    private static function framing(array $g, array $c): string
    {
        $joists = $g['joists'] ?? [];
        $beams = $g['beams'] ?? [];
        $joistXs = $joists['positions_x_ft'] ?? [];
        $beamYs = $beams['positions_y_ft'] ?? [];

        $body = self::deckRect($c);
        $thick = self::RIM_JOIST_FT * $c['scale'];
        $half = $thick / 2;

        foreach ($beamYs as $fy) {
            $y = $c['y0'] + (float) $fy * $c['scale'] - $half;
            $body .= sprintf(
                '<rect x="%.1f" y="%.1f" width="%.1f" height="%.1f" fill="#b45309" stroke="#92400e" stroke-width="0.75" rx="0.5" />',
                $c['x0'],
                $y,
                $c['w'],
                $thick
            );
        }

        foreach ($joistXs as $fx) {
            $x = $c['x0'] + (float) $fx * $c['scale'] - $half;
            $body .= sprintf(
                '<rect x="%.1f" y="%.1f" width="%.1f" height="%.1f" fill="#0f766e" stroke="#115e59" stroke-width="0.75" rx="0.5" />',
                $x,
                $c['y0'],
                $thick,
                $c['h']
            );
        }

        $body .= self::dimensions($c);
        $body .= self::caption(sprintf(
            '%d joists at %d in. O.C., %d beam lines (%d interior) · members shown 1.5 in. wide',
            (int) ($joists['total_lines'] ?? 0),
            (int) ($g['joist_spacing_in'] ?? 0),
            (int) ($beams['lines_total'] ?? 0),
            (int) ($beams['interior_count'] ?? 0)
        ));

        return self::wrap($body);
    }

    private static function decking(array $g, array $c): string
    {
        $boards = $g['boards'] ?? [];
        $rows = (int) ($boards['rows'] ?? 0);

        $body = self::deckRect($c);

        if ($rows > 0) {
            $pitch = $c['h'] / $rows;
            $boardH = max(1.5, $pitch * 0.82);
            for ($i = 0; $i < $rows; $i++) {
                $y = $c['y0'] + $i * $pitch;
                $body .= sprintf(
                    '<rect x="%.1f" y="%.1f" width="%.1f" height="%.1f" rx="1" fill="#c8a36b" stroke="#a9824a" stroke-width="0.5" />',
                    $c['x0'],
                    $y,
                    $c['w'],
                    $boardH
                );
            }
        }

        $body .= self::dimensions($c);
        $body .= self::caption(sprintf(
            '%d deck boards, 5.5 in. wide, 1/8 in. gap',
            $rows
        ));

        return self::wrap($body);
    }

    private static function foundationBlock(float $fx, float $fy, array $c): string
    {
        [$postCx, $postCy] = self::postCenterFt($fx, $fy, $c['runFt'], $c['spanFt']);
        $holePx = self::HOLE_FT * $c['scale'];
        $holeX = $c['x0'] + $postCx * $c['scale'] - $holePx / 2;
        $holeY = $c['y0'] + $postCy * $c['scale'] - $holePx / 2;

        $postPx = self::POST_FT * $c['scale'];
        $postX = $c['x0'] + $postCx * $c['scale'] - $postPx / 2;
        $postY = $c['y0'] + $postCy * $c['scale'] - $postPx / 2;

        return sprintf(
            '<rect x="%.1f" y="%.1f" width="%.1f" height="%.1f" fill="#f0f9ff" stroke="#0284c7" stroke-width="1.25" />'.
            '<rect x="%.1f" y="%.1f" width="%.1f" height="%.1f" fill="#024768" stroke="#013a52" stroke-width="0.75" rx="0.5" />',
            $holeX,
            $holeY,
            $holePx,
            $holePx,
            $postX,
            $postY,
            $postPx,
            $postPx
        );
    }

    /**
     * Post centre in feet from the deck top-left; inset 1.5 in. from perimeter edges.
     *
     * @return array{0: float, 1: float}
     */
    private static function postCenterFt(float $fx, float $fy, float $runFt, float $spanFt): array
    {
        $rim = self::RIM_JOIST_FT;
        $postHalf = self::POST_FT / 2;
        $eps = 0.001;

        $cx = abs($fx) < $eps
            ? $rim + $postHalf
            : (abs($fx - $runFt) < $eps ? $runFt - $rim - $postHalf : $fx);

        $cy = abs($fy) < $eps
            ? $rim + $postHalf
            : (abs($fy - $spanFt) < $eps ? $spanFt - $rim - $postHalf : $fy);

        return [$cx, $cy];
    }

    /** Rim-joist zone (1.5 in.) inside the deck perimeter. */
    private static function rimJoistBands(array $c): string
    {
        $rim = self::RIM_JOIST_FT * $c['scale'];

        return sprintf(
            '<rect x="%.1f" y="%.1f" width="%.1f" height="%.1f" fill="#e2e8f0" opacity="0.9" />'.
            '<rect x="%.1f" y="%.1f" width="%.1f" height="%.1f" fill="#e2e8f0" opacity="0.9" />'.
            '<rect x="%.1f" y="%.1f" width="%.1f" height="%.1f" fill="#e2e8f0" opacity="0.9" />'.
            '<rect x="%.1f" y="%.1f" width="%.1f" height="%.1f" fill="#e2e8f0" opacity="0.9" />',
            $c['x0'],
            $c['y0'],
            $c['w'],
            $rim,
            $c['x0'],
            $c['y0'] + $c['h'] - $rim,
            $c['w'],
            $rim,
            $c['x0'],
            $c['y0'],
            $rim,
            $c['h'],
            $c['x0'] + $c['w'] - $rim,
            $c['y0'],
            $rim,
            $c['h']
        );
    }

    /**
     * @param  list<float|int>  $xs
     * @param  list<float|int>  $ys
     */
    private static function holeSpacingDimensions(array $xs, array $ys, array $c): string
    {
        if (count($xs) < 2 && count($ys) < 2) {
            return '';
        }

        $svg = '';
        $dimY = $c['y0'] + ((float) ($ys[0] ?? 0)) * $c['scale'];
        $dimX = $c['x0'] + ((float) ($xs[0] ?? 0)) * $c['scale'];

        for ($i = 0; $i < count($xs) - 1; $i++) {
            $x1 = $c['x0'] + (float) $xs[$i] * $c['scale'];
            $x2 = $c['x0'] + (float) $xs[$i + 1] * $c['scale'];
            $gapFt = (float) $xs[$i + 1] - (float) $xs[$i];
            $svg .= self::dimensionLine($x1, $x2, $dimY, self::ftInLabel($gapFt), 'horizontal', $c);
        }

        for ($i = 0; $i < count($ys) - 1; $i++) {
            $y1 = $c['y0'] + (float) $ys[$i] * $c['scale'];
            $y2 = $c['y0'] + (float) $ys[$i + 1] * $c['scale'];
            $gapFt = (float) $ys[$i + 1] - (float) $ys[$i];
            $svg .= self::dimensionLine($dimX, $dimX, $y1, self::ftInLabel($gapFt), 'vertical', $c, $y2);
        }

        return $svg;
    }

    private static function dimensionLine(
        float $x1,
        float $x2,
        float $y,
        string $label,
        string $orientation,
        array $c,
        ?float $y2 = null
    ): string {
        $offset = 18.0;
        $color = '#475569';

        if ($orientation === 'horizontal') {
            $lineY = $y - $offset;
            $midX = ($x1 + $x2) / 2;

            return sprintf(
                '<line x1="%.1f" y1="%.1f" x2="%.1f" y2="%.1f" stroke="%s" stroke-width="1" />'.
                '<line x1="%.1f" y1="%.1f" x2="%.1f" y2="%.1f" stroke="%s" stroke-width="1" />'.
                '<line x1="%.1f" y1="%.1f" x2="%.1f" y2="%.1f" stroke="%s" stroke-width="1" />'.
                '<text x="%.1f" y="%.1f" text-anchor="middle" font-size="11" font-weight="600" fill="%s">%s</text>',
                $x1,
                $lineY,
                $x2,
                $lineY,
                $color,
                $x1,
                $lineY - 4,
                $x1,
                $lineY + 4,
                $color,
                $x2,
                $lineY - 4,
                $x2,
                $lineY + 4,
                $color,
                $midX,
                $lineY - 5,
                $color,
                htmlspecialchars($label, ENT_QUOTES, 'UTF-8')
            );
        }

        $yEnd = $y2 ?? $y;
        $lineX = $x1 - $offset;
        $midY = ($y + $yEnd) / 2;

        return sprintf(
            '<line x1="%.1f" y1="%.1f" x2="%.1f" y2="%.1f" stroke="%s" stroke-width="1" />'.
            '<line x1="%.1f" y1="%.1f" x2="%.1f" y2="%.1f" stroke="%s" stroke-width="1" />'.
            '<line x1="%.1f" y1="%.1f" x2="%.1f" y2="%.1f" stroke="%s" stroke-width="1" />'.
            '<text x="%.1f" y="%.1f" text-anchor="middle" font-size="11" font-weight="600" fill="%s" transform="rotate(-90 %.1f %.1f)">%s</text>',
            $lineX,
            $y,
            $lineX,
            $yEnd,
            $color,
            $lineX - 4,
            $y,
            $lineX + 4,
            $y,
            $color,
            $lineX - 4,
            $yEnd,
            $lineX + 4,
            $yEnd,
            $color,
            $lineX - 6,
            $midY,
            $color,
            $lineX - 6,
            $midY,
            htmlspecialchars($label, ENT_QUOTES, 'UTF-8')
        );
    }

    private static function deckRect(array $c): string
    {
        return sprintf(
            '<rect x="%.1f" y="%.1f" width="%.1f" height="%.1f" fill="#f8fafc" stroke="#1e293b" stroke-width="1.5" />',
            $c['x0'],
            $c['y0'],
            $c['w'],
            $c['h']
        );
    }

    private static function dimensions(array $c): string
    {
        $runLabel = self::ftLabel($c['runFt']);
        $spanLabel = self::ftLabel($c['spanFt']);
        $midX = $c['x0'] + $c['w'] / 2;
        $midY = $c['y0'] + $c['h'] / 2;

        return sprintf(
            '<text x="%.1f" y="%.1f" text-anchor="middle" font-size="13" font-weight="600" fill="#334155">%s</text>'.
            '<text x="%.1f" y="%.1f" text-anchor="middle" font-size="13" font-weight="600" fill="#334155" transform="rotate(-90 %.1f %.1f)">%s</text>',
            $midX,
            $c['y0'] - 14,
            $runLabel,
            $c['x0'] - 16,
            $midY,
            $c['x0'] - 16,
            $midY,
            $spanLabel
        );
    }

    private static function caption(string $text): string
    {
        return sprintf(
            '<text x="%.1f" y="%.1f" text-anchor="middle" font-size="11" fill="#64748b">%s</text>',
            self::CANVAS_W / 2,
            self::CANVAS_H - 10,
            htmlspecialchars($text, ENT_QUOTES, 'UTF-8')
        );
    }

    private static function ftLabel(float $ft): string
    {
        return rtrim(rtrim(number_format($ft, 2), '0'), '.')."'";
    }

    private static function ftInLabel(float $ft): string
    {
        $whole = (int) floor($ft + 1e-9);
        $inches = (int) round(($ft - $whole) * 12);
        if ($inches >= 12) {
            $whole++;
            $inches = 0;
        }

        if ($inches === 0) {
            return $whole."'";
        }

        return $whole."'".$inches.'"';
    }

    private static function wrap(string $body): string
    {
        return sprintf(
            '<svg viewBox="0 0 %d %d" xmlns="http://www.w3.org/2000/svg" class="ld-diagram-svg" preserveAspectRatio="xMidYMid meet">%s</svg>',
            (int) self::CANVAS_W,
            (int) self::CANVAS_H,
            $body
        );
    }
}

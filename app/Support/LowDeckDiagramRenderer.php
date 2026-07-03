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

        foreach ($xs as $fx) {
            foreach ($ys as $fy) {
                $cx = $c['x0'] + (float) $fx * $c['scale'];
                $cy = $c['y0'] + (float) $fy * $c['scale'];
                $body .= sprintf(
                    '<rect x="%.1f" y="%.1f" width="11" height="11" rx="1.5" fill="#024768" transform="translate(-5.5,-5.5)" />',
                    $cx,
                    $cy
                );
            }
        }

        $body .= self::dimensions($c);
        $body .= self::caption(sprintf(
            '%d concrete deck blocks, %d x %d grid',
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

        // Joists: vertical lines spanning the deck height.
        foreach ($joistXs as $fx) {
            $x = $c['x0'] + (float) $fx * $c['scale'];
            $body .= sprintf(
                '<line x1="%.1f" y1="%.1f" x2="%.1f" y2="%.1f" stroke="#0f766e" stroke-width="2" />',
                $x,
                $c['y0'],
                $x,
                $c['y0'] + $c['h']
            );
        }

        // Beams: horizontal lines across the deck width.
        foreach ($beamYs as $fy) {
            $y = $c['y0'] + (float) $fy * $c['scale'];
            $body .= sprintf(
                '<line x1="%.1f" y1="%.1f" x2="%.1f" y2="%.1f" stroke="#b45309" stroke-width="3" />',
                $c['x0'],
                $y,
                $c['x0'] + $c['w'],
                $y
            );
        }

        $body .= self::dimensions($c);
        $body .= self::caption(sprintf(
            '%d joists at %d in. O.C., %d beam lines (%d interior)',
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

    private static function deckRect(array $c): string
    {
        return sprintf(
            '<rect x="%.1f" y="%.1f" width="%.1f" height="%.1f" fill="#f8fafc" stroke="#334155" stroke-width="1.5" />',
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
            '<text x="%.1f" y="%.1f" text-anchor="middle" font-size="13" fill="#334155">%s</text>'.
            '<text x="%.1f" y="%.1f" text-anchor="middle" font-size="13" fill="#334155" transform="rotate(-90 %.1f %.1f)">%s</text>',
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
            '<text x="%.1f" y="%.1f" text-anchor="middle" font-size="12" fill="#64748b">%s</text>',
            self::CANVAS_W / 2,
            self::CANVAS_H - 10,
            htmlspecialchars($text, ENT_QUOTES, 'UTF-8')
        );
    }

    private static function ftLabel(float $ft): string
    {
        return rtrim(rtrim(number_format($ft, 2), '0'), '.')."'";
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

<?php

namespace App\Services\CutOptimizer;

class SvgVisualizer
{
    /**
     * @return array{svg: string, stats: array<string, mixed>, legend: list<array<string, mixed>>}
     */
    public function describe(GuillotineSheet $sheet): array
    {
        return [
            'svg' => $this->renderSheet($sheet),
            'legend' => $this->legend($sheet),
            'stats' => [
                'id' => $sheet->id,
                'label' => $sheet->label !== '' ? $sheet->label : $this->inchPair($sheet->width, $sheet->length),
                'width' => round($sheet->width, 3),
                'length' => round($sheet->length, 3),
                'used_area' => round($sheet->usedArea(), 1),
                'sheet_area' => round($sheet->width * $sheet->length, 1),
                'waste_pct' => $sheet->wastePercentage(),
                'used_pct' => round(100 - $sheet->wastePercentage(), 1),
                'part_count' => count($sheet->placedParts),
                'total_cuts' => count($sheet->cutLog),
                'total_cut_length' => $sheet->totalCutLength(),
            ],
        ];
    }

    public function renderSheet(GuillotineSheet $sheet): string
    {
        $padL = 18.0;
        $padR = 8.0;
        $padT = 6.0;
        $padB = 14.0;
        $vbW = $sheet->width + $padL + $padR;
        $vbH = $sheet->length + $padT + $padB;
        $flipY = $sheet->length;
        $clipPrefix = 'mw-clip-'.preg_replace('/[^A-Za-z0-9_-]/', '', $sheet->id);

        $svg = [];
        $svg[] = '<svg class="mw-cut-svg" viewBox="0 0 '.$this->n($vbW).' '.$this->n($vbH).'" xmlns="http://www.w3.org/2000/svg" role="img" aria-label="Cut plan '.$this->esc($sheet->label).'">';
        $svg[] = '<rect class="mw-cut-bg" x="0" y="0" width="'.$this->n($vbW).'" height="'.$this->n($vbH).'" />';
        $svg[] = '<defs>';
        $i = 0;
        foreach ($sheet->placedParts as $p) {
            $i++;
            $y = $flipY - $p->y - $p->height;
            $svg[] = '<clipPath id="'.$clipPrefix.'-'.$i.'"><rect x="'.$this->n($p->x).'" y="'.$this->n($y).'" width="'.$this->n($p->width).'" height="'.$this->n($p->height).'" /></clipPath>';
        }
        $svg[] = '</defs>';

        $svg[] = '<g transform="translate('.$this->n($padL).' '.$this->n($padT).')">';
        $svg[] = '<rect class="mw-cut-stock" x="0" y="0" width="'.$this->n($sheet->width).'" height="'.$this->n($sheet->length).'" />';

        foreach ($sheet->freeRectangles as $offcut) {
            if (min($offcut->width, $offcut->height) < 2.5) {
                continue;
            }
            $y = $flipY - $offcut->y - $offcut->height;
            $svg[] = '<rect class="mw-cut-offcut" x="'.$this->n($offcut->x).'" y="'.$this->n($y).'" width="'.$this->n($offcut->width).'" height="'.$this->n($offcut->height).'" />';
            if ($offcut->width >= 10 && $offcut->height >= 8) {
                $svg[] = $this->label(
                    $offcut->x + ($offcut->width / 2),
                    $y + ($offcut->height / 2),
                    $this->inchPair($offcut->width, $offcut->height),
                    'mw-cut-offcut-label',
                    min(2.2, min($offcut->width, $offcut->height) / 8)
                );
            }
        }

        $i = 0;
        foreach ($sheet->placedParts as $p) {
            $i++;
            $svg[] = $this->partGroup($p, $flipY, $i, $clipPrefix.'-'.$i);
        }

        $svg[] = $this->dimLine(0, $sheet->length + 7, $sheet->width, $sheet->length + 7, $this->inch($sheet->width), false);
        $svg[] = $this->dimLine(-7, $sheet->length, -7, 0, $this->inch($sheet->length), true);
        $svg[] = '</g>';
        $svg[] = '</svg>';

        return implode("\n", $svg);
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function humanCuts(GuillotineSheet $sheet): array
    {
        $steps = [];
        $steps[] = [
            'step' => 1,
            'instruction' => 'Start with '.$this->inchPair($sheet->width, $sheet->length)
                .($sheet->label !== '' ? ' ('.$sheet->label.')' : '').'.',
        ];
        $n = 2;
        foreach ($sheet->cutLog as $cut) {
            $type = ($cut['type'] ?? '') === 'rip' ? 'Rip' : 'Crosscut';
            $pos = $this->inch((float) ($cut['position'] ?? 0));
            $surplus = $cut['surplus'] ?? null;
            $line = $type.' at '.$pos.' to release '.(string) ($cut['part_label'] ?? 'part').'.';
            if (is_array($surplus) && count($surplus) >= 2) {
                $line .= ' Surplus '.$this->inchPair((float) $surplus[0], (float) $surplus[1]).'.';
            }
            $steps[] = [
                'step' => $n++,
                'type' => $cut['type'] ?? '',
                'position' => $cut['position'] ?? 0,
                'instruction' => $line,
            ];
        }

        return $steps;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function legend(GuillotineSheet $sheet): array
    {
        $rows = [];
        $n = 0;
        foreach ($sheet->placedParts as $p) {
            $n++;
            $rows[] = [
                'n' => $n,
                'label' => $p->part->label,
                'size' => $this->inchPair($p->width, $p->height),
                'rotated' => $p->rotated,
            ];
        }

        return $rows;
    }

    private function partGroup(PlacedPart $p, float $flipY, int $n, string $clipId): string
    {
        $y = $flipY - $p->y - $p->height;
        $cx = $p->x + ($p->width / 2);
        $cy = $y + ($p->height / 2);
        $minD = min($p->width, $p->height);
        $maxD = max($p->width, $p->height);
        $alongH = $p->width >= $p->height;
        $along = $alongH ? $p->width : $p->height;
        $across = $alongH ? $p->height : $p->width;
        $dim = $this->inchPair($p->width, $p->height);
        $hover = $p->part->label.' — '.$dim.($p->rotated ? ' (rotated)' : '');

        $out = [];
        $out[] = '<g class="mw-cut-part">';
        $out[] = '<title>'.$this->esc($hover).'</title>';
        $out[] = '<rect class="mw-cut-part-fill" x="'.$this->n($p->x).'" y="'.$this->n($y).'" width="'.$this->n($p->width).'" height="'.$this->n($p->height).'" />';
        $out[] = '<g clip-path="url(#'.$clipId.')">';

        if ($minD >= 7.5) {
            $nameSize = $this->fitFont($p->part->label, $p->width * 0.88, $minD * 0.28, 2.4);
            $dimSize = $this->fitFont($dim, $p->width * 0.88, $minD * 0.22, 2.0);
            $out[] = $this->label($cx, $cy - ($minD * 0.14), $p->part->label, 'mw-cut-part-label', $nameSize);
            $out[] = $this->label($cx, $cy + ($minD * 0.16), $dim, 'mw-cut-part-dim', $dimSize);
        } elseif ($maxD >= 5.5 && $minD >= 2.4) {
            $compact = $n.' '.$this->plainInch($p->width).'×'.$this->plainInch($p->height);
            $size = $this->fitFont($compact, $along * 0.92, $across * 0.7, 1.7);
            if ($size < 0.85) {
                $compact = (string) $n;
                $size = min(1.3, $across * 0.62);
            }
            $out[] = $this->label($cx, $cy, $compact, 'mw-cut-part-dim', $size, ! $alongH);
        } else {
            $size = min(1.35, $minD * 0.7);
            $out[] = $this->label($cx, $cy, (string) $n, 'mw-cut-part-label', $size);
        }

        $out[] = '</g>';
        $out[] = '</g>';

        return implode("\n", $out);
    }

    private function dimLine(float $x1, float $y1, float $x2, float $y2, string $text, bool $vertical): string
    {
        $mx = ($x1 + $x2) / 2;
        $my = ($y1 + $y2) / 2;
        $label = $this->label($mx + ($vertical ? -4.5 : 0), $my + ($vertical ? 0 : 4.2), $text, 'mw-cut-dim', 2.1);

        return '<line class="mw-cut-dim-line" x1="'.$this->n($x1).'" y1="'.$this->n($y1).'" x2="'.$this->n($x2).'" y2="'.$this->n($y2).'" />'.$label;
    }

    private function label(float $x, float $y, string $text, string $class, float $size, bool $vertical = false): string
    {
        $rot = $vertical ? ' transform="rotate(-90 '.$this->n($x).' '.$this->n($y).')"' : '';

        return '<text class="'.$class.'" x="'.$this->n($x).'" y="'.$this->n($y).'" font-size="'.$this->n($size).'" text-anchor="middle" dominant-baseline="middle"'.$rot.'>'.$this->esc($text).'</text>';
    }

    private function fitFont(string $text, float $along, float $across, float $preferred): float
    {
        $len = max(1, strlen($text));
        $byAlong = $along / ($len * 0.56);
        $byAcross = $across * 0.85;

        return max(0.7, min($preferred, $byAlong, $byAcross));
    }

    private function plainInch(float $n): string
    {
        $rounded = round($n, 2);

        return rtrim(rtrim(number_format($rounded, 2, '.', ''), '0'), '.');
    }

    private function inch(float $n): string
    {
        return $this->plainInch($n).'"';
    }

    private function inchPair(float $a, float $b): string
    {
        return $this->inch($a).' × '.$this->inch($b);
    }

    private function n(float $n): string
    {
        return rtrim(rtrim(number_format($n, 3, '.', ''), '0'), '.');
    }

    private function esc(string $s): string
    {
        return htmlspecialchars($s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}

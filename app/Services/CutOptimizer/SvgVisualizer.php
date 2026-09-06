<?php

namespace App\Services\CutOptimizer;

class SvgVisualizer
{
    /**
     * @return array{svg: string, cuts: list<array<string, mixed>>, stats: array<string, mixed>}
     */
    public function describe(GuillotineSheet $sheet): array
    {
        return [
            'svg' => $this->renderSheet($sheet),
            'cuts' => $this->humanCuts($sheet),
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
        $pad = 28.0;
        $vbW = $sheet->width + ($pad * 2);
        $vbH = $sheet->length + ($pad * 2);
        $flipY = $sheet->length;

        $svg = [];
        $svg[] = '<svg class="mw-cut-svg" viewBox="0 0 '.$this->n($vbW).' '.$this->n($vbH).'" xmlns="http://www.w3.org/2000/svg" role="img" aria-label="Cut plan '.$this->esc($sheet->label).'">';
        $svg[] = '<rect class="mw-cut-bg" x="0" y="0" width="'.$this->n($vbW).'" height="'.$this->n($vbH).'" />';

        $ox = $pad;
        $oy = $pad;

        $svg[] = '<g transform="translate('.$this->n($ox).' '.$this->n($oy).')">';
        $svg[] = '<rect class="mw-cut-stock" x="0" y="0" width="'.$this->n($sheet->width).'" height="'.$this->n($sheet->length).'" />';

        foreach ($sheet->freeRectangles as $offcut) {
            $y = $flipY - $offcut->y - $offcut->height;
            $svg[] = '<rect class="mw-cut-offcut" x="'.$this->n($offcut->x).'" y="'.$this->n($y).'" width="'.$this->n($offcut->width).'" height="'.$this->n($offcut->height).'" />';
            if ($offcut->width >= 6 && $offcut->height >= 6) {
                $svg[] = $this->label(
                    $offcut->x + ($offcut->width / 2),
                    $y + ($offcut->height / 2),
                    $this->inchPair($offcut->width, $offcut->height),
                    'mw-cut-offcut-label',
                    2.6
                );
            }
        }

        $i = 0;
        foreach ($sheet->placedParts as $p) {
            $i++;
            $y = $flipY - $p->y - $p->height;
            $svg[] = '<g class="mw-cut-part">';
            $svg[] = '<rect class="mw-cut-part-fill" x="'.$this->n($p->x).'" y="'.$this->n($y).'" width="'.$this->n($p->width).'" height="'.$this->n($p->height).'" />';
            $title = $p->part->label.($p->rotated ? ' (rotated)' : '');
            $svg[] = $this->label(
                $p->x + ($p->width / 2),
                $y + ($p->height / 2) - 2.2,
                $title,
                'mw-cut-part-label',
                min(4.2, max(2.4, min($p->width, $p->height) / 8))
            );
            $svg[] = $this->label(
                $p->x + ($p->width / 2),
                $y + ($p->height / 2) + 2.4,
                $this->inchPair($p->width, $p->height),
                'mw-cut-part-dim',
                min(3.6, max(2.2, min($p->width, $p->height) / 10))
            );
            $svg[] = '</g>';
        }

        $svg[] = $this->dimLine(0, $sheet->length + 8, $sheet->width, $sheet->length + 8, $this->inch($sheet->width), false);
        $svg[] = $this->dimLine(-10, $sheet->length, -10, 0, $this->inch($sheet->length), true);
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

    private function dimLine(float $x1, float $y1, float $x2, float $y2, string $text, bool $vertical): string
    {
        $mx = ($x1 + $x2) / 2;
        $my = ($y1 + $y2) / 2;
        $label = $this->label($mx + ($vertical ? -6 : 0), $my + ($vertical ? 0 : 5), $text, 'mw-cut-dim', 3.2);

        return '<line class="mw-cut-dim-line" x1="'.$this->n($x1).'" y1="'.$this->n($y1).'" x2="'.$this->n($x2).'" y2="'.$this->n($y2).'" />'.$label;
    }

    private function label(float $x, float $y, string $text, string $class, float $size): string
    {
        return '<text class="'.$class.'" x="'.$this->n($x).'" y="'.$this->n($y).'" font-size="'.$this->n($size).'" text-anchor="middle" dominant-baseline="middle">'.$this->esc($text).'</text>';
    }

    private function inch(float $n): string
    {
        $rounded = round($n, 2);
        $str = rtrim(rtrim(number_format($rounded, 2, '.', ''), '0'), '.');

        return $str.'"';
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

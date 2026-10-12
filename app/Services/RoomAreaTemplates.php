<?php

namespace App\Services;

/**
 * Closed-polygon templates and alcove insertion in feet (or any consistent unit).
 * Keep in sync with applyRoomTemplate() / insertAlcove() in public/js/admin-utilities-room-area.js.
 */
class RoomAreaTemplates
{
    /**
     * @return list<array{x: float, y: float}>
     */
    public function rectangle(float $width, float $length): array
    {
        $w = $this->positive($width);
        $l = $this->positive($length);

        return [
            ['x' => 0.0, 'y' => 0.0],
            ['x' => $w, 'y' => 0.0],
            ['x' => $w, 'y' => $l],
            ['x' => 0.0, 'y' => $l],
        ];
    }

    /**
     * L-shape: outer W×H with a return of returnW×returnH on the bottom-right.
     * Default 16×12 return 6×4 → floor 152, peri 56 (see Batch 0 math fixture).
     *
     * @return list<array{x: float, y: float}>
     */
    public function lShape(float $outerW, float $outerH, float $returnW, float $returnH): array
    {
        $w = $this->positive($outerW);
        $h = $this->positive($outerH);
        $rw = $this->positive($returnW);
        $rh = $this->positive($returnH);
        if ($rw >= $w || $rh >= $h) {
            return $this->rectangle($w, $h);
        }

        return [
            ['x' => 0.0, 'y' => 0.0],
            ['x' => $w, 'y' => 0.0],
            ['x' => $w, 'y' => $h],
            ['x' => $w - $rw, 'y' => $h],
            ['x' => $w - $rw, 'y' => $h - $rh],
            ['x' => 0.0, 'y' => $h - $rh],
        ];
    }

    /**
     * U-shape: outer W×H with a centered opening of openingW×openingD cut from the bottom.
     *
     * @return list<array{x: float, y: float}>
     */
    public function uShape(float $outerW, float $outerH, float $openingW, float $openingD): array
    {
        $w = $this->positive($outerW);
        $h = $this->positive($outerH);
        $ow = $this->positive($openingW);
        $od = $this->positive($openingD);
        if ($ow >= $w || $od >= $h) {
            return $this->rectangle($w, $h);
        }
        $arm = ($w - $ow) / 2.0;

        return [
            ['x' => 0.0, 'y' => 0.0],
            ['x' => $w, 'y' => 0.0],
            ['x' => $w, 'y' => $h],
            ['x' => $w - $arm, 'y' => $h],
            ['x' => $w - $arm, 'y' => $h - $od],
            ['x' => $arm, 'y' => $h - $od],
            ['x' => $arm, 'y' => $h],
            ['x' => 0.0, 'y' => $h],
        ];
    }

    /**
     * T-shape: a bar of barW×barH with a centered stem of stemW×stemH hanging down.
     *
     * @return list<array{x: float, y: float}>
     */
    public function tShape(float $barW, float $barH, float $stemW, float $stemH): array
    {
        $bw = $this->positive($barW);
        $bh = $this->positive($barH);
        $sw = $this->positive($stemW);
        $sh = $this->positive($stemH);
        if ($sw >= $bw) {
            return $this->rectangle($bw, $bh + $sh);
        }
        $left = ($bw - $sw) / 2.0;

        return [
            ['x' => 0.0, 'y' => 0.0],
            ['x' => $bw, 'y' => 0.0],
            ['x' => $bw, 'y' => $bh],
            ['x' => $left + $sw, 'y' => $bh],
            ['x' => $left + $sw, 'y' => $bh + $sh],
            ['x' => $left, 'y' => $bh + $sh],
            ['x' => $left, 'y' => $bh],
            ['x' => 0.0, 'y' => $bh],
        ];
    }

    /**
     * Insert a rectangular alcove / bump-out into wall $wallIndex.
     *
     * @param  list<array{x: float, y: float}>  $vertices
     * @return list<array{x: float, y: float}>
     */
    public function insertAlcove(array $vertices, int $wallIndex, float $width, float $depth, bool $closed = true): array
    {
        $n = count($vertices);
        $wallCount = $closed && $n >= 3 ? $n : max(0, $n - 1);
        if ($n < 2 || $wallCount < 1) {
            return $vertices;
        }
        $idx = $wallIndex % $wallCount;
        if ($idx < 0) {
            $idx += $wallCount;
        }
        $a = $vertices[$idx];
        $b = $vertices[$closed && $n >= 3 ? ($idx + 1) % $n : $idx + 1];
        $dx = (float) $b['x'] - (float) $a['x'];
        $dy = (float) $b['y'] - (float) $a['y'];
        $len = hypot($dx, $dy);
        if ($len < 1e-6) {
            return $vertices;
        }

        $width = min($this->positive($width), $len * 0.9);
        $depth = $this->positive($depth);
        $ux = $dx / $len;
        $uy = $dy / $len;
        $leftNx = -$uy;
        $leftNy = $ux;
        $nx = $leftNx;
        $ny = $leftNy;
        if ($closed && $n >= 3 && $this->signedArea2($vertices) > 0) {
            $nx = -$leftNx;
            $ny = -$leftNy;
        }

        $inset = ($len - $width) / 2.0;
        $p0 = ['x' => (float) $a['x'] + $ux * $inset, 'y' => (float) $a['y'] + $uy * $inset];
        $p1 = ['x' => (float) $a['x'] + $ux * ($inset + $width), 'y' => (float) $a['y'] + $uy * ($inset + $width)];
        $p0o = ['x' => $p0['x'] + $nx * $depth, 'y' => $p0['y'] + $ny * $depth];
        $p1o = ['x' => $p1['x'] + $nx * $depth, 'y' => $p1['y'] + $ny * $depth];

        $insert = [$p0, $p0o, $p1o, $p1];
        $endIdx = $closed && $n >= 3 ? ($idx + 1) % $n : $idx + 1;
        $out = [];
        for ($i = 0; $i < $n; $i++) {
            $out[] = ['x' => (float) $vertices[$i]['x'], 'y' => (float) $vertices[$i]['y']];
            if ($i === $idx) {
                foreach ($insert as $pt) {
                    $out[] = $pt;
                }
            }
        }
        if (! $closed && $endIdx === $n) {
            // open polyline last wall already appended inserts after $idx
        }

        return $this->dedupeClose($out);
    }

    /**
     * L / T from overall W×L. L uses a 6×4 return when it fits (152 sq ft at 16×12).
     * T uses typed L as overall height (bar + stem).
     *
     * @return list<array{x: float, y: float}>
     */
    public function fromKind(string $kind, float $width, float $length): array
    {
        $kind = strtolower($kind);
        if ($kind === 'l') {
            return $this->lFromOverall($width > 0 ? $width : 16.0, $length > 0 ? $length : 12.0);
        }
        if ($kind === 'u') {
            $w = $width > 0 ? $width : 16.0;
            $h = $length > 0 ? $length : 12.0;

            return $this->uShape($w, $h, min(8.0, $w / 2.0), min(8.0, $h * 0.65));
        }
        if ($kind === 't') {
            return $this->tFromOverall($width > 0 ? $width : 16.0, $length > 0 ? $length : 12.0);
        }

        return $this->rectangle($width > 0 ? $width : 12.0, $length > 0 ? $length : 10.0);
    }

    /**
     * @return list<array{x: float, y: float}>
     */
    public function lFromOverall(float $width, float $length): array
    {
        $rw = 6.0;
        $rh = 4.0;
        if ($rw >= $width) {
            $rw = $width / 3.0;
        }
        if ($rh >= $length) {
            $rh = $length / 3.0;
        }

        return $this->lShape($width, $length, $rw, $rh);
    }

    /**
     * @return list<array{x: float, y: float}>
     */
    public function tFromOverall(float $width, float $length): array
    {
        $barH = min(4.0, $length / 2.0);
        $stemH = $length - $barH;
        if ($stemH < 1.0) {
            $barH = $length / 2.0;
            $stemH = $length / 2.0;
        }

        return $this->tShape($width, $barH, min(6.0, $width / 2.0), $stemH);
    }

    /**
     * @param  list<array{x: float, y: float}>  $vertices
     */
    public function signedArea2(array $vertices): float
    {
        $s = 0.0;
        $n = count($vertices);
        for ($i = 0; $i < $n; $i++) {
            $j = ($i + 1) % $n;
            $s += ((float) $vertices[$i]['x'] * (float) $vertices[$j]['y'])
                - ((float) $vertices[$j]['x'] * (float) $vertices[$i]['y']);
        }

        return $s;
    }

    /**
     * @param  list<array{x: float, y: float}>  $vertices
     * @return list<array{x: float, y: float}>
     */
    private function dedupeClose(array $vertices): array
    {
        $out = [];
        foreach ($vertices as $v) {
            if ($out) {
                $prev = $out[count($out) - 1];
                if (hypot($v['x'] - $prev['x'], $v['y'] - $prev['y']) < 1e-6) {
                    continue;
                }
            }
            $out[] = $v;
        }
        if (count($out) > 1) {
            $first = $out[0];
            $last = $out[count($out) - 1];
            if (hypot($first['x'] - $last['x'], $first['y'] - $last['y']) < 1e-6) {
                array_pop($out);
            }
        }

        return $out;
    }

    private function positive(float $n): float
    {
        return $n > 0 ? $n : 1.0;
    }
}

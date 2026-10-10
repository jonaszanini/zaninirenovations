<?php

namespace App\Services;

use App\Models\RoomAreaRoom;

class RoomAreaPlanSvg
{
    public function formatFeetInches(float $feet): string
    {
        $sign = $feet < 0 ? '-' : '';
        $abs = abs($feet);
        $whole = (int) floor($abs + 1e-9);
        $inches = (int) round(($abs - $whole) * 12);
        if ($inches >= 12) {
            $whole++;
            $inches = 0;
        }

        return $sign.$whole.'\'-'.$inches.'"';
    }

    /**
     * @return list<string>
     */
    public function edgeLabels(RoomAreaRoom $room): array
    {
        $scale = $room->resolvedScalePixelsPerFoot();
        if ($scale <= 0) {
            return [];
        }
        $labels = [];
        foreach ($this->polygons($room) as $polygon) {
            foreach ($this->edges($polygon, $scale) as $edge) {
                $labels[] = $this->formatFeetInches($edge['length_ft']);
            }
        }

        return $labels;
    }

    public function forRoom(RoomAreaRoom $room, int $width = 480, int $height = 260): string
    {
        $polygons = $this->polygons($room);
        if ($polygons === []) {
            return '';
        }
        $scale = $room->resolvedScalePixelsPerFoot();
        $minX = PHP_FLOAT_MAX;
        $minY = PHP_FLOAT_MAX;
        $maxX = -PHP_FLOAT_MAX;
        $maxY = -PHP_FLOAT_MAX;
        foreach ($polygons as $polygon) {
            foreach ($polygon as $vertex) {
                $minX = min($minX, $vertex['x']);
                $minY = min($minY, $vertex['y']);
                $maxX = max($maxX, $vertex['x']);
                $maxY = max($maxY, $vertex['y']);
            }
        }
        $spanX = max(1.0, $maxX - $minX);
        $spanY = max(1.0, $maxY - $minY);
        $pad = 28.0;
        $innerW = max(40.0, $width - ($pad * 2));
        $innerH = max(40.0, $height - ($pad * 2));
        $fit = min($innerW / $spanX, $innerH / $spanY);
        $drawW = $spanX * $fit;
        $drawH = $spanY * $fit;
        $ox = ($width - $drawW) / 2;
        $oy = ($height - $drawH) / 2;

        $map = function (array $vertex) use ($minX, $minY, $fit, $ox, $oy): array {
            return [
                'x' => $ox + (($vertex['x'] - $minX) * $fit),
                'y' => $oy + (($vertex['y'] - $minY) * $fit),
            ];
        };

        $parts = [
            '<svg xmlns="http://www.w3.org/2000/svg" width="'.$width.'" height="'.$height.'" viewBox="0 0 '.$width.' '.$height.'" role="img">',
            '<rect width="100%" height="100%" fill="#ffffff"/>',
        ];
        foreach ($polygons as $polygon) {
            $points = [];
            foreach ($polygon as $vertex) {
                $p = $map($vertex);
                $points[] = $this->n($p['x']).','.$this->n($p['y']);
            }
            $parts[] = '<polygon points="'.implode(' ', $points).'" fill="#e8f1f6" stroke="#024768" stroke-width="1.6"/>';
            if ($scale <= 0) {
                continue;
            }
            foreach ($this->edges($polygon, $scale) as $edge) {
                $a = $map($edge['a']);
                $b = $map($edge['b']);
                $mx = ($a['x'] + $b['x']) / 2;
                $my = ($a['y'] + $b['y']) / 2;
                $dx = $b['x'] - $a['x'];
                $dy = $b['y'] - $a['y'];
                $len = hypot($dx, $dy);
                if ($len < 8) {
                    continue;
                }
                $nx = -$dy / $len;
                $ny = $dx / $len;
                $lx = $mx + ($nx * 12);
                $ly = $my + ($ny * 12);
                $label = $this->formatFeetInches($edge['length_ft']);
                $parts[] = '<text x="'.$this->n($lx).'" y="'.$this->n($ly).'" text-anchor="middle" dominant-baseline="middle" fill="#024768" font-family="DejaVu Sans, sans-serif" font-size="10">'.$label.'</text>';
            }
        }
        $parts[] = '</svg>';

        return implode('', $parts);
    }

    /**
     * @return list<list<array{x: float, y: float}>>
     */
    private function polygons(RoomAreaRoom $room): array
    {
        $canvas = $room->canvasData();
        $polygons = [];
        foreach ($canvas['groups'] ?? [] as $group) {
            if (! is_array($group)) {
                continue;
            }
            foreach ($group['polygons'] ?? [] as $polygon) {
                if (! is_array($polygon) || empty($polygon['vertices']) || ! is_array($polygon['vertices'])) {
                    continue;
                }
                $verts = [];
                foreach ($polygon['vertices'] as $vertex) {
                    if (! is_array($vertex) || ! isset($vertex['x'], $vertex['y'])) {
                        continue;
                    }
                    $verts[] = ['x' => (float) $vertex['x'], 'y' => (float) $vertex['y']];
                }
                if (count($verts) >= 3) {
                    $polygons[] = $verts;
                }
            }
        }

        return $polygons;
    }

    /**
     * @param  list<array{x: float, y: float}>  $polygon
     * @return list<array{a: array{x: float, y: float}, b: array{x: float, y: float}, length_ft: float}>
     */
    private function edges(array $polygon, float $scale): array
    {
        $count = count($polygon);
        if ($count < 2) {
            return [];
        }
        $edges = [];
        for ($i = 0; $i < $count; $i++) {
            $a = $polygon[$i];
            $b = $polygon[($i + 1) % $count];
            $px = hypot($b['x'] - $a['x'], $b['y'] - $a['y']);
            $edges[] = [
                'a' => $a,
                'b' => $b,
                'length_ft' => $scale > 0 ? $px / $scale : 0.0,
            ];
        }

        return $edges;
    }

    private function n(float $value): string
    {
        return rtrim(rtrim(number_format($value, 2, '.', ''), '0'), '.');
    }
}

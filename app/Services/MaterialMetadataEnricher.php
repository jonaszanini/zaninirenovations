<?php

namespace App\Services;

use App\Models\Material;

/**
 * Rule-based suggestions for material short_name, category_id, and unit.
 * Returns null for any field when not confident enough to set.
 */
class MaterialMetadataEnricher
{
    /** @var array<string, int> */
    private array $categories = [];

    public function __construct()
    {
        $this->categories = [
            'lumber' => 1,
            'hardware' => 2,
            'fencing' => 3,
            'decking' => 4,
            'concrete' => 6,
            'fasteners' => 7,
            'finishing' => 8,
            'dimensional' => 12,
        ];
    }

    /**
     * @return array{short_name: ?string, category_id: ?int, unit: ?string}
     */
    public function suggest(Material $material): array
    {
        $name = trim((string) $material->name);
        $lower = strtolower($name);

        return [
            'short_name' => $this->suggestShortName($name, $lower),
            'category_id' => $this->suggestCategoryId($name, $lower),
            'unit' => $this->suggestUnit($name, $lower),
        ];
    }

    private function suggestShortName(string $name, string $lower): ?string
    {
        if ($short = $this->matchFenceBoard($name)) {
            return $short;
        }
        if ($short = $this->matchWoodPost($name)) {
            return $short;
        }
        if ($short = $this->matchDecking($name, $lower)) {
            return $short;
        }
        if ($short = $this->matchPremiumWood($name)) {
            return $short;
        }
        if ($short = $this->matchSpfLumber($name)) {
            return $short;
        }
        if ($short = $this->matchSheetGoods($name, $lower)) {
            return $short;
        }
        if ($short = $this->matchDrywall($name, $lower)) {
            return $short;
        }
        if ($short = $this->matchHardware($name, $lower)) {
            return $short;
        }
        if ($short = $this->matchConcrete($name, $lower)) {
            return $short;
        }
        if ($short = $this->matchFasteners($name, $lower)) {
            return $short;
        }
        if ($short = $this->matchMiscWood($name, $lower)) {
            return $short;
        }

        return null;
    }

    private function suggestCategoryId(string $name, string $lower): ?int
    {
        if (str_contains($lower, 'fence board')
            || str_contains($lower, 'fence rail bracket')
            || str_contains($lower, 'fence post cap')
            || (str_contains($lower, 'gate') && (str_contains($lower, 'fence') || str_contains($lower, 'bracket') || str_contains($lower, 'latch')))) {
            return $this->categories['fencing'];
        }

        if (str_contains($lower, 'decking')) {
            return $this->categories['decking'];
        }

        if (str_contains($lower, 'concrete mix')
            || str_contains($lower, 'rapid post')
            || (str_contains($lower, 'cement') && str_contains($lower, 'kg'))) {
            return $this->categories['concrete'];
        }

        if (str_contains($lower, 'drywall')
            || str_contains($lower, 'spackling')
            || str_contains($lower, 'plaster of paris')
            || str_contains($lower, 'joint compound')
            || str_contains($lower, 'patching compound')
            || str_contains($lower, 'patch pro compound')) {
            return $this->categories['finishing'];
        }

        if (preg_match('/\b(nail|nails)\b/', $lower) && ! str_contains($lower, 'joist')) {
            return $this->categories['fasteners'];
        }

        if (str_contains($lower, 'joist hanger')
            || preg_match('/rafter\s+u\s+hanger/i', $name)
            || str_contains($lower, 'rafter hanger')
            || str_contains($lower, 'jack hanger')
            || str_contains($lower, 'post base')
            || str_contains($lower, 'post cap')
            || str_contains($lower, 'carport saddle')
            || str_contains($lower, 'retrofit post base')) {
            return $this->categories['hardware'];
        }

        if (preg_match('/spf\b/i', $name) && preg_match('/\blumber\b/i', $name)) {
            return $this->categories['dimensional'];
        }

        if (preg_match('/\bpremium wood\b/i', $name)
            || str_contains($lower, 'cedar')
            || str_contains($lower, 'plywood')
            || str_contains($lower, 'oriented strand')
            || str_contains($lower, ' osb')
            || str_contains($lower, 'mdf')
            || str_contains($lower, 'hardboard')
            || str_contains($lower, 'melamine')
            || (preg_match('/\b4x8\b/i', $name) && str_contains($lower, 'fir'))
            || str_contains($lower, 'stringer')
            || str_contains($lower, 'nailing strip')
            || str_contains($lower, 'mini tie')) {
            return $this->categories['lumber'];
        }

        return null;
    }

    private function suggestUnit(string $name, string $lower): ?string
    {
        if (preg_match('/\b(\d+)\s*kg\b/i', $name)) {
            return 'bag';
        }

        if (preg_match('/\b(\d+(?:\.\d+)?)\s*l\s+(?:pail|carton)\b/i', $name)
            || preg_match('/\b\d+(?:\.\d+)?\s*l\s+pail\b/i', $lower)) {
            return 'pail';
        }

        if (preg_match('/\b\d+(?:\.\d+)?\s*l\s+carton\b/i', $lower)
            || preg_match('/\bcarton\b/i', $lower)) {
            return 'box';
        }

        if (preg_match('/\bbag\b/i', $name)) {
            return 'bag';
        }

        if (preg_match('/\bpail\b/i', $name)) {
            return 'pail';
        }

        if (preg_match('/\bplywood\b|\bosb\b|oriented strand|hardboard|melamine|\bmdf\b/i', $name)) {
            return 'sheet';
        }

        if (preg_match('/\b4x8\b/i', $name) && preg_match('/\b(fir|plywood|sanded|osb)\b/i', $name)) {
            return 'sheet';
        }

        if (preg_match('/\b4\s*ft.*\b8\s*ft|\b4\s*[\'x]\s*8\b|\b4x8\b/i', $name)
            && (str_contains($lower, 'plywood') || str_contains($lower, 'strand') || str_contains($lower, 'hardboard') || str_contains($lower, 'melamine') || str_contains($lower, 'mdf'))) {
            return 'sheet';
        }

        if (preg_match('/\b(nail|nails)\b/i', $name) && preg_match('/\b(box|pack|count)\b/i', $lower)) {
            return 'box';
        }

        if (str_contains($lower, '2 set') || preg_match('/\b2\s*set\b/i', $lower)) {
            return 'set';
        }

        if (preg_match('/\bpremium wood\b|\bspf\b.*\blumber\b|\bcedar\b/i', $name)
            || str_contains($lower, 'mini tie')
            || str_contains($lower, 'nailing strip')
            || str_contains($lower, 'stair stringer')
            || str_contains($lower, 'joist hanger')
            || preg_match('/rafter\s+u\s+hanger/i', $name)
            || str_contains($lower, 'rafter hanger')
            || str_contains($lower, 'jack hanger')
            || str_contains($lower, 'post base')
            || str_contains($lower, 'post cap')
            || str_contains($lower, 'carport saddle')
            || str_contains($lower, 'fence bracket')
            || str_contains($lower, 'fence rail bracket')
            || str_contains($lower, 'gate corner')) {
            return 'each';
        }

        return null;
    }

    private function matchFenceBoard(string $name): ?string
    {
        if (preg_match('/\d+\s*x\s*\d+\s*x\s*(\d+)\s*\'?\s*Premium Wood Fence Board/i', $name, $m)) {
            return $m[1] . "' Fence Board";
        }

        return null;
    }

    private function matchWoodPost(string $name): ?string
    {
        if (preg_match('/(\d+)\s*x\s*(\d+)\s*x\s*(\d+)\s*\'?\s*Premium Wood Post/i', $name, $m)) {
            return $m[1] . 'x' . $m[2] . 'x' . $m[3] . ' Post';
        }

        if (preg_match('/(\d+)\s*in\.?\s*x\s*(\d+)\s*in\.?\s*x\s*(\d+)\s*ft\.?\s*Premium Knotty Cedar Post/i', $name, $m)) {
            return $m[1] . 'x' . $m[2] . 'x' . $m[3] . ' Cedar Post';
        }

        return null;
    }

    private function matchDecking(string $name, string $lower): ?string
    {
        if (preg_match('/5\/4\s*x\s*6\s*x\s*(\d+)\s*\'?\s*Premium Wood Decking/i', $name, $m)) {
            return '5/4x6x' . $m[1] . ' Decking';
        }

        if (preg_match('/5\/4\s*in\.?\s*x\s*6\s*in\.?\s*x\s*(\d+)\s*ft\.?\s*Premium\s+Knotty Cedar Decking/i', $name, $m)) {
            return '5/4x6x' . $m[1] . ' Cedar Decking';
        }

        return null;
    }

    private function matchPremiumWood(string $name): ?string
    {
        if (preg_match('/(\d+)\s*x\s*(\d+)\s*x\s*(\d+)\s*\'?\s*Premium Wood(?!\s+(?:Post|Fence Board|Decking))/i', $name, $m)) {
            return $m[1] . 'x' . $m[2] . 'x' . $m[3] . ' PT';
        }

        return null;
    }

    private function matchSpfLumber(string $name): ?string
    {
        if (preg_match('/(\d+)-inch\s*x\s*(\d+)(?:-inch| inch)\s*x\s*(\d+)(?:-foot|-ft|-feet)?\s*SPF/i', $name, $m)) {
            return $m[1] . 'x' . $m[2] . 'x' . $m[3] . ' SPF';
        }

        return null;
    }

    private function matchSheetGoods(string $name, string $lower): ?string
    {
        if (preg_match("/(\d+\/?\d*)\s*-?\s*4\s*(?:x|')\s*8\s*'?\\s*Plywood/i", $name, $m)) {
            return "4x8 {$m[1]}\" Plywood";
        }

        if (preg_match("/(\d+\/?\d*)\s*-\s*4'\s*x\s*8'\s*Plywood/i", $name, $m)) {
            return "4x8 {$m[1]}\" Plywood";
        }

        if (preg_match('/(\d+\/?\d*)-inch\s+x\s+4\s+ft\.\s+x\s+8\s+ft\.\s+Standard Spruce Plywood/i', $name, $m)) {
            return "4x8 {$m[1]}\" Plywood";
        }

        if (preg_match('/(\d+\/?\d*)\s*inch\s+4\s*ftx8\s*ft\s+Standard Spruce Plywood/i', $name, $m)) {
            return "4x8 {$m[1]}\" Plywood";
        }

        if (preg_match('/(\d+\/?\d*)\s+4x8\s+Oriented Strand Board/i', $name, $m)) {
            return "4x8 {$m[1]}\" OSB";
        }

        if (preg_match('/(\d+-foot|\d+)\s*x\s*(\d+-foot|\d+)\s*x\s*([\d\/]+)\s*inch\s+Oriented Strand Board/i', $name, $m)) {
            return "4x8 {$m[3]}\" OSB";
        }

        if (preg_match('/(\d+\/?\d*)\s*inch\s+4\s*ftx8\s*ft\s+Standard Spruce Plywood Tongue/i', $name, $m)) {
            return "4x8 {$m[1]}\" T&G Plywood";
        }

        if (preg_match('/(\d+\/?\d*)-inch\s+x\s+49-inch\s+x\s+97-inch\s+MDF/i', $name, $m)) {
            return "{$m[1]}\" MDF Sheet";
        }

        if (preg_match('/(\d+)mm\s+48-inch\s+X96-inch\s+Standard Hardboard/i', $name, $m)) {
            return "4x8 {$m[1]}mm Hardboard";
        }

        if (preg_match('/(\d+\/?\d*)\s*Inch\s+4X8\s+Melamine/i', $name, $m)) {
            return "4x8 {$m[1]}\" Melamine";
        }

        if (preg_match('/(\d+\/?\d*)\s*inch\s*\((\d+(?:\.\d+)?mm)\)\s*4x8\s+Sanded/i', $name, $m)) {
            return "4x8 {$m[1]}\" Fir Plywood";
        }

        if (preg_match('/(\d+\/?\d*)\s*inch\s+x\s+(\d+)\s+Feet\s+x\s+(\d+)\s+Feet\s+Birch Plywood/i', $name, $m)) {
            return "{$m[2]}x{$m[3]} {$m[1]}\" Birch";
        }

        if (preg_match('/White Hardboard\s+(\d+\/?\d*)\s*x\s*4\s*x\s*8/i', $name, $m)) {
            return "4x8 {$m[1]}\" Hardboard";
        }

        if (preg_match('/(\d+\/?\d*)\s*Inch\s+x\s+4\s+Feet\s+x\s+8\s+Feet\s+Sanded Aspen Plywood/i', $name, $m)) {
            return "4x8 {$m[1]}\" Aspen Plywood";
        }

        if (preg_match('/Premium Pine Plywood ACX\s+(\d+\/?\d*)\s*inch/i', $name, $m)) {
            return "4x8 {$m[1]}\" Pine Plywood";
        }

        if (preg_match('/(\d+(?:\.\d+)?)\s*mm\s+4\s*ft\.\s*x\s*8\s*ft\.\s*x[\d.]+mm\s+Plywood/i', $name, $m)) {
            return "4x8 {$m[1]}mm Plywood";
        }

        if (preg_match('/4\s*ft\.\s*x\s*8\s*ft\.\s*x\s*(\d+(?:\.\d+)?)\s*mm\s+Plywood/i', $name, $m)) {
            return "4x8 {$m[1]}mm Plywood";
        }

        if (preg_match('/(\d+\/?\d*)\s*inch\s*\(\d+(?:\.\d+)?mm\)\s*4x8\s+Sanded/i', $name, $m)) {
            return "4x8 {$m[1]}\" Fir Plywood";
        }

        if (preg_match('/(\d+)\s*in\.\s*x\s*(\d+)\s*in\.\s*x\s*(\d+)\s*ft\.\s*Premium\s+Knotty Cedar Wood/i', $name, $m)) {
            return $m[1] . 'x' . $m[2] . 'x' . $m[3] . ' Cedar';
        }

        return null;
    }

    private function matchDrywall(string $name, string $lower): ?string
    {
        if (preg_match('/Sheetrock\s+(\d+),/i', $name, $type)
            && preg_match('/(\d+(?:\.\d+)?)\s*kg\s+Bag/i', $name, $size)) {
            return $size[1] . 'kg Sheetrock ' . $type[1];
        }

        if (preg_match('/Sheetrock\s+(\d+),.*(\d+(?:\.\d+)?)\s*kg\s+Bag/i', $name, $m)) {
            return $m[2] . 'kg Sheetrock ' . $m[1];
        }

        if (preg_match('/Durabond\s+(\d+),/i', $name, $type)
            && preg_match('/(\d+(?:\.\d+)?)\s*kg\s+Bag/i', $name, $size)) {
            return $size[1] . 'kg Durabond ' . $type[1];
        }

        if (preg_match('/All Purpose(?:-Lite)?\s+Drywall Compound,.*?, (\d+(?:\.\d+)?)\s*L\s+(Pail|Carton)/i', $name, $m)) {
            return $m[1] . 'L All Purpose Mud';
        }

        if (preg_match('/Dust Control Drywall Compound,.*?, (\d+(?:\.\d+)?)\s*L\s+(Pail|Carton)/i', $name, $m)) {
            return $m[1] . 'L Dust Control Mud';
        }

        if (preg_match('/Machine Mud Drywall Compound,.*?, (\d+(?:\.\d+)?)\s*L\s+Carton/i', $name, $m)) {
            return $m[1] . 'L Machine Mud';
        }

        if (preg_match('/Total Drywall Compound,.*?, (\d+(?:\.\d+)?)\s*L\s+Carton/i', $name, $m)) {
            return $m[1] . 'L Total Mud';
        }

        if (preg_match('/All Purpose Drywall Compound,.*?, (\d+(?:\.\d+)?)\s*L\s+Pail/i', $name, $m)) {
            return $m[1] . 'L All Purpose Mud';
        }

        if (preg_match('/Spackling\b[^,]*?(\d+)\s*mL/i', $name, $m)) {
            return $m[1] . 'mL Spackling';
        }

        if (preg_match('/Plaster of Paris.*(\d+)\s*kg/i', $name, $m)) {
            return $m[1] . 'kg Plaster';
        }

        if (str_contains($lower, 'smartwall patch pro compound')) {
            return 'Patch Pro Compound';
        }

        return null;
    }

    private function matchHardware(string $name, string $lower): ?string
    {
        if (preg_match('/Fence Rail Bracket for\s+(2x\d+)/i', $name, $m)) {
            return $m[1] . ' Fence Bracket';
        }

        if (preg_match('/Fence Post Cap/i', $name)) {
            return 'Post Cap';
        }

        if (preg_match('/Joist Hanger for (?:Interior |Exterior )?(2x\d+|\d+x\d+)/i', $name, $m)) {
            return str_replace(' ', '', $m[1]) . ' Joist Hanger';
        }

        if (preg_match('/(?:Concealed-Flange )?Joist Hanger for (2x\d+)/i', $name, $m)) {
            return $m[1] . ' Joist Hanger';
        }

        if (preg_match('/Rafter (?:U )?Hanger for (2x\d+)/i', $name, $m)) {
            return $m[1] . ' Rafter Hanger';
        }

        if (preg_match('/Jack Hanger for (2x\d+)/i', $name, $m)) {
            return $m[1] . ' Jack Hanger';
        }

        if (preg_match('/Rafter Hanger for (2x\d+)/i', $name, $m)) {
            return $m[1] . ' Rafter Hanger';
        }

        if (preg_match('/Post Cap for (4x|6x|\d+x\d+)/i', $name, $m)) {
            $size = str_replace(' ', '', $m[1]);

            return str_ends_with($size, 'x') ? 'Post Cap ' . $size : 'Post Cap ' . $size;
        }

        if (preg_match('/Post Base for (4x4|6x6|4x|6x|\d+x\d+)/i', $name, $m)) {
            return 'Post Base ' . str_replace(' ', '', $m[1]);
        }

        if (preg_match('/Standoff Post Base for (4x4|6x6)/i', $name, $m)) {
            return 'Post Base ' . $m[1];
        }

        if (preg_match('/Retrofit Post Base for (Double\s+2x\d+)/i', $name, $m)) {
            return 'Retrofit ' . str_replace(' ', '', $m[1]) . ' Base';
        }

        if (preg_match('/Post Cap\/Base for Double (\d+x) Beam, (4x|6x) Post/i', $name, $m)) {
            return 'Cap/Base ' . $m[1] . ' on ' . $m[2];
        }

        if (str_contains($lower, 'rebar carport saddle')) {
            if (preg_match('/(\d+-\d+\/\d+)\s*inch\s*x\s*(\d+-\d+\/\d+)\s*inch/i', $name, $m)) {
                return 'Carport Saddle ' . $m[1];
            }

            if (preg_match('/for (6x6)/i', $name, $m)) {
                return 'Carport Saddle ' . $m[1];
            }
        }

        if (str_contains($lower, 'gate corner') || (str_contains($lower, 'gate') && str_contains($lower, 'bracket'))) {
            return 'Gate Hardware';
        }

        return null;
    }

    private function matchConcrete(string $name, string $lower): ?string
    {
        if (preg_match('/Rapid Post,\s*(\d+)\s*Kg/i', $name, $m)) {
            return 'Cement bag ' . $m[1] . 'kg';
        }

        return null;
    }

    private function matchFasteners(string $name, string $lower): ?string
    {
        if (preg_match('/(\d+(?:\.\d+)?)\s*(?:°|Degree).*Nail/i', $name)
            || preg_match('/\bNail Project Pack\b/i', $name)) {
            if (preg_match('/(\d+(?:\.\d+)?)\s*Inch/i', $name, $m)) {
                return $m[1] . '" Nails';
            }

            return 'Framing Nails';
        }

        if (preg_match('/(\d+)\s*Count.*Framing Nails.*\((\d+)\s*Inch/i', $name, $m)) {
            return $m[2] . '" Framing Nails';
        }

        return null;
    }

    private function matchMiscWood(string $name, string $lower): ?string
    {
        if (preg_match("/(\d+)'\s*Wood Mini Tie/i", $name, $m)) {
            return $m[1] . "' Mini Tie";
        }

        if (preg_match('/(\d+)\s*x\s*(\d+)\s*x\s*(\d+)\s+Wood Nailing Strip/i', $name, $m)) {
            return $m[1] . 'x' . $m[2] . 'x' . $m[3] . ' Nailing Strip';
        }

        if (str_contains($lower, 'stair stringer')) {
            if (preg_match('/(\d+)-Step/i', $name, $m)) {
                return $m[1] . '-Step Stringer';
            }
        }

        return null;
    }
}

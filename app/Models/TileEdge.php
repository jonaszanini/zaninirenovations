<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TileEdge extends Model
{
    use HasFactory;

    /** Standard purchase length in feet for edge pieces. */
    public const PIECE_LENGTH_FT = 8;

    protected $fillable = [
        'name',
        'url',
        'thickness',
        'color',
        'unit_price',
        'sort_order',
        'is_active',
    ];

    protected $casts = [
        'unit_price' => 'decimal:4',
        'sort_order' => 'integer',
        'is_active' => 'boolean',
    ];

    public function optionLabel(): string
    {
        $bits = [trim((string) $this->name)];
        if (filled($this->thickness)) {
            $bits[] = trim((string) $this->thickness).'"';
        }
        if (filled($this->color)) {
            $bits[] = trim((string) $this->color);
        }
        $bits[] = '$'.number_format((float) $this->unit_price, 2).'/pc';

        return implode(' — ', $bits);
    }
}

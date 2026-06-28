<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * FenceFormulaSetting — singleton row (id = 1) storing every tunable
 * parameter of the fence calculator. Access via
 * `FenceFormulaSetting::current()` which creates-or-fetches the row;
 * treat it as a config object, not a collection.
 *
 * The migration seeds sensible defaults matching the Ontario Building
 * Code conventions the app uses in the spec (1/8" gaps, 8' max post
 * spacing, 1.78 bags/post for 6' fences, etc.). Labour per foot is
 * stored separately for 6' and 8' fence heights.
 */
class FenceFormulaSetting extends Model
{
    protected $fillable = [
        'gate_rails',
        'concrete_per_post_6ft',
        'concrete_per_post_8ft',
        'labour_rate_per_ft',
        'labour_rate_8ft_per_ft',
        'labour_per_additional_gate',
        'rails_per_section_6ft',
        'rails_per_section_8ft',
        'nails_per_picket',
        'board_width_in',
        'overlapping_in',
        'max_post_spacing_ft',
    ];

    protected $casts = [
        'gate_rails' => 'integer',
        'concrete_per_post_6ft' => 'decimal:3',
        'concrete_per_post_8ft' => 'decimal:3',
        'labour_rate_per_ft' => 'decimal:2',
        'labour_rate_8ft_per_ft' => 'decimal:2',
        'labour_per_additional_gate' => 'decimal:2',
        'rails_per_section_6ft' => 'integer',
        'rails_per_section_8ft' => 'integer',
        'nails_per_picket' => 'integer',
        'board_width_in' => 'decimal:3',
        'overlapping_in' => 'decimal:3',
        'max_post_spacing_ft' => 'decimal:2',
    ];

    /**
     * Return the singleton settings row, creating it on first access with
     * the migration's default values baked into the column defaults.
     */
    public static function current(): self
    {
        /** @var self $row */
        $row = static::query()->firstOrCreate(['id' => 1]);

        return $row;
    }

    /**
     * Concrete bags per post for a given fence height (feet).
     *
     * We only support the two heights the spec mentions (6' and 8');
     * anything else falls back to the 6' multiplier so the calculator
     * still produces a sensible number instead of zero.
     */
    public function concretePerPostForHeight(float $heightFt): float
    {
        return (float) (((int) round($heightFt)) === 8
            ? $this->concrete_per_post_8ft
            : $this->concrete_per_post_6ft);
    }

    /** Horizontal rails per section for a given fence height (6' or 8'). */
    public function railsPerSectionForHeight(float $heightFt): int
    {
        return (int) (((int) round($heightFt)) === 8
            ? $this->rails_per_section_8ft
            : $this->rails_per_section_6ft);
    }

    /**
     * Labour charged per linear foot for a given fence height (6' or 8').
     * Falls back to the 6' rate if height is anything else.
     */
    public function labourRatePerFtForHeight(float $heightFt): float
    {
        return (float) (((int) round($heightFt)) === 8
            ? $this->labour_rate_8ft_per_ft
            : $this->labour_rate_per_ft);
    }
}

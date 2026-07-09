<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * LowDeckFormulaSetting — singleton row (id = 1) storing every tunable
 * parameter of the low deck calculator. Access via
 * `LowDeckFormulaSetting::current()` which creates-or-fetches the row;
 * treat it as a config object, not a collection.
 *
 * Defaults follow the Ontario Building Code conventions used in the spec
 * (592 sq ft guardrail, 5 ft block grid, 9' 4" max 2x6 span at 12" O.C.,
 * 5.5" boards with a 1/8" gap, etc.).
 */
class LowDeckFormulaSetting extends Model
{
    protected $fillable = [
        'max_area_sq_ft',
        'block_spacing_ft',
        'default_joist_spacing_in',
        'max_joist_span_in',
        'board_width_in',
        'board_gap_in',
        'post_length_per_block_ft',
        'gravel_depth_in',
        'gravel_footprint_in',
        'gravel_cubic_ft_per_bag',
        'under_deck_gravel_sq_ft_per_bag',
        'structural_screws_per_post',
        'structural_screws_per_corner',
        'board_screws_per_inch',
        'frame_screws_per_t_joint',
        'frame_screws_per_cross_joint',
        'joist_tape_roll_length_ft',
        'joist_tape_overlap_in',
        'kerf_in',
        'labour_rate_per_sqft',
        'waste_dump_fee_cad',
        'work_description_template',
    ];

    protected $casts = [
        'max_area_sq_ft' => 'decimal:2',
        'block_spacing_ft' => 'decimal:2',
        'default_joist_spacing_in' => 'integer',
        'max_joist_span_in' => 'decimal:2',
        'board_width_in' => 'decimal:3',
        'board_gap_in' => 'decimal:3',
        'post_length_per_block_ft' => 'decimal:2',
        'gravel_depth_in' => 'decimal:2',
        'gravel_footprint_in' => 'decimal:2',
        'gravel_cubic_ft_per_bag' => 'decimal:3',
        'under_deck_gravel_sq_ft_per_bag' => 'decimal:2',
        'structural_screws_per_post' => 'integer',
        'structural_screws_per_corner' => 'integer',
        'board_screws_per_inch' => 'decimal:4',
        'frame_screws_per_t_joint' => 'integer',
        'frame_screws_per_cross_joint' => 'integer',
        'joist_tape_roll_length_ft' => 'decimal:2',
        'joist_tape_overlap_in' => 'decimal:2',
        'kerf_in' => 'decimal:3',
        'labour_rate_per_sqft' => 'decimal:2',
        'waste_dump_fee_cad' => 'decimal:2',
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
}

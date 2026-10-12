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
        'concrete_per_post_6x6_6ft',
        'concrete_per_post_6x6_8ft',
        'labour_rate_per_ft',
        'labour_rate_8ft_per_ft',
        'labour_per_additional_gate',
        'existing_post_labour_credit',
        'rails_per_section_6ft',
        'rails_per_section_8ft',
        'nails_per_picket',
        'board_width_in',
        'overlapping_in',
        'max_post_spacing_ft',
        'waste_extra_posts',
        'waste_pickets_per_section',
        'waste_extra_rails',
        'waste_extra_brackets',
        'waste_extra_caps',
        'waste_concrete_posts_per_extra_bag',
        'work_description_template',
    ];

    protected $casts = [
        'gate_rails' => 'integer',
        'concrete_per_post_6ft' => 'decimal:3',
        'concrete_per_post_8ft' => 'decimal:3',
        'concrete_per_post_6x6_6ft' => 'decimal:3',
        'concrete_per_post_6x6_8ft' => 'decimal:3',
        'labour_rate_per_ft' => 'decimal:2',
        'labour_rate_8ft_per_ft' => 'decimal:2',
        'labour_per_additional_gate' => 'decimal:2',
        'existing_post_labour_credit' => 'decimal:2',
        'rails_per_section_6ft' => 'integer',
        'rails_per_section_8ft' => 'integer',
        'nails_per_picket' => 'integer',
        'board_width_in' => 'decimal:3',
        'overlapping_in' => 'decimal:3',
        'max_post_spacing_ft' => 'decimal:2',
        'waste_extra_posts' => 'integer',
        'waste_pickets_per_section' => 'integer',
        'waste_extra_rails' => 'integer',
        'waste_extra_brackets' => 'integer',
        'waste_extra_caps' => 'integer',
        'waste_concrete_posts_per_extra_bag' => 'integer',
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
     * Concrete bags per post for a given fence height and post size.
     * 6×6 uses its own rates so a larger hole is not silently priced as 4×4.
     */
    public function concretePerPostForHeight(float $heightFt, string $postSize = FenceQuotation::POST_SIZE_4X4): float
    {
        $isEight = ((int) round($heightFt)) === 8;
        $use6x6 = $postSize === FenceQuotation::POST_SIZE_6X6;

        if ($use6x6) {
            $six = $this->concrete_per_post_6x6_6ft;
            $eight = $this->concrete_per_post_6x6_8ft;
            $sixVal = $six !== null ? (float) $six : 2.5;
            $eightVal = $eight !== null ? (float) $eight : 3.5;

            return $isEight ? $eightVal : $sixVal;
        }

        return (float) ($isEight ? $this->concrete_per_post_8ft : $this->concrete_per_post_6ft);
    }

    public function wasteExtraPosts(): int
    {
        return max(0, (int) ($this->waste_extra_posts ?? 1));
    }

    public function wastePicketsPerSection(): int
    {
        return max(0, (int) ($this->waste_pickets_per_section ?? 1));
    }

    public function wasteExtraRails(): int
    {
        return max(0, (int) ($this->waste_extra_rails ?? 3));
    }

    public function wasteExtraBrackets(): int
    {
        return max(0, (int) ($this->waste_extra_brackets ?? 6));
    }

    public function wasteExtraCaps(): int
    {
        return max(0, (int) ($this->waste_extra_caps ?? 1));
    }

    public function wasteConcretePostsPerExtraBag(): int
    {
        $n = (int) ($this->waste_concrete_posts_per_extra_bag ?? 3);

        return $n > 0 ? $n : 3;
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

    /**
     * Labour credit (CAD$) for each existing-post tick that actually removes a post.
     */
    public function existingPostLabourCredit(): float
    {
        return max(0, (float) ($this->existing_post_labour_credit ?? 30));
    }
}

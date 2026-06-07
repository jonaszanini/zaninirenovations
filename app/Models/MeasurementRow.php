<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MeasurementRow extends Model
{
    public const UNIT_MM = 'mm';

    public const UNIT_CM = 'cm';

    public const UNIT_M = 'm';

    public const UNIT_IN_DEC = 'in_dec';

    public const UNIT_IN_FRAC = 'in_frac';

    public const UNIT_FT_DEC = 'ft_dec';

    public const UNIT_FT_IN_DEC = 'ft_in_dec';

    public const UNIT_FT_IN_FRAC = 'ft_in_frac';

    protected $fillable = [
        'measurement_project_id',
        'label',
        'source_unit',
        'source_value',
        'base_mm',
        'sort_order',
    ];

    protected $casts = [
        'base_mm' => 'float',
        'sort_order' => 'integer',
    ];

    public function project(): BelongsTo
    {
        return $this->belongsTo(MeasurementProject::class, 'measurement_project_id');
    }
}

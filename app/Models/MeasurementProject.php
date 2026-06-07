<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MeasurementProject extends Model
{
    protected $fillable = [
        'name',
        'client_project_id',
    ];

    public function clientProject(): BelongsTo
    {
        return $this->belongsTo(ClientProject::class);
    }

    public function rows(): HasMany
    {
        return $this->hasMany(MeasurementRow::class)->orderBy('sort_order')->orderBy('id');
    }
}

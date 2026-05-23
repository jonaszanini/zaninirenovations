<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Helper extends Model
{
    protected $fillable = [
        'name',
        'phone',
        'email',
        'hourly_rate',
        'is_active',
    ];

    protected $casts = [
        'hourly_rate' => 'decimal:2',
        'is_active' => 'boolean',
    ];

    public function projects(): BelongsToMany
    {
        return $this->belongsToMany(
            ClientProject::class,
            'client_project_helper',
            'helper_id',
            'client_project_id'
        )->withTimestamps();
    }

    public function timeEntries(): HasMany
    {
        return $this->hasMany(ClientProjectHelperTimeEntry::class);
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function displayPhone(): ?string
    {
        if (! filled($this->phone)) {
            return null;
        }

        return ClientProject::formatPhone($this->phone) ?: (string) $this->phone;
    }
}

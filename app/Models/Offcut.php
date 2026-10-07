<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Offcut extends Model
{
    public const MIN_LINEAR_IN = 12.0;

    public const MIN_SHEET_IN = 2.0;

    protected $fillable = [
        'material_key',
        'thickness_in',
        'width_in',
        'length_in',
        'qty',
        'location',
        'notes',
        'created_from_project_id',
        'used_by_project_id',
        'used_at',
    ];

    protected $casts = [
        'thickness_in' => 'decimal:3',
        'width_in' => 'decimal:3',
        'length_in' => 'decimal:3',
        'qty' => 'integer',
        'created_from_project_id' => 'integer',
        'used_by_project_id' => 'integer',
        'used_at' => 'datetime',
    ];

    public function createdFromProject(): BelongsTo
    {
        return $this->belongsTo(ClientProject::class, 'created_from_project_id');
    }

    public function usedByProject(): BelongsTo
    {
        return $this->belongsTo(ClientProject::class, 'used_by_project_id');
    }

    public function scopeAvailable(Builder $query): Builder
    {
        return $query->whereNull('used_at')->where('qty', '>', 0);
    }

    public function isAvailable(): bool
    {
        return $this->used_at === null && (int) $this->qty > 0;
    }

    /**
     * Sheet leftover when a second dimension (width) is stored.
     * Linear lumber stores length only (width_in null/0).
     */
    public function isSheet(): bool
    {
        return (float) ($this->width_in ?? 0) > 0;
    }

    public function longerSideIn(): float
    {
        $length = (float) $this->length_in;
        $width = (float) ($this->width_in ?? 0);

        return max($length, $width);
    }

    public function shorterSideIn(): float
    {
        $length = (float) $this->length_in;
        $width = (float) ($this->width_in ?? 0);
        if ($width <= 0) {
            return $length;
        }

        return min($length, $width);
    }

    public function sizeLabel(): string
    {
        $length = $this->trimNum((float) $this->length_in);
        if ($this->isSheet()) {
            $width = $this->trimNum((float) $this->width_in);

            return $width.'×'.$length.' in';
        }

        return $length.' in';
    }

    public function displayName(): string
    {
        $key = trim((string) $this->material_key);

        return $key !== '' ? $key : 'Offcut';
    }

    public function markUsed(?int $projectId = null): void
    {
        $this->used_at = now();
        $this->used_by_project_id = $projectId;
        $this->qty = 0;
        $this->save();
    }

    /**
     * Consume one or more pieces. Marks the row used when qty hits zero.
     */
    public function consume(int $count, ?int $projectId = null): int
    {
        $count = max(0, $count);
        if ($count === 0 || ! $this->isAvailable()) {
            return 0;
        }

        $take = min($count, (int) $this->qty);
        $remaining = (int) $this->qty - $take;
        if ($remaining <= 0) {
            $this->markUsed($projectId);
        } else {
            $this->qty = $remaining;
            $this->save();
        }

        return $take;
    }

    private function trimNum(float $n): string
    {
        $formatted = number_format($n, 3, '.', '');

        return rtrim(rtrim($formatted, '0'), '.');
    }
}

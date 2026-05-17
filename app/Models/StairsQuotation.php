<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StairsQuotation extends Model
{
    public const HST_RATE = 0.13;

    protected $fillable = [
        'client_project_id',
        'quote_number',
        'client_name',
        'is_draft',
        'inputs',
        'calculation',
        'final_quote_total',
        'version',
        'linked_at',
    ];

    protected $casts = [
        'inputs' => 'array',
        'calculation' => 'array',
        'is_draft' => 'boolean',
        'final_quote_total' => 'decimal:2',
        'version' => 'integer',
        'linked_at' => 'datetime',
    ];

    public function project(): BelongsTo
    {
        return $this->belongsTo(ClientProject::class, 'client_project_id');
    }

    public function isLinked(): bool
    {
        return $this->client_project_id !== null;
    }

    public function summaryDescription(): string
    {
        $inputs = is_array($this->inputs) ? $this->inputs : [];
        $steps = (int) ($inputs['total_steps'] ?? 0);
        $mid = $inputs['material_type_id'] ?? null;
        $material = $mid ? StairsMaterialType::query()->find($mid) : null;
        $matName = $material ? $material->name : 'Stairs';
        $open = match ($inputs['open_sides'] ?? 'none') {
            'one' => 'One open side',
            'two' => 'Two open sides',
            default => 'No open sides',
        };

        return sprintf(
            'Stairs renovation — %d steps, %s, %s',
            $steps,
            $matName,
            $open
        );
    }

    public static function generateNumber(): string
    {
        $year = (int) date('Y');
        $latest = static::query()
            ->where('quote_number', 'like', "ST-{$year}-%")
            ->orderByDesc('quote_number')
            ->first();

        if ($latest) {
            $parts = explode('-', $latest->quote_number);
            $next = (int) end($parts) + 1;
        } else {
            $next = random_int(100, 400);
        }

        return sprintf('ST-%d-%04d', $year, $next);
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ClientProjectTimeEntry extends Model
{
    protected $table = 'client_project_time_entries';

    protected $fillable = [
        'client_project_id',
        'occurred_on',
        'hours',
        'note',
    ];

    protected $casts = [
        'occurred_on' => 'date',
        'hours' => 'decimal:2',
    ];

    public function project(): BelongsTo
    {
        return $this->belongsTo(ClientProject::class, 'client_project_id');
    }
}

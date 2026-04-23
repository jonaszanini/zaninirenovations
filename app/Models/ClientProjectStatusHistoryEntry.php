<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ClientProjectStatusHistoryEntry extends Model
{
    protected $table = 'client_project_status_history';

    protected $fillable = [
        'client_project_id',
        'from_status',
        'to_status',
        'note',
        'user_id',
        'changed_at',
    ];

    protected $casts = [
        'changed_at' => 'datetime',
    ];

    public function project(): BelongsTo
    {
        return $this->belongsTo(ClientProject::class, 'client_project_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function fromLabel(): string
    {
        if ($this->from_status === null || $this->from_status === $this->to_status) {
            return '—';
        }

        return ClientProject::statusLabels()[$this->from_status] ?? ucfirst(str_replace('_', ' ', $this->from_status));
    }

    public function toLabel(): string
    {
        return ClientProject::statusLabels()[$this->to_status] ?? ucfirst(str_replace('_', ' ', $this->to_status));
    }

    /**
     * True when this row is a plain event (email/file/audit) rather than
     * a real status transition. The view uses this to render the "To"
     * column as a muted badge instead of the bold status change.
     */
    public function isEvent(): bool
    {
        return $this->from_status !== null && $this->from_status === $this->to_status;
    }

    /**
     * When column for the History tab — always shown in the app timezone
     * (default America/Toronto = Eastern) with EST/EDT abbreviation.
     */
    public function changedAtDisplay(): string
    {
        return ClientProject::formatDateTime($this->changed_at);
    }
}

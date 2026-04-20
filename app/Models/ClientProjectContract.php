<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

/**
 * ClientProjectContract — rich-text contract document attached to a
 * ClientProject. The body is HTML (produced by TinyMCE in the admin),
 * and we generate a PDF on demand for download / emailing to the client.
 *
 * One contract per project (unique on client_project_id).
 */
class ClientProjectContract extends Model
{
    protected $fillable = [
        'client_project_id',
        'html_content',
        'pdf_path',
        'sent_at',
        'version',
    ];

    protected $casts = [
        'sent_at' => 'datetime',
        'version' => 'integer',
    ];

    public function project(): BelongsTo
    {
        return $this->belongsTo(ClientProject::class, 'client_project_id');
    }

    /**
     * Defensive cleanup: when a contract row is deleted directly (i.e. not
     * through the parent project's cascade, which doesn't fire model events),
     * also remove the latest PDF on disk.
     *
     * The parent ClientProject's deleting hook handles every version when a
     * whole project is deleted; this only ensures we don't orphan a single
     * file when a contract is deleted on its own.
     */
    protected static function booted(): void
    {
        static::deleting(function (self $contract): void {
            if (filled($contract->pdf_path)) {
                Storage::disk('local')->delete($contract->pdf_path);
            }
        });
    }

    /** Has the contract been emailed to the client at least once? */
    public function isSent(): bool
    {
        return $this->sent_at !== null;
    }

    /** Has the editor body been written (i.e. there's something to render)? */
    public function hasContent(): bool
    {
        return filled(trim(strip_tags((string) $this->html_content)));
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

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

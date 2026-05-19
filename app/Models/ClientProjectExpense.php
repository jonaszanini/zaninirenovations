<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

class ClientProjectExpense extends Model
{
    protected $fillable = [
        'client_project_id',
        'client_project_invoice_id',
        'expense_ref',
        'occurred_on',
        'description',
        'amount',
        'receipt_path',
    ];

    protected $casts = [
        'occurred_on' => 'date',
        'amount' => 'decimal:2',
        'expense_ref' => 'integer',
    ];

    protected static function booted(): void
    {
        static::deleting(function (self $expense): void {
            if (! filled($expense->receipt_path)) {
                return;
            }
            $rel = ltrim(str_replace('\\', '/', (string) $expense->receipt_path), '/');
            Storage::disk('public')->delete($rel);
            $mirror = public_path('storage/'.$rel);
            if (is_file($mirror)) {
                @unlink($mirror);
            }
        });
    }

    /** Filename for email attachments (matches on-disk name: Receipt-#.webp or .pdf). */
    public function receiptAttachmentBasename(): string
    {
        if (filled($this->receipt_path)) {
            $base = basename(str_replace('\\', '/', (string) $this->receipt_path));
            if ($base !== '' && $base !== '.' && $base !== '..') {
                return $base;
            }
        }

        return 'Receipt-'.(int) $this->expense_ref.'.webp';
    }

    /** MIME type for mail attachments. */
    public function receiptAttachmentMime(): string
    {
        $base = strtolower(basename(str_replace('\\', '/', (string) ($this->receipt_path ?? ''))));

        if (str_ends_with($base, '.pdf')) {
            return 'application/pdf';
        }

        return 'image/webp';
    }

    /** True when the stored file is WebP (thumbnail preview in admin). */
    public function receiptIsImagePreview(): bool
    {
        if (! filled($this->receipt_path)) {
            return false;
        }
        $base = strtolower(basename(str_replace('\\', '/', $this->receipt_path)));

        return str_ends_with($base, '.webp');
    }

    /** Public URL for the receipt file, or null when none. */
    public function receiptUrl(): ?string
    {
        if (! filled($this->receipt_path)) {
            return null;
        }

        return asset('storage/'.ltrim(str_replace('\\', '/', $this->receipt_path), '/'));
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(ClientProject::class, 'client_project_id');
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(ClientProjectInvoice::class, 'client_project_invoice_id');
    }
}

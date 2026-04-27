<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Audit log of every email + SMS sent by the application.
 *
 * One row per send attempt (status records whether it succeeded). The
 * row is denormalised on purpose — the snapshots of project name +
 * client name + recipient survive even after the parent project is
 * deleted, so the historical record is never silently lost.
 *
 * Created exclusively via App\Services\ClientCommunicationLogger; the
 * model itself is read-mostly outside of those write paths.
 */
class ClientCommunicationLog extends Model
{
    public const CHANNEL_EMAIL = 'email';
    public const CHANNEL_SMS = 'sms';

    public const DIRECTION_TO_CLIENT = 'to_client';
    public const DIRECTION_TO_ADMIN = 'to_admin';

    public const STATUS_SENT = 'sent';
    public const STATUS_FAILED = 'failed';

    /*
     * Reason codes — every place we send a message picks one of these.
     * Adding a new reason is a 2-step change: add the constant here +
     * a label in REASON_LABELS below.
     */
    public const REASON_QUOTATION_SENT = 'quotation_sent';
    public const REASON_CONTRACT_SENT = 'contract_sent';
    public const REASON_INVOICE_SENT = 'invoice_sent';
    public const REASON_INVOICE_REMINDER = 'invoice_reminder';
    public const REASON_RECEIPT_SENT = 'receipt_sent';
    public const REASON_QUOTE_RECEIVED_AUTO = 'quote_received_auto';
    public const REASON_SERVICE_CONFIRMATION = 'service_confirmation';
    public const REASON_FIXED_SCHEDULE_CONFIRMATION = 'fixed_schedule_confirmation';
    public const REASON_TIME_TRACKING_SUMMARY = 'time_tracking_summary';
    public const REASON_THANK_YOU = 'thank_you';
    public const REASON_COMMENCEMENT_EVE_SMS = 'commencement_eve_sms';
    public const REASON_COMMENCEMENT_EVE_ADMIN = 'commencement_eve_admin';
    public const REASON_CALENDAR_EVENT_ADMIN = 'calendar_event_admin';
    public const REASON_QUOTE_PENDING_REMINDER_ADMIN = 'quote_pending_reminder_admin';
    public const REASON_QUOTE_SENT_FOLLOWUP_ADMIN = 'quote_sent_followup_admin';

    public const REASON_LABELS = [
        self::REASON_QUOTATION_SENT => 'Quotation',
        self::REASON_CONTRACT_SENT => 'Contract',
        self::REASON_INVOICE_SENT => 'Invoice',
        self::REASON_INVOICE_REMINDER => 'Invoice reminder',
        self::REASON_RECEIPT_SENT => 'Receipt',
        self::REASON_QUOTE_RECEIVED_AUTO => 'Quote received (auto-reply)',
        self::REASON_SERVICE_CONFIRMATION => 'Service confirmation',
        self::REASON_FIXED_SCHEDULE_CONFIRMATION => 'Schedule confirmation',
        self::REASON_TIME_TRACKING_SUMMARY => 'Time-tracking summary',
        self::REASON_THANK_YOU => 'Thank you / review',
        self::REASON_COMMENCEMENT_EVE_SMS => 'Save the date (day before)',
        self::REASON_COMMENCEMENT_EVE_ADMIN => 'Day-before (admin)',
        self::REASON_CALENDAR_EVENT_ADMIN => 'Calendar event (admin)',
        self::REASON_QUOTE_PENDING_REMINDER_ADMIN => 'Daily quote reminder (admin)',
        self::REASON_QUOTE_SENT_FOLLOWUP_ADMIN => 'Quote follow-up (admin)',
    ];

    protected $fillable = [
        'client_project_id',
        'project_name_snapshot',
        'client_name_snapshot',
        'channel',
        'direction',
        'reason',
        'recipient',
        'cc',
        'subject',
        'description',
        'status',
        'error',
        'sent_at',
    ];

    protected $casts = [
        'sent_at' => 'datetime',
    ];

    public function project(): BelongsTo
    {
        return $this->belongsTo(ClientProject::class, 'client_project_id');
    }

    // --- Display helpers -------------------------------------------------

    public function reasonLabel(): string
    {
        return self::REASON_LABELS[$this->reason] ?? ucfirst(str_replace('_', ' ', (string) $this->reason));
    }

    public function channelLabel(): string
    {
        return $this->channel === self::CHANNEL_SMS ? 'SMS' : 'Email';
    }

    public function directionLabel(): string
    {
        return $this->direction === self::DIRECTION_TO_ADMIN ? 'Admin' : 'Client';
    }

    public function isFailed(): bool
    {
        return $this->status === self::STATUS_FAILED;
    }

    public function clientNameLabel(): string
    {
        $name = trim((string) $this->client_name_snapshot);
        if ($name !== '') {
            return $name;
        }
        // Fallback for admin-bound rows where there's no client.
        return $this->direction === self::DIRECTION_TO_ADMIN ? '— (admin)' : '—';
    }

    public function projectNameLabel(): string
    {
        $name = trim((string) $this->project_name_snapshot);

        return $name !== '' ? $name : '—';
    }

    /**
     * Color hex for the reason badge. Mirrors the brand status palette
     * so the log feels at home next to the project status badges.
     */
    public function reasonColor(): string
    {
        return match ($this->reason) {
            self::REASON_QUOTATION_SENT,
            self::REASON_QUOTE_RECEIVED_AUTO => '#3b82f6',          // blue
            self::REASON_CONTRACT_SENT => '#8b5cf6',                 // violet
            self::REASON_INVOICE_SENT,
            self::REASON_INVOICE_REMINDER => '#f59e0b',              // amber
            self::REASON_RECEIPT_SENT => '#22c55e',                  // green
            self::REASON_SERVICE_CONFIRMATION,
            self::REASON_FIXED_SCHEDULE_CONFIRMATION => '#10b981',   // emerald
            self::REASON_COMMENCEMENT_EVE_SMS,
            self::REASON_COMMENCEMENT_EVE_ADMIN => '#0891b2',        // cyan
            self::REASON_THANK_YOU => '#ec4899',                     // pink
            self::REASON_TIME_TRACKING_SUMMARY => '#6366f1',         // indigo
            default => '#6b7280',                                    // slate
        };
    }

    // --- Query scopes ---------------------------------------------------

    public function scopeChannel(Builder $q, ?string $channel): Builder
    {
        return $channel ? $q->where('channel', $channel) : $q;
    }

    public function scopeReason(Builder $q, ?string $reason): Builder
    {
        return $reason ? $q->where('reason', $reason) : $q;
    }

    public function scopeDirection(Builder $q, ?string $direction): Builder
    {
        return $direction ? $q->where('direction', $direction) : $q;
    }

    public function scopeForProject(Builder $q, ?int $projectId): Builder
    {
        return $projectId ? $q->where('client_project_id', $projectId) : $q;
    }
}

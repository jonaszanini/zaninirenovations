<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Directory entry for a person/company you work with. Projects link via
 * `client_id`; contact fields are also denormalised on ClientProject for
 * historical PDFs and per-project overrides.
 */
class Client extends Model
{
    protected $fillable = [
        'name',
        'email',
        'email_secondary',
        'phone',
        'sms_intro_sent_at',
        'address',
        'address_place_id',
        'address_latitude',
        'address_longitude',
        'notes',
    ];

    protected $casts = [
        'sms_intro_sent_at' => 'datetime',
        'address_latitude' => 'decimal:7',
        'address_longitude' => 'decimal:7',
    ];

    public function projects(): HasMany
    {
        return $this->hasMany(ClientProject::class, 'client_id');
    }

    /**
     * Directory client for a project (linked id, or phone match on denormalised
     * project contact fields).
     */
    public static function resolveForProject(ClientProject $project): ?self
    {
        if ($project->client_id) {
            return $project->client;
        }

        return static::findBySmsPhone($project->client_phone);
    }

    /**
     * Find a directory client whose phone normalises to the same SMS digits.
     */
    public static function findBySmsPhone(?string $phone): ?self
    {
        $digits = ClientProject::phoneDigitsForSms($phone);
        if ($digits === null) {
            return null;
        }

        return static::query()
            ->whereNotNull('phone')
            ->where('phone', '!=', '')
            ->get()
            ->first(fn (self $client) => ClientProject::phoneDigitsForSms($client->phone) === $digits);
    }

    /** Single-line label for search dropdowns (Tom Select, etc.). */
    public function searchLabel(): string
    {
        $parts = array_filter([$this->name, $this->email], fn ($v) => filled($v));

        return implode(' — ', $parts) ?: ('Client #'.$this->id);
    }

    /**
     * Payload for the admin client-picker (project create/edit). Phone is
     * returned as stored (digits); the UI runs it through formatPhone.
     */
    public function toPickerArray(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'email_secondary' => $this->email_secondary,
            'phone' => $this->phone,
            'address' => $this->address,
            'address_place_id' => $this->address_place_id,
            'address_latitude' => $this->address_latitude,
            'address_longitude' => $this->address_longitude,
        ];
    }

    /**
     * Tom Select option row (project create/edit client picker).
     *
     * @return array<string, mixed>
     */
    public function toPickerOption(): array
    {
        $label = $this->searchLabel();

        return array_merge($this->toPickerArray(), [
            'label' => $label,
            'display' => $label,
            'phone_display' => $this->phone ? ClientProject::formatPhone($this->phone) : '',
        ]);
    }
}

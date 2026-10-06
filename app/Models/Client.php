<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

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
     * Most recently created linked project that has a service address.
     * Used when the directory client has no address of its own.
     */
    public function latestAddressProject(): HasOne
    {
        return $this->hasOne(ClientProject::class, 'client_id')->ofMany(
            ['id' => 'max'],
            function ($query) {
                $query->whereNotNull('client_address')
                    ->where('client_address', '!=', '');
            }
        );
    }

    public function tags(): BelongsToMany
    {
        return $this->belongsToMany(ClientTag::class)->orderBy('name');
    }

    /**
     * Address for list cards/tables: the client's stored address, or the
     * most recent linked project's address when the directory record is blank.
     */
    public function listAddress(): ?string
    {
        $own = trim((string) $this->address);
        if ($own !== '') {
            return $own;
        }

        $project = $this->latestAddressProject;
        if (! $project) {
            return null;
        }

        $fromProject = trim((string) ($project->getRawOriginal('client_address') ?: $project->client_address ?: ''));

        return $fromProject !== '' ? $fromProject : null;
    }

    /**
     * Google Maps search URL for {@see listAddress()}, using the same helper
     * as the project page map-pin link.
     */
    public function googleMapsSearchUrl(): ?string
    {
        $address = $this->listAddress();
        if ($address === null) {
            return null;
        }

        $own = trim((string) $this->address);
        $placeId = $own !== ''
            ? $this->address_place_id
            : ($this->latestAddressProject?->getRawOriginal('client_address_place_id')
                ?: $this->latestAddressProject?->client_address_place_id);

        return ClientProject::googleMapsSearchUrlFor($address, $placeId);
    }

    /**
     * Filter directory clients by name, email, phone, or address (including
     * linked project service addresses).
     *
     * @param  Builder<self>  $query
     */
    public function scopeSearch(Builder $query, string $q): Builder
    {
        $q = trim($q);
        if ($q === '') {
            return $query;
        }

        $like = '%'.$q.'%';
        $digits = preg_replace('/\D/', '', $q) ?? '';

        return $query->where(function (Builder $sub) use ($like, $digits) {
            $sub->where('name', 'like', $like)
                ->orWhere('email', 'like', $like)
                ->orWhere('email_secondary', 'like', $like)
                ->orWhere('phone', 'like', $like)
                ->orWhere('address', 'like', $like)
                ->orWhereHas('projects', function ($projects) use ($like) {
                    $projects->where('client_address', 'like', $like);
                });

            if (strlen($digits) >= 3) {
                $sub->orWhere('phone', 'like', '%'.$digits.'%');
            }
        });
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

        // Compare on the last 10 digits (matches phoneDigitsForSms, which
        // treats a bare 10-digit number and a "1"-prefixed 11-digit number
        // as the same SMS destination). Filtering happens in SQL — with a
        // formatting-agnostic strip of common punctuation — instead of
        // pulling every client row into PHP to compare one by one.
        $last10 = substr($digits, -10);

        return static::query()
            ->whereNotNull('phone')
            ->where('phone', '!=', '')
            ->whereRaw(
                "RIGHT(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(phone, ' ', ''), '-', ''), '(', ''), ')', ''), '.', ''), '+', ''), 10) = ?",
                [$last10]
            )
            ->first();
    }

    /**
     * Existing directory clients that look like the same person as the
     * given name/email/phone: exact email match, exact 10-digit phone
     * match, or an exact (case/whitespace-insensitive) name match. Used to
     * warn an admin before they create an obvious duplicate directory entry.
     *
     * @return \Illuminate\Support\Collection<int, self>
     */
    public static function findDuplicateCandidates(?string $name, ?string $email, ?string $phone, ?int $excludeId = null): \Illuminate\Support\Collection
    {
        $emailNorm = strtolower(trim((string) $email));
        $phoneDigits = preg_replace('/\D/', '', (string) $phone);
        $nameNorm = strtolower(trim((string) $name));

        if ($emailNorm === '' && $phoneDigits === '' && $nameNorm === '') {
            return collect();
        }

        return static::query()
            ->when($excludeId, fn ($q) => $q->where('id', '!=', $excludeId))
            ->where(function ($q) use ($emailNorm, $phoneDigits, $nameNorm) {
                $q->whereRaw('1 = 0');
                if ($emailNorm !== '') {
                    $q->orWhereRaw('LOWER(TRIM(email)) = ?', [$emailNorm]);
                }
                if (strlen($phoneDigits) === 10) {
                    $q->orWhere('phone', $phoneDigits);
                }
                if ($nameNorm !== '') {
                    $q->orWhereRaw('LOWER(TRIM(name)) = ?', [$nameNorm]);
                }
            })
            ->orderBy('name')
            ->limit(5)
            ->get();
    }

    /** Single-line label for search dropdowns. */
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
     * Admin search-select option row (project create/edit client picker).
     *
     * @return array<string, mixed>
     */
    public function toPickerOption(): array
    {
        $label = $this->searchLabel();

        return array_merge($this->toPickerArray(), [
            // String id keeps search-select option keys consistent in the browser.
            'id' => (string) $this->id,
            'label' => $label,
            'display' => $label,
            'phone_display' => $this->phone ? ClientProject::formatPhone($this->phone) : '',
        ]);
    }
}

<?php

namespace App\Models;

use App\Services\AdditionalCostsService;
use App\Services\BasementQuotationCalculator;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BasementQuotation extends Model
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
        'work_description',
        'payment_installments',
        'payment_percentages',
        'include_commute',
        'commute_trip_percent',
        'commute_estimated_trips',
        'commute_round_trip_cost',
        'commute_amount',
        'include_debris_removal',
        'debris_removal_amount',
        'additional_costs',
        'version',
        'linked_at',
        'client_intake_token',
        'client_intake_sent_at',
        'client_intake_submitted_at',
        'client_intake_answers',
        'client_filled_keys',
    ];

    protected $casts = [
        'inputs' => 'array',
        'calculation' => 'array',
        'is_draft' => 'boolean',
        'final_quote_total' => 'decimal:2',
        'payment_installments' => 'integer',
        'payment_percentages' => 'array',
        'include_commute' => 'boolean',
        'commute_trip_percent' => 'decimal:2',
        'commute_estimated_trips' => 'integer',
        'commute_round_trip_cost' => 'decimal:2',
        'commute_amount' => 'decimal:2',
        'include_debris_removal' => 'boolean',
        'debris_removal_amount' => 'decimal:2',
        'additional_costs' => 'array',
        'version' => 'integer',
        'linked_at' => 'datetime',
        'client_intake_sent_at' => 'datetime',
        'client_intake_submitted_at' => 'datetime',
        'client_intake_answers' => 'array',
        'client_filled_keys' => 'array',
    ];

    public function project(): BelongsTo
    {
        return $this->belongsTo(ClientProject::class, 'client_project_id');
    }

    public function isLinked(): bool
    {
        return $this->client_project_id !== null;
    }

    /**
     * Full basement and bathroom-only quotes can send a client intake form.
     */
    public function supportsClientIntake(): bool
    {
        $preset = BasementQuotationCalculator::normalizePreset(
            is_array($this->inputs) ? ($this->inputs['scope_preset'] ?? null) : null
        );

        return in_array($preset, ['full', 'bathroom'], true);
    }

    public function hasClientIntakeToken(): bool
    {
        return is_string($this->client_intake_token) && $this->client_intake_token !== '';
    }

    public function isClientIntakeSubmitted(): bool
    {
        return $this->client_intake_submitted_at !== null;
    }

    /**
     * @return list<string>
     */
    public function clientFilledKeys(): array
    {
        $keys = $this->client_filled_keys;

        return is_array($keys) ? array_values(array_map('strval', $keys)) : [];
    }

    public function clientFilled(string $key): bool
    {
        return in_array($key, $this->clientFilledKeys(), true);
    }

    public function ensureIntakeToken(): string
    {
        if ($this->hasClientIntakeToken()) {
            return (string) $this->client_intake_token;
        }

        do {
            $token = bin2hex(random_bytes(32));
        } while (static::query()->where('client_intake_token', $token)->exists());

        $this->client_intake_token = $token;
        $this->save();

        return $token;
    }

    public function clientIntakeUrl(): string
    {
        return route('public.basement-intake.show', ['token' => $this->ensureIntakeToken()]);
    }

    public function intakeClientEmail(): ?string
    {
        $project = $this->project;
        if (! $project) {
            return null;
        }
        $email = trim((string) ($project->client_email ?? ''));

        return $email !== '' ? $email : null;
    }

    public function areaSqft(): float
    {
        $calc = is_array($this->calculation) ? $this->calculation : [];
        $scope = is_array($calc['scope'] ?? null) ? $calc['scope'] : [];
        $inputs = is_array($this->inputs) ? $this->inputs : [];

        return round((float) ($scope['area_sqft'] ?? $inputs['area_sqft'] ?? 0), 2);
    }

    public function displayTitle(): string
    {
        return 'Basement Renovation — '.$this->quote_number;
    }

    /**
     * Short client-facing job label for quotation PDFs (scope selected in the wizard).
     */
    public function jobLabelForPdf(): string
    {
        $inputs = $this->inputs;
        if (is_string($inputs)) {
            $decoded = json_decode($inputs, true);
            $inputs = is_array($decoded) ? $decoded : [];
        }
        if (! is_array($inputs)) {
            $inputs = [];
        }

        return BasementQuotationCalculator::finishLabel($inputs);
    }

    public function labourPreTaxAmountForProjectQuotation(): float
    {
        $calc = is_array($this->calculation) ? $this->calculation : [];
        $summary = is_array($calc['summary'] ?? null) ? $calc['summary'] : [];

        return max(0, round((float) ($summary['labor_pre_hst'] ?? 0), 2));
    }

    public function materialsPreTaxAmountForProjectQuotation(): float
    {
        $calc = is_array($this->calculation) ? $this->calculation : [];
        $summary = is_array($calc['summary'] ?? null) ? $calc['summary'] : [];

        return max(0, round((float) ($summary['materials_pre_hst'] ?? 0), 2));
    }

    public function includesMaterialCharge(): bool
    {
        return $this->materialsPreTaxAmountForProjectQuotation() > 0;
    }

    public function summaryDescription(): string
    {
        $area = $this->areaSqft();
        $inputs = is_array($this->inputs) ? $this->inputs : [];
        $finish = BasementQuotationCalculator::finishLabel($inputs);

        if ($area > 0) {
            return sprintf('Basement Renovation – %s sq ft, %s', number_format($area, 0), $finish);
        }

        return 'Basement Renovation – '.$finish;
    }

    public function projectTitleSuffix(): string
    {
        return $this->summaryDescription();
    }

    public function scopeDescriptionForPdf(): string
    {
        $inputs = is_array($this->inputs) ? $this->inputs : [];
        $calc = is_array($this->calculation) ? $this->calculation : [];
        $scope = is_array($calc['scope'] ?? null) ? $calc['scope'] : [];

        $lines = [];
        $area = round((float) ($scope['area_sqft'] ?? $inputs['area_sqft'] ?? 0), 0);
        $height = round((float) ($scope['ceiling_height_ft'] ?? $inputs['ceiling_height_ft'] ?? 0), 2);
        $rooms = (int) ($inputs['room_count'] ?? 0);

        $lines[] = 'Basement renovation'.($area > 0 ? ' — '.number_format($area, 0).' sq ft' : '');
        if ($height > 0) {
            $lines[] = 'Ceiling height: '.$height.' ft';
        }
        if ($rooms > 0) {
            $lines[] = 'Finished rooms / zones: '.$rooms;
        }
        if (! empty($inputs['include_bathroom'])) {
            $type = ($inputs['bath_type'] ?? '') === 'half' ? 'Half bath' : 'Full bath';
            $lines[] = 'Bathroom: '.$type;
        }

        foreach (['electrical_lines' => 'Electrical', 'plumbing_lines' => 'Plumbing'] as $key => $heading) {
            $rows = AdditionalCostsService::normalizeRows($inputs[$key] ?? [], $heading.' work');
            foreach ($rows as $row) {
                $label = trim((string) ($row['description'] ?? ''));
                if ($label !== '') {
                    $lines[] = '  • '.$heading.': '.$label;
                }
            }
        }

        $notes = trim((string) ($inputs['existing_notes'] ?? ''));
        if ($notes !== '') {
            $lines[] = 'Existing conditions: '.$notes;
        }

        foreach ($calc['labor'] ?? [] as $row) {
            if (! is_array($row) || (float) ($row['hours'] ?? 0) <= 0) {
                continue;
            }
            $label = trim((string) ($row['label'] ?? ''));
            if ($label !== '') {
                $lines[] = '  • '.$label;
            }
        }

        return \App\Support\QuotationWorkDescriptionHtml::combineAutoScopeWithWork(
            $lines,
            $this->work_description
        );
    }

    public static function generateNumber(): string
    {
        $year = (int) date('Y');
        $latest = static::query()
            ->where('quote_number', 'like', "BR-{$year}-%")
            ->orderByDesc('quote_number')
            ->first();

        if ($latest) {
            $parts = explode('-', $latest->quote_number);
            $next = (int) end($parts) + 1;
        } else {
            $next = random_int(100, 400);
        }

        return sprintf('BR-%d-%04d', $year, $next);
    }
}

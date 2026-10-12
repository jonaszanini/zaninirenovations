<?php

namespace App\Models;

use App\Services\AdditionalCostsService;
use App\Services\BasementQuotationCalculator;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BasementQuotation extends Model
{
    use \App\Models\Concerns\HasClientEmailMessage;
    use \App\Models\Concerns\HasPdfFoldVersion;
    use \App\Models\Concerns\HasReplyToAcceptChoice;

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

    public function permitPreTaxAmountForProjectQuotation(): float
    {
        $calc = is_array($this->calculation) ? $this->calculation : [];
        $summary = is_array($calc['summary'] ?? null) ? $calc['summary'] : [];
        if (array_key_exists('permit_pre_hst', $summary)) {
            return max(0, round((float) $summary['permit_pre_hst'], 2));
        }

        foreach ($calc['materials'] ?? [] as $row) {
            if (! is_array($row) || ($row['key'] ?? '') !== 'building_permit') {
                continue;
            }

            return max(0, round((float) ($row['amount'] ?? 0), 2));
        }

        return 0.0;
    }

    public function materialsPreTaxAmountForProjectQuotation(): float
    {
        $calc = is_array($this->calculation) ? $this->calculation : [];
        $summary = is_array($calc['summary'] ?? null) ? $calc['summary'] : [];
        $materials = max(0, round((float) ($summary['materials_pre_hst'] ?? 0), 2));
        if (! array_key_exists('permit_pre_hst', $summary)) {
            $materials = max(0, round($materials - $this->permitPreTaxAmountForProjectQuotation(), 2));
        }

        return $materials;
    }

    public function includesMaterialCharge(): bool
    {
        return $this->materialsPreTaxAmountForProjectQuotation() > 0
            || $this->permitPreTaxAmountForProjectQuotation() > 0;
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
        $html = $this->scopeHtmlForPdf();
        $work = \App\Support\QuotationWorkDescriptionHtml::normalizeForStorage($this->work_description);
        if ($work === null) {
            return $html;
        }
        if (! \App\Support\QuotationWorkDescriptionHtml::isHtml($work)) {
            return $html.'<p>'.nl2br(e($work), false).'</p>';
        }

        return $html.$work;
    }

    /**
     * Grouped two-column scope block (DomPDF-safe table).
     * Trades follow work sequence and flow left-to-right, row by row.
     * Page breaks are allowed between rows, not inside a trade group.
     */
    protected function scopeHtmlForPdf(): string
    {
        $inputs = is_array($this->inputs) ? $this->inputs : [];
        $calc = is_array($this->calculation) ? $this->calculation : [];
        $scope = is_array($calc['scope'] ?? null) ? $calc['scope'] : [];

        $intro = [];
        $area = round((float) ($scope['area_sqft'] ?? $inputs['area_sqft'] ?? 0), 0);
        $height = round((float) ($scope['ceiling_height_ft'] ?? $inputs['ceiling_height_ft'] ?? 0), 2);
        $rooms = (int) ($inputs['room_count'] ?? 0);

        $intro[] = 'Basement renovation'.($area > 0 ? ' — '.number_format($area, 0).' sq ft' : '');
        if ($height > 0) {
            $intro[] = 'Ceiling height: '.$height.' ft';
        }
        if ($rooms > 0) {
            $intro[] = 'Finished rooms / zones: '.$rooms;
        }
        if (! empty($inputs['include_bathroom'])) {
            $type = ($inputs['bath_type'] ?? '') === 'half' ? 'Half bath' : 'Full bath';
            $intro[] = 'Bathroom: '.$type;
        }
        $notes = trim((string) ($inputs['existing_notes'] ?? ''));
        if ($notes !== '') {
            $intro[] = 'Existing conditions: '.$notes;
        }

        $groups = [];
        foreach (['electrical_lines' => 'Electrical', 'plumbing_lines' => 'Plumbing'] as $key => $heading) {
            $rows = AdditionalCostsService::normalizeRows($inputs[$key] ?? [], $heading.' work');
            foreach ($rows as $row) {
                $label = trim((string) ($row['description'] ?? ''));
                if ($label !== '') {
                    $groups[$heading][] = $label;
                }
            }
        }

        foreach ($calc['labor'] ?? [] as $row) {
            if (! is_array($row) || (float) ($row['hours'] ?? 0) <= 0) {
                continue;
            }
            $label = trim((string) ($row['label'] ?? ''));
            if ($label === '') {
                continue;
            }
            $section = (string) ($row['section'] ?? 'finishing');
            $heading = BasementQuotationCalculator::SECTIONS[$section] ?? ucfirst(str_replace('_', ' ', $section));
            $groups[$heading][] = $label;
        }

        if (! empty($inputs['include_building_permit'])) {
            $groups['Final Details & Cleanup'][] = 'Building permit (optional, extra charge)';
        }

        $ordered = [];
        foreach (BasementQuotationCalculator::SECTIONS as $heading) {
            if (! empty($groups[$heading])) {
                $ordered[$heading] = $groups[$heading];
                unset($groups[$heading]);
            }
        }
        foreach ($groups as $heading => $items) {
            $ordered[$heading] = $items;
        }
        $groups = $ordered;

        $introHtml = '';
        foreach ($intro as $line) {
            $introHtml .= '<p style="margin:0 0 2pt;line-height:1.25;">'.e($line).'</p>';
        }

        if ($groups === []) {
            return $introHtml;
        }

        $blocks = [];
        foreach ($groups as $heading => $items) {
            $block = '<div class="br-pdf-scope-group" style="page-break-inside:avoid;break-inside:avoid;">';
            $block .= '<p style="margin:0 0 1pt;font-weight:700;line-height:1.2;">'.e($heading).'</p>';
            $block .= '<ul style="margin:0 0 3pt 11pt;padding:0;line-height:1.2;">';
            foreach ($items as $item) {
                $block .= '<li style="margin:0;padding:0;line-height:1.2;">'.e($item).'</li>';
            }
            $block .= '</ul></div>';
            $blocks[] = $block;
        }

        // Consecutive pairs so reading order is work sequence across two columns.
        // Breaks are allowed between rows; each group stays together.
        $html = $introHtml;
        $html .= '<table class="br-pdf-scope-cols" width="100%" cellpadding="0" cellspacing="0" border="0" style="border:none;margin:0;border-collapse:collapse;page-break-inside:auto;">';
        $count = count($blocks);
        for ($i = 0; $i < $count; $i += 2) {
            $html .= '<tr style="page-break-inside:auto;">';
            $html .= '<td width="50%" valign="top" style="border:none;padding:0 8pt 2pt 0;vertical-align:top;page-break-inside:avoid;">'.($blocks[$i] ?? '').'</td>';
            $html .= '<td width="50%" valign="top" style="border:none;padding:0 0 2pt 4pt;vertical-align:top;page-break-inside:avoid;">'.($blocks[$i + 1] ?? '').'</td>';
            $html .= '</tr>';
        }
        $html .= '</table>';

        return $html;
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

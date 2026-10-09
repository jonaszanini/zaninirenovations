<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MillworkQuotation extends Model
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
        'include_debris_removal',
        'debris_removal_amount',
        'additional_costs',
        'include_commute',
        'commute_trip_percent',
        'commute_estimated_trips',
        'commute_round_trip_cost',
        'commute_amount',
        'version',
        'linked_at',
    ];

    protected $casts = [
        'inputs' => 'array',
        'calculation' => 'array',
        'is_draft' => 'boolean',
        'final_quote_total' => 'decimal:2',
        'payment_installments' => 'integer',
        'payment_percentages' => 'array',
        'include_debris_removal' => 'boolean',
        'debris_removal_amount' => 'decimal:2',
        'additional_costs' => 'array',
        'include_commute' => 'boolean',
        'commute_trip_percent' => 'decimal:2',
        'commute_estimated_trips' => 'integer',
        'commute_round_trip_cost' => 'decimal:2',
        'commute_amount' => 'decimal:2',
        'version' => 'integer',
        'linked_at' => 'datetime',
    ];

    public function project(): BelongsTo
    {
        return $this->belongsTo(ClientProject::class, 'client_project_id');
    }

    public function isLinked(): bool
    {
        return $this->client_project_id !== null;
    }

    public function sheetCount(): int
    {
        $calc = is_array($this->calculation) ? $this->calculation : [];
        $cut = is_array($calc['cut_plan'] ?? null) ? $calc['cut_plan'] : [];

        return max(0, (int) ($cut['sheet_count'] ?? 0));
    }

    /**
     * v1 snapshots (no unplaced key) stay downloadable. Live v2 plans with
     * leftover parts block the client PDF.
     */
    public function hasUnplacedCutParts(): bool
    {
        $calc = is_array($this->calculation) ? $this->calculation : [];
        $cut = is_array($calc['cut_plan'] ?? null) ? $calc['cut_plan'] : [];
        $unplaced = $cut['unplaced'] ?? null;

        return is_array($unplaced) && $unplaced !== [];
    }

    public function cutPlanVersion(): int
    {
        $calc = is_array($this->calculation) ? $this->calculation : [];
        $cut = is_array($calc['cut_plan'] ?? null) ? $calc['cut_plan'] : [];

        return (int) ($cut['version'] ?? 1);
    }

    public function displayTitle(): string
    {
        return 'Custom Millwork — '.$this->quote_number;
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

        $materials = max(0, (float) ($summary['materials_pre_hst'] ?? 0));
        $markup = max(0, (float) ($summary['markup_amount'] ?? 0));

        return round($materials + $markup, 2);
    }

    public function includesMaterialCharge(): bool
    {
        return $this->materialsPreTaxAmountForProjectQuotation() > 0;
    }

    public function summaryDescription(): string
    {
        $calc = is_array($this->calculation) ? $this->calculation : [];
        $scope = is_array($calc['scope'] ?? null) ? $calc['scope'] : [];
        $label = trim((string) ($scope['piece_summary'] ?? ''));
        if ($label !== '') {
            return 'Custom millwork – '.$label;
        }

        $n = (int) ($scope['piece_count'] ?? 0);

        return $n > 0
            ? 'Custom millwork – '.$n.' piece'.($n === 1 ? '' : 's')
            : 'Custom millwork';
    }

    public function projectTitleSuffix(): string
    {
        return $this->summaryDescription();
    }

    public function scopeDescriptionForPdf(): string
    {
        $inputs = is_array($this->inputs) ? $this->inputs : [];
        $calc = is_array($this->calculation) ? $this->calculation : [];
        $summary = is_array($calc['summary'] ?? null) ? $calc['summary'] : [];

        $included = [];
        if (empty($inputs['client_provides_all'])) {
            $included[] = 'supply';
        }
        $included[] = 'shop build';
        $hasHardware = false;
        $hasFinish = false;
        foreach (is_array($inputs['pieces'] ?? null) ? $inputs['pieces'] : [] as $piece) {
            if (! is_array($piece)) {
                continue;
            }
            $type = \App\Services\Millwork\MillworkPartBuilder::normalizeType((string) ($piece['type'] ?? 'shelf'));
            if (in_array($type, ['cabinet', 'cabinet_door', 'vanity', 'closet', 'bench'], true)) {
                $hasHardware = true;
            }
            $finish = \App\Services\Millwork\MillworkPartBuilder::normalizeFinish((string) ($piece['finish'] ?? 'none'));
            if ($finish !== 'none') {
                $hasFinish = true;
            }
        }
        if ($hasHardware) {
            $included[] = 'hardware';
        }
        if ($hasFinish) {
            $included[] = 'finishing';
        }
        if ((float) ($summary['delivery_amount'] ?? $inputs['delivery_amount'] ?? 0) > 0) {
            $included[] = 'delivery';
        }
        if (! array_key_exists('include_install', $inputs) || ! empty($inputs['include_install'])) {
            $included[] = 'on-site install';
        }
        $includeSentence = 'Includes '.self::joinAnd($included).'.';

        $lines = ['Custom millwork — cabinets, closets, built-ins, and finish carpentry.'];
        foreach (is_array($inputs['pieces'] ?? null) ? $inputs['pieces'] : [] as $piece) {
            if (! is_array($piece)) {
                continue;
            }
            $line = \App\Services\Millwork\MillworkPartBuilder::clientScopeLine($piece);
            if ($line === '') {
                continue;
            }
            $lines[] = $line.' '.$includeSentence;
        }

        return \App\Support\QuotationWorkDescriptionHtml::combineAutoScopeWithWork(
            $lines,
            $this->work_description
        );
    }

    /**
     * @param  list<string>  $parts
     */
    private static function joinAnd(array $parts): string
    {
        $parts = array_values(array_filter($parts, fn ($p) => trim((string) $p) !== ''));
        $n = count($parts);
        if ($n === 0) {
            return '';
        }
        if ($n === 1) {
            return $parts[0];
        }
        if ($n === 2) {
            return $parts[0].' and '.$parts[1];
        }

        return implode(', ', array_slice($parts, 0, -1)).', and '.$parts[$n - 1];
    }

    public static function generateNumber(): string
    {
        $year = (int) date('Y');
        $latest = static::query()
            ->where('quote_number', 'like', "MW-{$year}-%")
            ->orderByDesc('quote_number')
            ->first();

        if ($latest) {
            $parts = explode('-', $latest->quote_number);
            $next = (int) end($parts) + 1;
        } else {
            $next = random_int(100, 400);
        }

        return sprintf('MW-%d-%04d', $year, $next);
    }
}

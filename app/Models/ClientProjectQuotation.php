<?php

namespace App\Models;

use App\Services\DebrisRemovalExpenseService;
use App\Services\QuotationPaymentSchedule;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * ClientProjectQuotation — structured quotation/estimate attached to a
 * ClientProject. Line items are synced from Fence / Standard / Paving / Stairs
 * calculators; legacy manual rows remain as read-only snapshots.
 *
 * A project may have multiple quotations. Use {@see ClientProject::quotations()}.
 */
class ClientProjectQuotation extends Model
{
    public const TYPE_QUOTATION = 'quotation';
    public const TYPE_ESTIMATE = 'estimate';

    public const HST_RATE = 0.13;

    public const STATUS_DRAFT = 'draft';

    public const STATUS_SENT = 'sent';

    public const STATUS_ACCEPTED = 'accepted';

    public const STATUS_REJECTED = 'rejected';

    /** Legacy: line items were edited on the project before calculator-only flow. */
    public const SOURCE_MANUAL = 'manual';

    public const SOURCE_FENCE = 'fence';

    public const SOURCE_PAVING = 'paving';

    public const SOURCE_STANDARD = 'standard';

    public const SOURCE_STAIRS = 'stairs';

    public const SOURCE_PAINTING = 'painting';

    public const SOURCE_FLOORING = 'flooring';

    public const SOURCE_TILE = 'tile';

    public const SOURCE_LOW_DECK = 'low_deck';

    public const SOURCE_BASEMENT = 'basement';

    public const SOURCE_SHED = 'shed';

    public const SOURCE_TRIMMING = 'trimming';

    public const SOURCE_MILLWORK = 'millwork';

    protected $fillable = [
        'client_project_id',
        'title',
        'status',
        'total_amount',
        'subtotal_override',
        'source_type',
        'source_id',
        'source_snapshot',
        'type',
        'labour',
        'material',
        'items',
        'work_description',
        'payment_installments',
        'pdf_photo_ids',
        'quotation_number',
        'pdf_path',
        'payment_installments',
        'payment_percentages',
        'sent_at',
        'version',
    ];

    protected $casts = [
        'labour' => 'boolean',
        'material' => 'boolean',
        'items' => 'array',
        'source_snapshot' => 'array',
        'pdf_photo_ids' => 'array',
        'sent_at' => 'datetime',
        'payment_installments' => 'integer',
        'payment_percentages' => 'array',
        'total_amount' => 'decimal:2',
        'subtotal_override' => 'decimal:2',
    ];

    public function project(): BelongsTo
    {
        return $this->belongsTo(ClientProject::class, 'client_project_id');
    }

    /**
     * Defensive cleanup: when a quotation row is deleted directly (i.e. not
     * through the parent project's cascade, which doesn't fire model events),
     * also remove the latest PDF on disk.
     *
     * The parent ClientProject's deleting hook handles every version when a
     * whole project is deleted; this only ensures we don't orphan a single
     * file when a quotation is deleted on its own.
     */
    protected static function booted(): void
    {
        static::deleting(function (self $quotation): void {
            if (filled($quotation->pdf_path)) {
                Storage::disk('local')->delete($quotation->pdf_path);
            }
        });

        static::saving(function (self $quotation): void {
            if ($quotation->hasContent()) {
                $quotation->total_amount = $quotation->grandTotal();
            }
        });
    }

    /** Has the quotation been emailed to the client at least once? */
    public function isSent(): bool
    {
        return $this->sent_at !== null;
    }

    public function isEstimate(): bool
    {
        return $this->type === self::TYPE_ESTIMATE;
    }

    /**
     * Rows created by the old manual line-item editor (`source_type` null
     * or explicit `manual`). Read-only in the admin UI; edit in calculators.
     */
    public function isLegacyManualSource(): bool
    {
        return $this->source_type === null || $this->source_type === self::SOURCE_MANUAL;
    }

    /** Swap QUO ↔ EST prefix when the document type changes. */
    public static function swapNumberPrefixForType(?string $number, string $newType): string
    {
        if (! $number) {
            return static::generateNumber($newType);
        }
        $newPrefix = $newType === self::TYPE_ESTIMATE ? 'EST' : 'QUO';

        return preg_replace('/^[A-Z]+/', $newPrefix, $number);
    }

    /** @return list<int> Photo IDs selected for the client PDF, in display order. */
    public function selectedPdfPhotoIds(): array
    {
        $raw = $this->pdf_photo_ids;
        if (! is_array($raw)) {
            return [];
        }

        return array_values(array_filter(array_map('intval', $raw), fn (int $id) => $id > 0));
    }

    /**
     * Project images to append to the quotation PDF, in saved order.
     *
     * @return Collection<int, ClientProjectPhoto>
     */
    public function pdfPhotosForRendering(?ClientProject $project = null): Collection
    {
        $ids = $this->selectedPdfPhotoIds();
        if ($ids === []) {
            return collect();
        }

        $project ??= $this->relationLoaded('project') ? $this->project : $this->project()->with('photos')->first();
        if (! $project) {
            return collect();
        }

        if (! $project->relationLoaded('photos')) {
            $project->load('photos');
        }

        $byId = $project->photos->keyBy('id');
        $ordered = collect();
        foreach ($ids as $id) {
            $photo = $byId->get($id);
            if ($photo && $photo->isImage()) {
                $ordered->push($photo);
            }
        }

        return $ordered;
    }

    /** Human-friendly type label ("Quotation" or "Estimate"). */
    public function typeLabel(): string
    {
        return $this->isEstimate() ? 'Estimate' : 'Quotation';
    }

    /**
     * Heading line from the linked {@see StandardQuotation}. Prefer
     * {@see $source_snapshot} when in sync; otherwise read the live SQ row
     * (e.g. after a DB backfill before the next save).
     */
    public function standardDisplayTitle(): ?string
    {
        if ($this->source_type !== self::SOURCE_STANDARD) {
            return null;
        }
        $snap = $this->source_snapshot;
        if (is_array($snap)) {
            $t = trim((string) ($snap['display_title'] ?? ''));
            if ($t !== '') {
                return $t;
            }
        }
        $sourceId = (int) ($this->source_id ?? 0);
        if ($sourceId <= 0) {
            return null;
        }
        $t = trim((string) (StandardQuotation::query()->whereKey($sourceId)->value('display_title') ?? ''));

        return $t !== '' ? $t : null;
    }

    /**
     * Card heading for linked calculator rows. Stairs titles follow the selected material
     * (e.g. "Vinyl Stairs — ST-2026-0163"); other sources use the stored title column.
     */
    public function resolvedTitle(): string
    {
        if ($this->source_type === self::SOURCE_STAIRS && $this->source_id) {
            $stairs = StairsQuotation::query()->find($this->source_id);
            if ($stairs) {
                return $stairs->displayTitle();
            }
        }

        if ($this->source_type === self::SOURCE_PAINTING && $this->source_id) {
            $painting = PaintingQuotation::query()->find($this->source_id);
            if ($painting) {
                return $painting->displayTitle();
            }
        }

        if ($this->source_type === self::SOURCE_FLOORING && $this->source_id) {
            $flooring = FlooringQuotation::query()->find($this->source_id);
            if ($flooring) {
                return $flooring->displayTitle();
            }
        }

        if ($this->source_type === self::SOURCE_TILE && $this->source_id) {
            $tile = TileQuotation::query()->find($this->source_id);
            if ($tile) {
                return $tile->displayTitle();
            }
        }

        if ($this->source_type === self::SOURCE_BASEMENT && $this->source_id) {
            $basement = BasementQuotation::query()->find($this->source_id);
            if ($basement) {
                return $basement->displayTitle();
            }
        }

        if ($this->source_type === self::SOURCE_SHED && $this->source_id) {
            $shed = ShedQuotation::query()->find($this->source_id);
            if ($shed) {
                return $shed->displayTitle();
            }
        }

        if ($this->source_type === self::SOURCE_TRIMMING && $this->source_id) {
            $trimming = TrimmingQuotation::query()->find($this->source_id);
            if ($trimming) {
                return $trimming->displayTitle();
            }
        }

        if ($this->source_type === self::SOURCE_MILLWORK && $this->source_id) {
            $millwork = MillworkQuotation::query()->find($this->source_id);
            if ($millwork) {
                return $millwork->displayTitle();
            }
        }

        $stored = trim((string) ($this->title ?? ''));

        return $stored !== '' ? $stored : $this->typeLabel();
    }

    /**
     * Short client-facing job label for the Prepared for block on quotation PDFs.
     * Uses the calculator scope (e.g. basement "New wall"), never the parent project name.
     */
    public function jobLabelForPdf(): string
    {
        $source = $this->calculatorModelForLabourHstSetAside();
        if ($source instanceof BasementQuotation) {
            return $source->jobLabelForPdf();
        }

        if ($source instanceof StandardQuotation) {
            $summary = trim($source->summaryDescription());
            if ($summary !== '') {
                return $summary;
            }
        }

        if ($source !== null && method_exists($source, 'displayTitle')) {
            $fromDisplay = self::stripDisplayTitleQuoteNumber((string) $source->displayTitle());
            if ($fromDisplay !== '') {
                return $fromDisplay;
            }
        }

        $typeJob = match ($this->source_type) {
            self::SOURCE_FENCE => 'Fence',
            self::SOURCE_PAVING => 'Paving',
            self::SOURCE_LOW_DECK => 'Low deck',
            default => null,
        };
        if ($typeJob !== null) {
            return $typeJob;
        }

        $fromTitle = self::stripDisplayTitleQuoteNumber($this->resolvedTitle());
        if ($fromTitle !== '' && strcasecmp($fromTitle, $this->typeLabel()) !== 0) {
            return $fromTitle;
        }

        $stored = trim((string) ($this->title ?? ''));
        if ($stored !== '') {
            return self::stripDisplayTitleQuoteNumber($stored);
        }

        return $this->typeLabel();
    }

    /**
     * Remove trailing " — QUO-2026-0001" / " — BR-2026-0280" style suffixes from titles.
     */
    public static function stripDisplayTitleQuoteNumber(string $title): string
    {
        $trimmed = trim($title);
        if ($trimmed === '') {
            return '';
        }

        $stripped = preg_replace('/\s*[—–-]\s*[A-Z]{1,4}-\d{4}-\d+\s*$/u', '', $trimmed);

        return trim((string) $stripped);
    }

    /**
     * Free-form scope text for the quotation PDF "Description of the work" block.
     */
    public function workDescriptionForPdf(): string
    {
        if ($this->source_type === self::SOURCE_STANDARD && $this->source_id) {
            $sq = StandardQuotation::query()->find($this->source_id);
            if ($sq) {
                return trim((string) ($sq->work_description ?? ''));
            }
        }

        if ($this->source_type === self::SOURCE_PAVING && $this->source_id) {
            $pq = PavingQuotation::query()->find($this->source_id);
            if ($pq) {
                return trim((string) ($pq->work_description ?? ''));
            }
        }

        if ($this->source_type === self::SOURCE_FENCE && $this->source_id) {
            $fq = FenceQuotation::query()->find($this->source_id);
            if ($fq) {
                return trim((string) ($fq->work_description ?? ''));
            }
        }

        if ($this->source_type === self::SOURCE_LOW_DECK && $this->source_id) {
            $ld = LowDeckQuotation::query()->find($this->source_id);
            if ($ld) {
                return trim((string) ($ld->work_description ?? ''));
            }
        }

        if ($this->source_type === self::SOURCE_PAINTING && $this->source_id) {
            $pq = PaintingQuotation::query()->find($this->source_id);
            if ($pq) {
                return $pq->scopeDescriptionForPdf();
            }
        }

        if ($this->source_type === self::SOURCE_FLOORING && $this->source_id) {
            $fq = FlooringQuotation::query()->find($this->source_id);
            if ($fq) {
                return $fq->scopeDescriptionForPdf();
            }
        }

        if ($this->source_type === self::SOURCE_BASEMENT && $this->source_id) {
            $bq = BasementQuotation::query()->find($this->source_id);
            if ($bq) {
                return $bq->scopeDescriptionForPdf();
            }
        }

        if ($this->source_type === self::SOURCE_SHED && $this->source_id) {
            $sq = ShedQuotation::query()->find($this->source_id);
            if ($sq) {
                return $sq->scopeDescriptionForPdf();
            }
        }

        if ($this->source_type === self::SOURCE_TRIMMING && $this->source_id) {
            $tq = TrimmingQuotation::query()->find($this->source_id);
            if ($tq) {
                return $tq->scopeDescriptionForPdf();
            }
        }

        if ($this->source_type === self::SOURCE_MILLWORK && $this->source_id) {
            $mq = MillworkQuotation::query()->find($this->source_id);
            if ($mq) {
                return $mq->scopeDescriptionForPdf();
            }
        }

        if ($this->source_type === self::SOURCE_TILE && $this->source_id) {
            $tq = TileQuotation::query()->find($this->source_id);
            if ($tq) {
                return $tq->scopeDescriptionForPdf();
            }
        }

        return trim((string) ($this->work_description ?? ''));
    }

    /** True when the PDF description has visible text (ignores empty TinyMCE markup). */
    public function hasWorkDescriptionForPdf(): bool
    {
        $raw = $this->workDescriptionForPdf();
        if ($raw === '') {
            return false;
        }

        $plain = trim(html_entity_decode(strip_tags($raw), ENT_QUOTES | ENT_HTML5, 'UTF-8'));

        return $plain !== '';
    }

    /** True when the stored description already contains HTML tags (e.g. TinyMCE). */
    public function workDescriptionForPdfIsHtml(): bool
    {
        return \App\Support\QuotationWorkDescriptionHtml::isHtml($this->workDescriptionForPdf());
    }

    /** Document number prefix matching the current type. */
    public function numberPrefix(): string
    {
        return $this->isEstimate() ? 'EST' : 'QUO';
    }

    /** Remove internal calculator cross-reference suffixes from client-facing text. */
    public static function stripCalculatorReferenceSuffix(string $description): string
    {
        return trim((string) preg_replace('/\s*\(see\s+[A-Z]{2,}-\d{4}-\d+\)\s*$/iu', '', $description));
    }

    /** Sanitised line items: drops blank rows and coerces amounts to float. */
    public function cleanItems(): array
    {
        $items = is_array($this->items) ? $this->items : [];

        $clean = [];
        foreach ($items as $row) {
            $description = self::stripCalculatorReferenceSuffix(trim((string) ($row['description'] ?? '')));
            $amount = (float) ($row['amount'] ?? 0);
            // Skip rows that are completely empty (no text AND zero amount).
            if ($description === '' && $amount == 0.0) {
                continue;
            }
            $applyHst = !array_key_exists('apply_hst', $row)
                || filter_var($row['apply_hst'], FILTER_VALIDATE_BOOLEAN);
            $hstIncluded = filter_var($row['hst_included'] ?? false, FILTER_VALIDATE_BOOLEAN);

            $cleanRow = [
                'description' => $description,
                'amount' => round($amount, 2),
                'apply_hst' => $applyHst,
            ];
            if ($hstIncluded) {
                $cleanRow['hst_included'] = true;
            }
            if (array_key_exists('hst_taxable_amount', $row)) {
                $cleanRow['hst_taxable_amount'] = round((float) $row['hst_taxable_amount'], 2);
            }
            if (! empty($row['is_heading'])) {
                $cleanRow['is_heading'] = true;
            }
            $clean[] = $cleanRow;
        }

        return $this->normalizeStairsLinkedLineItems($clean);
    }

    /**
     * Line items rendered on the client PDF. Standard quotations can show
     * either the linked summary lines or a full material/labour breakdown.
     * Commute fuel is never shown as its own row: the pre-tax amount is split
     * evenly across each job's Labour line (remainder cents on the last job)
     * so HST stays in the footer. Additional costs are rolled into the
     * materials line. Debris removal is never its own row: the entered
     * HST-inclusive amount is converted to pre-tax and added to each job's
     * Labour line (or the combined Labour and material line).
     *
     * @return list<array{description: string, amount: float, apply_hst: bool}>
     */
    public function pdfLineItems(): array
    {
        if ($this->source_type === self::SOURCE_STANDARD && $this->source_id) {
            $sq = StandardQuotation::query()->find($this->source_id);
            if ($sq) {
                if ($sq->usesDetailedPdf()) {
                    $detailed = $sq->detailedPdfLineItems();
                    $items = $detailed !== [] ? $detailed : $this->cleanItems();
                } elseif ($sq->usesCombinedPdf()) {
                    $combined = $sq->combinedPdfLineItems();
                    $items = $combined !== [] ? $combined : $this->cleanItems();
                } elseif ($sq->usesSummaryPdf()) {
                    $summary = $sq->summaryPdfLineItems();
                    $items = $summary !== [] ? $summary : $this->cleanItems();
                } else {
                    $items = $this->applyStandardSummaryLineWording($this->cleanItems(), $sq);
                }
            } else {
                $items = $this->cleanItems();
            }
        } else {
            $items = $this->cleanItems();
            if ($this->source_type === self::SOURCE_PAVING) {
                $items = $this->normalizePavingPdfPreTaxLines($items);
            }
        }

        return $this->normalizeDebrisRemovalForClientFacing(
            $this->normalizeInternalExpensesForClientFacing($items)
        );
    }

    /**
     * @param  list<array{description: string, amount: float, apply_hst: bool}>  $items
     * @return list<array{description: string, amount: float, apply_hst: bool}>
     */
    protected function applyStandardSummaryLineWording(array $items, StandardQuotation $sq): array
    {
        $wording = $sq->pdfSummaryLineDescription();
        $bases = ['Standard quotation', 'Labour and material', 'Labour', 'Material'];

        foreach ($items as &$row) {
            $desc = (string) ($row['description'] ?? '');
            foreach ($bases as $base) {
                if ($desc === $base) {
                    $row['description'] = $wording;
                    break;
                }
                $suffix = ' — ';
                if (str_starts_with($desc, $base.$suffix)) {
                    $row['description'] = $wording.substr($desc, strlen($base));
                    break;
                }
            }
        }
        unset($row);

        return $items;
    }

    /** Pre-tax subtotal for {@see pdfLineItems()}. */
    public function pdfSubtotal(): float
    {
        $sum = 0.0;
        foreach ($this->pdfLineItems() as $row) {
            if (! empty($row['is_heading'])) {
                continue;
            }
            $sum += (float) ($row['amount'] ?? 0);
        }

        return round($sum, 2);
    }

    /** HST for {@see pdfLineItems()}. */
    public function pdfHst(): float
    {
        $sum = 0.0;
        foreach ($this->pdfLineItems() as $row) {
            if (! empty($row['is_heading'])) {
                continue;
            }
            $sum += self::lineHstAmount($row);
        }

        return round($sum, 2);
    }

    public function pdfGrandTotal(): float
    {
        return round($this->pdfSubtotal() + $this->pdfHst(), 2);
    }

    /**
     * Client PDFs: fold debris into labour (pre-tax) so HST stays in the footer.
     * Tile quotations also label materials as "Materials".
     *
     * @param  list<array<string, mixed>>  $items
     * @return list<array<string, mixed>>
     */
    protected function normalizeDebrisRemovalForClientFacing(array $items): array
    {
        $debrisGross = 0.0;
        $filtered = [];

        foreach ($items as $row) {
            $desc = (string) ($row['description'] ?? '');
            if (DebrisRemovalExpenseService::isLineDescription($desc)) {
                $debrisGross += (float) ($row['amount'] ?? 0);

                continue;
            }
            if ($this->source_type === self::SOURCE_TILE && $desc === 'Client-selected tile & materials') {
                $row['description'] = 'Materials';
            }
            $filtered[] = $row;
        }

        if ($debrisGross > 0 && $this->commuteLabourLineIndexes($filtered) !== []) {
            $filtered = $this->mergeCommuteIntoLabourLines($filtered, $debrisGross);
        } elseif ($debrisGross > 0) {
            $filtered[] = [
                'description' => DebrisRemovalExpenseService::LINE_DESCRIPTION,
                'amount' => round($debrisGross, 2),
                'apply_hst' => false,
                'hst_included' => true,
                'is_standalone' => true,
            ];
        }

        return array_values($filtered);
    }

    /**
     * Client PDFs: merge a hidden HST-inclusive expense into one labour or
     * materials line as pre-tax, so HST (13%) includes that tax.
     *
     * @param  list<array<string, mixed>>  $items
     * @return list<array<string, mixed>>
     */
    protected function normalizeInternalExpensesForClientFacing(array $items): array
    {
        $labourHiddenGross = 0.0;
        $materialsHiddenGross = 0.0;
        $filtered = [];

        foreach ($items as $row) {
            $desc = (string) ($row['description'] ?? '');
            if (self::isMaterialsHiddenInternalExpenseLineDescription($desc)) {
                $materialsHiddenGross += (float) ($row['amount'] ?? 0);

                continue;
            }
            if (self::isLabourHiddenInternalExpenseLineDescription($desc)) {
                $labourHiddenGross += (float) ($row['amount'] ?? 0);

                continue;
            }
            $filtered[] = $row;
        }

        if ($filtered === []) {
            return $items;
        }

        if ($materialsHiddenGross > 0) {
            $filtered = $this->mergeHiddenTaxInExpenseIntoTarget($filtered, $materialsHiddenGross, 'materials');
        }
        if ($labourHiddenGross > 0) {
            $filtered = $this->mergeCommuteIntoLabourLines($filtered, $labourHiddenGross);
        }

        return array_values($filtered);
    }

    /**
     * Split an HST-inclusive commute amount evenly across job labour lines.
     *
     * @param  list<array<string, mixed>>  $items
     * @return list<array<string, mixed>>
     */
    protected function mergeCommuteIntoLabourLines(array $items, float $hiddenGross): array
    {
        $indexes = $this->commuteLabourLineIndexes($items);
        if ($indexes === []) {
            return $items;
        }

        $allTaxIn = true;
        foreach ($indexes as $idx) {
            $row = $items[$idx];
            if (empty($row['hst_included']) || ! empty($row['apply_hst'])) {
                $allTaxIn = false;
                break;
            }
        }

        $total = $allTaxIn ? round($hiddenGross, 2) : self::preTaxFromHstIncluded($hiddenGross);
        if ($total <= 0) {
            return $items;
        }

        $shares = self::splitAmountEvenly($total, count($indexes));
        foreach ($indexes as $i => $idx) {
            $row = &$items[$idx];
            $this->addAmountToPdfLine($row, $shares[$i]);
            unset($row);
        }

        return $items;
    }

    /**
     * @return list<int>
     */
    protected function commuteLabourLineIndexes(array $items): array
    {
        $indexes = [];
        foreach ($items as $i => $row) {
            if (self::isPdfLabourLine($row)) {
                $indexes[] = $i;
            }
        }

        if ($indexes !== []) {
            return $indexes;
        }

        $one = $this->commuteMergeTargetIndex($items);

        return $one !== null ? [$one] : [];
    }

    /**
     * @param  array<string, mixed>  $row
     */
    protected static function isPdfLabourLine(array $row): bool
    {
        if (! empty($row['is_heading'])) {
            return false;
        }

        $desc = (string) ($row['description'] ?? '');
        if (stripos($desc, 'HST exempt') !== false) {
            return false;
        }
        if (DebrisRemovalExpenseService::isLineDescription($desc)) {
            return false;
        }

        return $desc === 'Labour'
            || $desc === 'Labor'
            || str_ends_with($desc, ' — Labour')
            || str_ends_with($desc, ' — Labor')
            || (bool) preg_match('/\blabou?r\b/i', $desc);
    }

    /**
     * @return list<float>
     */
    public static function splitAmountEvenly(float $total, int $parts): array
    {
        if ($parts <= 0) {
            return [];
        }

        $cents = (int) round($total * 100);
        $base = intdiv($cents, $parts);
        $remainder = $cents % $parts;
        $shares = [];
        for ($i = 0; $i < $parts; $i++) {
            $extra = ($i === $parts - 1) ? $remainder : 0;
            $shares[] = round(($base + $extra) / 100, 2);
        }

        return $shares;
    }

    /**
     * @param  array<string, mixed>  $row
     */
    protected function addAmountToPdfLine(array &$row, float $add): void
    {
        if ($add == 0.0) {
            return;
        }

        $row['amount'] = round((float) ($row['amount'] ?? 0) + $add, 2);

        if (! empty($row['apply_hst'])) {
            $taxableBase = array_key_exists('hst_taxable_amount', $row)
                ? (float) $row['hst_taxable_amount']
                : (float) $row['amount'] - $add;
            $row['hst_taxable_amount'] = round($taxableBase + $add, 2);
        }
    }

    /**
     * @param  list<array<string, mixed>>  $items
     * @return list<array<string, mixed>>
     */
    protected function mergeHiddenTaxInExpenseIntoTarget(array $items, float $hiddenGross, string $bucket): array
    {
        if ($hiddenGross <= 0) {
            return $items;
        }

        $targetIdx = $bucket === 'materials'
            ? $this->materialsMergeTargetIndex($items)
            : $this->commuteMergeTargetIndex($items);

        if ($targetIdx === null) {
            return $items;
        }

        $row = &$items[$targetIdx];

        // Tax-included material buckets (e.g. paving) absorb the gross amount.
        if (! empty($row['hst_included']) && empty($row['apply_hst'])) {
            $row['amount'] = round((float) ($row['amount'] ?? 0) + $hiddenGross, 2);
            unset($row);

            return $items;
        }

        $hiddenPreTax = self::preTaxFromHstIncluded($hiddenGross);
        if ($hiddenPreTax <= 0) {
            unset($row);

            return $items;
        }

        $row['amount'] = round((float) ($row['amount'] ?? 0) + $hiddenPreTax, 2);

        if (! empty($row['apply_hst'])) {
            $taxableBase = array_key_exists('hst_taxable_amount', $row)
                ? (float) $row['hst_taxable_amount']
                : (float) ($row['amount'] ?? 0) - $hiddenPreTax;
            $row['hst_taxable_amount'] = round($taxableBase + $hiddenPreTax, 2);
        }

        unset($row);

        return $items;
    }

    /** @deprecated Use normalizeInternalExpensesForClientFacing */
    protected function normalizeCommuteForClientFacing(array $items): array
    {
        return $this->normalizeInternalExpensesForClientFacing($items);
    }

    protected static function isHiddenInternalExpenseLineDescription(string $description): bool
    {
        return self::isLabourHiddenInternalExpenseLineDescription($description)
            || self::isMaterialsHiddenInternalExpenseLineDescription($description);
    }

    protected static function isLabourHiddenInternalExpenseLineDescription(string $description): bool
    {
        return str_starts_with($description, 'Commute expense')
            || str_starts_with($description, \App\Services\AdditionalCostsService::LINE_PREFIX_LABOUR);
    }

    protected static function isMaterialsHiddenInternalExpenseLineDescription(string $description): bool
    {
        return str_starts_with($description, \App\Services\AdditionalCostsService::LINE_PREFIX);
    }

    protected static function isCommuteLineDescription(string $description): bool
    {
        return self::isHiddenInternalExpenseLineDescription($description);
    }

    /**
     * @param  list<array<string, mixed>>  $items
     */
    protected function materialsMergeTargetIndex(array $items): ?int
    {
        $candidates = [];

        foreach ($items as $i => $row) {
            if (! empty($row['is_heading'])) {
                continue;
            }
            $desc = (string) ($row['description'] ?? '');
            if (stripos($desc, 'HST exempt') !== false) {
                continue;
            }
            if (DebrisRemovalExpenseService::isLineDescription($desc)) {
                continue;
            }

            $score = 0;
            if (str_ends_with($desc, ' — Material') || str_ends_with($desc, ' — materials')) {
                $score = 100;
            } elseif (preg_match('/\bMaterials?\b/i', $desc) && stripos($desc, 'labour') === false) {
                $score = 90;
            } elseif (stripos($desc, 'Labour and material') !== false || stripos($desc, 'Material and labour') !== false) {
                $score = 70;
            } elseif (! empty($row['apply_hst']) || ! empty($row['hst_included'])) {
                $score = 10;
            } else {
                continue;
            }

            $candidates[] = ['index' => $i, 'score' => $score];
        }

        if ($candidates === []) {
            return null;
        }

        usort($candidates, fn ($a, $b) => $b['score'] <=> $a['score']);

        return $candidates[0]['index'];
    }

    /**
     * @param  list<array<string, mixed>>  $items
     */
    protected function commuteMergeTargetIndex(array $items): ?int
    {
        $candidates = [];

        foreach ($items as $i => $row) {
            if (! empty($row['is_heading'])) {
                continue;
            }
            if (empty($row['apply_hst'])) {
                continue;
            }

            $desc = (string) ($row['description'] ?? '');
            if (stripos($desc, 'HST exempt') !== false) {
                continue;
            }
            if (DebrisRemovalExpenseService::isLineDescription($desc)) {
                continue;
            }

            $score = 0;
            if (str_ends_with($desc, ' — Labour') || str_ends_with($desc, ' — Labor')) {
                $score = 100;
            } elseif (stripos($desc, 'HST applicable') !== false) {
                $score = 90;
            } elseif (preg_match('/\blabou?r\b/i', $desc) && stripos($desc, 'material') === false) {
                $score = 80;
            } elseif (stripos($desc, 'Labour and material') !== false || stripos($desc, 'Material and labour') !== false) {
                $score = 70;
            } else {
                $score = 10;
            }

            $candidates[] = ['index' => $i, 'score' => $score];
        }

        if ($candidates === []) {
            return null;
        }

        usort($candidates, fn ($a, $b) => $b['score'] <=> $a['score']);

        return $candidates[0]['index'];
    }

    /**
     * Paving quotes: show pre-tax line amounts and roll embedded HST into the footer.
     * so clients see one consistent 13% tax line (materials were stored tax-in).
     *
     * @param  list<array<string, mixed>>  $items
     * @return list<array{description: string, amount: float, apply_hst: bool}>
     */
    protected function normalizePavingPdfPreTaxLines(array $items): array
    {
        $normalized = [];

        foreach ($items as $row) {
            $description = (string) ($row['description'] ?? '');
            $amount = (float) ($row['amount'] ?? 0);

            if (DebrisRemovalExpenseService::isLineDescription($description)) {
                $normalized[] = $row;

                continue;
            }

            if (! empty($row['hst_included'])) {
                $normalized[] = [
                    'description' => $description,
                    'amount' => self::preTaxFromHstIncluded($amount),
                    'apply_hst' => true,
                ];

                continue;
            }

            if (! empty($row['apply_hst'])) {
                $taxableBase = array_key_exists('hst_taxable_amount', $row)
                    ? (float) $row['hst_taxable_amount']
                    : $amount;
                $includedPortion = round($amount - $taxableBase, 2);
                $preTaxAmount = $taxableBase;

                if ($includedPortion > 0) {
                    $preTaxAmount = round($taxableBase + self::preTaxFromHstIncluded($includedPortion), 2);
                }

                $normalized[] = [
                    'description' => $description,
                    'amount' => round($preTaxAmount, 2),
                    'apply_hst' => true,
                ];

                continue;
            }

            $normalized[] = $row;
        }

        return $normalized;
    }

    public static function preTaxFromHstIncluded(float $gross): float
    {
        if ($gross <= 0) {
            return 0.0;
        }

        return round($gross / (1 + self::HST_RATE), 2);
    }

    /**
     * @param  list<array{description: string, amount: float, apply_hst: bool}>  $items
     * @return list<array{description: string, amount: float, apply_hst: bool}>
     */
    protected function normalizeStairsLinkedLineItems(array $items): array
    {
        if ($this->source_type !== self::SOURCE_STAIRS || count($items) !== 1) {
            return $items;
        }

        $row = $items[0];
        if (! empty($row['apply_hst'])) {
            return $items;
        }

        $snap = is_array($this->source_snapshot) ? $this->source_snapshot : [];
        $calc = is_array($snap['calculation'] ?? null) ? $snap['calculation'] : [];
        $summary = is_array($calc['summary'] ?? null) ? $calc['summary'] : [];
        $preTax = round((float) ($summary['subtotal_pre_hst'] ?? 0), 2);
        $grand = round((float) ($summary['grand_total'] ?? $summary['final_quote_total'] ?? 0), 2);
        if ($preTax <= 0 || $grand <= 0) {
            return $items;
        }

        $lineAmount = (float) $row['amount'];
        if (abs($lineAmount - $grand) > 0.02 && abs($lineAmount - $preTax) > 0.02) {
            return $items;
        }

        return [[
            'description' => $row['description'],
            'amount' => $preTax,
            'apply_hst' => true,
        ]];
    }

    /** True iff the quotation has at least one usable line item. */
    public function hasContent(): bool
    {
        return count($this->cleanItems()) > 0;
    }

    /**
     * Project quotation row synced from a specialized calculator (standard, fence, …).
     */
    public static function findForSource(string $sourceType, int $sourceId): ?self
    {
        if ($sourceId < 1 || $sourceType === '') {
            return null;
        }

        return static::query()
            ->where('source_type', $sourceType)
            ->where('source_id', $sourceId)
            ->first();
    }

    /**
     * Sum of line-item pre-tax amounts (ignores {@see $subtotal_override}).
     */
    public function lineItemsSubtotal(): float
    {
        return $this->pdfSubtotal();
    }

    /**
     * When non-null and not 0.00, {@see subtotal()} and {@see hst()} use this
     * instead of the raw line sum (HST is scaled to preserve taxable vs exempt mix).
     */
    public function hasSubtotalOverrideActive(): bool
    {
        if ($this->subtotal_override === null) {
            return false;
        }

        return round((float) $this->subtotal_override, 2) != 0.0;
    }

    /** Effective pre-tax subtotal (override or sum of lines). */
    public function subtotal(): float
    {
        if ($this->hasSubtotalOverrideActive()) {
            return round((float) $this->subtotal_override, 2);
        }

        return $this->lineItemsSubtotal();
    }

    /**
     * Labour pre-tax amount used to set aside HST on the profit summary.
     *
     * Materials HST is already paid at the store (ITC); commute/debris are
     * not labour. When a calculator split is unavailable, the whole subtotal
     * is treated as labour so we do not drop the set-aside.
     */
    public function labourPreTaxForHstSetAside(): float
    {
        if ($this->labour === false) {
            return 0.0;
        }

        $source = $this->calculatorModelForLabourHstSetAside();
        if ($source !== null && method_exists($source, 'labourPreTaxAmountForProjectQuotation')) {
            $labour = $source->labourPreTaxAmountForProjectQuotation();
            if ($this->hasSubtotalOverrideActive()) {
                $lineSub = $this->lineItemsSubtotal();
                if ($lineSub > 0.00001) {
                    $labour *= ((float) $this->subtotal_override / $lineSub);
                }
            }

            return max(0, round(min($labour, $this->subtotal()), 2));
        }

        return $this->subtotal();
    }

    /**
     * Calculator row used to read labour pre-tax. Prefers the stored snapshot
     * (the accepted quote) so Financials does not hit extra queries.
     */
    protected function calculatorModelForLabourHstSetAside(): ?object
    {
        $class = match ($this->source_type) {
            self::SOURCE_FENCE => FenceQuotation::class,
            self::SOURCE_LOW_DECK => LowDeckQuotation::class,
            self::SOURCE_PAVING => PavingQuotation::class,
            self::SOURCE_STANDARD => StandardQuotation::class,
            self::SOURCE_STAIRS => StairsQuotation::class,
            self::SOURCE_PAINTING => PaintingQuotation::class,
            self::SOURCE_FLOORING => FlooringQuotation::class,
            self::SOURCE_TILE => TileQuotation::class,
            self::SOURCE_BASEMENT => BasementQuotation::class,
            self::SOURCE_SHED => ShedQuotation::class,
            self::SOURCE_TRIMMING => TrimmingQuotation::class,
            self::SOURCE_MILLWORK => MillworkQuotation::class,
            default => null,
        };
        if ($class === null) {
            return null;
        }

        $snap = is_array($this->source_snapshot) ? $this->source_snapshot : [];
        if ($snap !== []) {
            return (new $class)->forceFill($snap);
        }

        $id = (int) ($this->source_id ?? 0);
        if ($id <= 0) {
            return null;
        }

        return $class::query()->find($id);
    }

    /** 13% HST only on rows where `apply_hst` is true (default). */
    public function hst(): float
    {
        if (! $this->hasSubtotalOverrideActive()) {
            return $this->pdfHst();
        }

        $items = $this->pdfLineItems();
        $lineSub = $this->lineItemsSubtotal();
        $override = round((float) $this->subtotal_override, 2);

        if ($lineSub > 0.00001) {
            $ratio = $override / $lineSub;
            $sum = 0.0;
            foreach ($items as $row) {
                if (empty($row['apply_hst'])) {
                    continue;
                }
                $sum += (float) ($row['amount'] ?? 0) * $ratio * self::HST_RATE;
            }

            return round($sum, 2);
        }

        $anyTaxable = false;
        foreach ($items as $row) {
            if (! empty($row['apply_hst'])) {
                $anyTaxable = true;
                break;
            }
        }

        if ($anyTaxable) {
            return round($override * self::HST_RATE, 2);
        }

        return 0.0;
    }

    public function grandTotal(): float
    {
        return round($this->subtotal() + $this->hst(), 2);
    }

    public function statusLabel(): string
    {
        return match ($this->status) {
            self::STATUS_SENT => 'Sent',
            self::STATUS_ACCEPTED => 'Accepted',
            self::STATUS_REJECTED => 'Rejected',
            default => 'Draft',
        };
    }

    public function isAccepted(): bool
    {
        return $this->status === self::STATUS_ACCEPTED;
    }

    /**
     * Installment count and % split for Financials, from this row or its
     * calculator snapshot when the columns were not copied yet.
     *
     * @return array{installments: int, percentages: list<float>}|null
     */
    public function resolvedPaymentSchedule(): ?array
    {
        $n = (int) ($this->payment_installments ?? 0);
        $percentages = is_array($this->payment_percentages) ? $this->payment_percentages : null;

        if (! in_array($n, QuotationPaymentSchedule::ALLOWED, true)) {
            $snap = is_array($this->source_snapshot) ? $this->source_snapshot : [];
            $n = (int) ($snap['payment_installments'] ?? 0);
            if (isset($snap['payment_percentages']) && is_array($snap['payment_percentages'])) {
                $percentages = $snap['payment_percentages'];
            }
        }

        if (! in_array($n, QuotationPaymentSchedule::ALLOWED, true)) {
            return null;
        }

        return [
            'installments' => $n,
            'percentages' => QuotationPaymentSchedule::percentages($n, $percentages),
        ];
    }

    /**
     * Delete this row and the linked Fence / Standard / Paving / Stairs calculator
     * record. Does nothing for legacy manual documents — callers must guard.
     */
    public static function deleteWithLinkedCalculatorSource(self $quotation): void
    {
        DB::transaction(function () use ($quotation) {
            $projectId = (int) $quotation->client_project_id;
            $sourceType = $quotation->source_type;
            $sourceId = (int) ($quotation->source_id ?? 0);

            $quotation->delete();

            if ($sourceId > 0) {
                match ($sourceType) {
                    self::SOURCE_FENCE => FenceQuotation::query()->whereKey($sourceId)->delete(),
                    self::SOURCE_STANDARD => StandardQuotation::query()->whereKey($sourceId)->delete(),
                    self::SOURCE_PAVING => PavingQuotation::query()->whereKey($sourceId)->delete(),
                    self::SOURCE_STAIRS => StairsQuotation::query()->whereKey($sourceId)->delete(),
                    self::SOURCE_PAINTING => PaintingQuotation::query()->whereKey($sourceId)->delete(),
                    self::SOURCE_FLOORING => FlooringQuotation::query()->whereKey($sourceId)->delete(),
                    self::SOURCE_TILE => TileQuotation::query()->whereKey($sourceId)->delete(),
                    self::SOURCE_LOW_DECK => LowDeckQuotation::query()->whereKey($sourceId)->delete(),
                    self::SOURCE_BASEMENT => BasementQuotation::query()->whereKey($sourceId)->delete(),
                    self::SOURCE_SHED => ShedQuotation::query()->whereKey($sourceId)->delete(),
                    self::SOURCE_TRIMMING => TrimmingQuotation::query()->whereKey($sourceId)->delete(),
                    self::SOURCE_MILLWORK => MillworkQuotation::query()->whereKey($sourceId)->delete(),
                    default => null,
                };
            }

            $project = ClientProject::query()->find($projectId);
            if ($project && ! $project->isHourly()) {
                $project->syncFixedContractTotalsFromQuotation();
            }
        });
    }

    /** Sum of accepted quotations' stored total_amount (grand total, incl. line HST). */
    public static function acceptedGrandTotalForProject(ClientProject $project): float
    {
        return round(
            (float) static::query()
                ->where('client_project_id', $project->id)
                ->where('status', self::STATUS_ACCEPTED)
                ->sum('total_amount'),
            2
        );
    }

    /** HST amount attributed to a single cleaned row (for PDFs / previews). */
    public static function lineHstAmount(array $row): float
    {
        if (empty($row['apply_hst'])) {
            return 0.0;
        }

        $base = array_key_exists('hst_taxable_amount', $row)
            ? (float) $row['hst_taxable_amount']
            : (float) ($row['amount'] ?? 0);

        return round($base * self::HST_RATE, 2);
    }

    /** HST column label for a quotation line (admin preview / exports). */
    public static function lineHstColumnLabel(array $row): string
    {
        if (! empty($row['apply_hst'])) {
            return '13%';
        }
        if (! empty($row['hst_included'])) {
            return '13% incl.';
        }

        return '—';
    }

    /**
     * Whether this quotation price includes labour (installation / workmanship).
     */
    public function includesLabourInSupplyScope(): bool
    {
        if ($this->source_type === self::SOURCE_STAIRS && $this->source_id) {
            return true;
        }

        if ($this->source_type === self::SOURCE_PAINTING && $this->source_id) {
            return true;
        }

        if ($this->source_type === self::SOURCE_FLOORING && $this->source_id) {
            return true;
        }

        if ($this->source_type === self::SOURCE_TILE && $this->source_id) {
            return true;
        }

        if ($this->source_type === self::SOURCE_BASEMENT && $this->source_id) {
            return true;
        }

        if ($this->source_type === self::SOURCE_SHED && $this->source_id) {
            return true;
        }

        if ($this->source_type === self::SOURCE_TRIMMING && $this->source_id) {
            return true;
        }

        if ($this->source_type === self::SOURCE_MILLWORK && $this->source_id) {
            return true;
        }

        return (bool) $this->labour;
    }

    /**
     * Whether this quotation price includes materials supplied by Zanini.
     */
    public function includesMaterialInSupplyScope(): bool
    {
        if ($this->source_type === self::SOURCE_STAIRS && $this->source_id) {
            $stairs = StairsQuotation::query()->find($this->source_id);

            return $stairs?->includesMaterialCharge() ?? false;
        }

        if ($this->source_type === self::SOURCE_PAINTING && $this->source_id) {
            $painting = PaintingQuotation::query()->find($this->source_id);

            return $painting?->includesMaterialCharge() ?? false;
        }

        if ($this->source_type === self::SOURCE_FLOORING && $this->source_id) {
            $flooring = FlooringQuotation::query()->find($this->source_id);

            return $flooring?->includesMaterialCharge() ?? false;
        }

        if ($this->source_type === self::SOURCE_TILE && $this->source_id) {
            $tile = TileQuotation::query()->find($this->source_id);

            return $tile?->includesMaterialCharge() ?? false;
        }

        if ($this->source_type === self::SOURCE_BASEMENT && $this->source_id) {
            $basement = BasementQuotation::query()->find($this->source_id);

            return $basement?->includesMaterialCharge() ?? false;
        }

        if ($this->source_type === self::SOURCE_SHED && $this->source_id) {
            $shed = ShedQuotation::query()->find($this->source_id);

            return $shed?->includesMaterialCharge() ?? false;
        }

        if ($this->source_type === self::SOURCE_TRIMMING && $this->source_id) {
            $trimming = TrimmingQuotation::query()->find($this->source_id);

            return $trimming?->includesMaterialCharge() ?? false;
        }

        if ($this->source_type === self::SOURCE_MILLWORK && $this->source_id) {
            $millwork = MillworkQuotation::query()->find($this->source_id);

            return $millwork?->includesMaterialCharge() ?? false;
        }

        return (bool) $this->material;
    }

    /**
     * Client-facing supply scope line for PDF and admin preview.
     *
     * @return array{show: bool, headline_html: string}
     */
    public function supplyScopePdfMeta(): array
    {
        $labour = $this->includesLabourInSupplyScope();
        $material = $this->includesMaterialInSupplyScope();

        if ($labour && $material) {
            return [
                'show' => true,
                'headline_html' => 'This quotation includes <strong>MATERIALS</strong> and <strong>LABOUR</strong> for the following.',
            ];
        }

        if ($labour) {
            return [
                'show' => true,
                'headline_html' => 'This quotation includes <strong>LABOUR</strong> for the following.',
            ];
        }

        if ($material) {
            return [
                'show' => true,
                'headline_html' => 'This quotation includes <strong>MATERIALS</strong> for the following.',
            ];
        }

        return [
            'show' => false,
            'headline_html' => '',
        ];
    }

    /**
     * Intro phrase printed before the items table, derived from the
     * labour/material checkboxes:
     *   - both   → "To supply material and labour of the following:"
     *   - labour → "For the labour of the following:"
     *   - mat.   → "To supply material of the following:"
     *   - none   → fallback (UI also warns when neither is checked)
     */
    public function introPhrase(): string
    {
        if ($this->includesLabourInSupplyScope() && $this->includesMaterialInSupplyScope()) {
            return 'To supply material and labour of the following:';
        }
        if ($this->includesLabourInSupplyScope()) {
            return 'For the labour of the following:';
        }
        if ($this->includesMaterialInSupplyScope()) {
            return 'To supply material of the following:';
        }

        return 'For the following scope of work:';
    }

    /**
     * Client material selection block for flooring PDFs.
     *
     * @return array{
     *   name: string,
     *   color: string,
     *   url: string,
     *   cost_per_sqft: float,
     *   waste_percent: float,
     *   delivery_fee: float,
     *   total_pre_tax: float,
     *   square_footage: float
     * }|null
     */
    public function flooringMaterialSelectionForPdf(): ?array
    {
        if ($this->source_type !== self::SOURCE_FLOORING || ! $this->source_id) {
            return null;
        }

        $flooring = FlooringQuotation::query()->find($this->source_id);

        return $flooring?->materialSelectionForPdf();
    }

    /**
     * Generate a unique number like "QUO-2026-0327" or "EST-2026-0327".
     *
     * Numeric suffix is shared across both prefixes per year, so changing
     * the type later only swaps the prefix without colliding.
     *
     * The very first number issued in a given year starts at a random offset
     * (150–500) so a brand-new client doesn't see "0001" and assume we're
     * just getting started.
     */
    public static function generateNumber(string $type): string
    {
        $prefix = $type === self::TYPE_ESTIMATE ? 'EST' : 'QUO';
        $year = (int) date('Y');

        // Highest numeric suffix this year (shared across QUO and EST prefixes).
        // String sort on quotation_number is wrong here — e.g. QUO-2026-0485
        // sorts above EST-2026-0486 and would re-issue an existing number.
        $maxSuffix = (int) self::query()
            ->where(function ($q) use ($year) {
                $q->where('quotation_number', 'like', "QUO-{$year}-%")
                    ->orWhere('quotation_number', 'like', "EST-{$year}-%");
            })
            ->selectRaw('MAX(CAST(SUBSTRING_INDEX(quotation_number, "-", -1) AS UNSIGNED)) as max_suffix')
            ->value('max_suffix');

        $next = $maxSuffix > 0 ? $maxSuffix + 1 : random_int(150, 500);

        do {
            $candidate = sprintf('%s-%d-%04d', $prefix, $year, $next);
            if (! self::query()->where('quotation_number', $candidate)->exists()) {
                return $candidate;
            }
            $next++;
        } while (true);
    }
}

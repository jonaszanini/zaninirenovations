<?php

namespace App\Services;

use App\Models\ClientProject;
use App\Models\ClientProjectExpense;
use App\Models\ClientProjectInvoice;
use App\Models\ClientProjectReturn;
use App\Models\ClientProjectStatusHistoryEntry;
use App\Models\FenceMaterial;
use App\Models\FenceQuotation;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

/**
 * Aggregates admin dashboard metrics (financials, calendar snapshot, activity).
 * Heavy lifting stays server-side; the Blade view renders JSON for Chart.js.
 */
class AdminDashboardService
{
    private const CACHE_TTL_SECONDS = 120;

    private const CACHE_KEY = 'admin_dashboard_payload_v1';

    public function build(bool $refresh = false): array
    {
        if ($refresh) {
            Cache::forget(self::CACHE_KEY);
        }

        return Cache::remember(self::CACHE_KEY, self::CACHE_TTL_SECONDS, function () {
            return $this->computePayload();
        });
    }

    private function computePayload(): array
    {
        $now = Carbon::now(config('app.timezone'));
        $year = (int) $now->year;

        $projectionMonths = collect(range(0, 5))->map(function (int $i) use ($now) {
            return $now->copy()->startOfMonth()->addMonths($i);
        });
        $rangeStart = $projectionMonths->first()->copy()->startOfDay();
        $rangeEnd = $projectionMonths->last()->copy()->endOfMonth()->endOfDay();

        $projects = ClientProject::query()
            ->with([
                'installments' => fn ($q) => $q->orderBy('sort_order'),
                'invoices',
                'quotations',
            ])
            ->where('status', '!=', ClientProject::STATUS_REJECTED)
            ->get();

        $projection = $this->buildSixMonthProjection($projects, $projectionMonths, $rangeStart, $rangeEnd);
        $ytd = $this->buildYtdFinancials($now, $year);
        $cards = $this->buildSummaryCards($projects, $now, $year);
        $upcoming = $this->buildUpcomingSnapshot($projects, $now);
        $activity = $this->buildRecentActivity();
        $statusOverview = $this->buildStatusOverview($projects);
        $pipeline = $this->buildPipelineSummary($projects);
        $attention = $this->buildAttentionProjects($projects, $now);
        $overdueRows = $this->buildOverdueInvoiceRows();
        $materialAlert = $this->buildMaterialPriceAlert();
        $quickActions = $this->quickActionsMeta();

        $ytdPlusForward = round(
            (float) ($ytd['collected_invoice_basis'] ?? 0) + (float) ($projection['forward_total_revenue'] ?? 0),
            2
        );

        return [
            'generated_at' => $now->toIso8601String(),
            'cache_ttl_seconds' => self::CACHE_TTL_SECONDS,
            'projection' => $projection,
            'ytd' => $ytd,
            'cards' => $cards,
            'upcoming' => $upcoming,
            'activity' => $activity,
            'status_overview' => $statusOverview,
            'pipeline' => $pipeline,
            'attention' => $attention,
            'overdue_invoices' => $overdueRows,
            'material_alert' => $materialAlert,
            'quick_actions' => $quickActions,
            'ytd_plus_forward_revenue' => $ytdPlusForward,
        ];
    }

    private function quickActionsMeta(): array
    {
        return [
            ['label' => 'Create new project', 'route' => 'admin.client-projects.create', 'icon' => 'fa-plus-circle', 'variant' => 'primary'],
            ['label' => 'New fence quotation', 'route' => 'admin.quotations.fence.create', 'icon' => 'fa-calculator', 'variant' => 'primary'],
            ['label' => 'New paving quotation', 'route' => 'admin.quotations.paving.create', 'icon' => 'fa-th', 'variant' => 'primary'],
            ['label' => 'All active projects', 'route' => 'admin.client-projects.index', 'icon' => 'fa-briefcase', 'variant' => 'secondary', 'query' => []],
            ['label' => 'Project calendar', 'route' => 'admin.client-projects.calendar', 'icon' => 'fa-calendar', 'variant' => 'secondary'],
        ];
    }

    private function buildMaterialPriceAlert(): ?array
    {
        try {
            $latest = FenceMaterial::query()->max('updated_at');
            if (!$latest) {
                return null;
            }
            $t = Carbon::parse($latest, config('app.timezone'));
            if ($t->greaterThanOrEqualTo(now()->subDays(7))) {
                return [
                    'message' => 'Fence material catalogue prices were updated in the last 7 days. Review the Fence config if quotes need refreshing.',
                    'route' => 'admin.quotations.fence.config',
                    'updated_at' => $t->toIso8601String(),
                ];
            }
        } catch (\Throwable) {
            return null;
        }

        return null;
    }

    private function buildSixMonthProjection(
        Collection $projects,
        Collection $projectionMonths,
        Carbon $rangeStart,
        Carbon $rangeEnd
    ): array {
        $buckets = [];
        foreach ($projectionMonths as $m) {
            $key = $m->format('Y-m');
            $buckets[$key] = [
                'key' => $key,
                'label' => $m->format('M Y'),
                'revenue' => 0.0,
                'profit' => 0.0,
            ];
        }

        $ytdMargin = $this->estimateForwardMarginFromYtd();

        foreach ($projects as $project) {
            if ($project->isHourly()) {
                foreach ($project->invoices as $invoice) {
                    if (!$invoice->isSent() || $invoice->isPaid()) {
                        continue;
                    }
                    $amt = (float) $invoice->total;
                    if ($amt <= 0) {
                        continue;
                    }
                    $d = $this->expectedCollectionDateForOpenInvoice($invoice);
                    $this->addToProjectionBuckets($buckets, $d, $rangeStart, $rangeEnd, $amt, $ytdMargin);
                }

                continue;
            }

            if (!$project->hasSchedule()) {
                continue;
            }

            $instRows = $project->installments;
            if ($instRows->isEmpty()) {
                continue;
            }

            $byInst = $project->invoices->whereNotNull('installment_number')->keyBy('installment_number');

            foreach ($instRows as $inst) {
                if ($inst->is_paid) {
                    continue;
                }
                // Use the installment's proportional HST share rather than
                // a flat 13% — see ClientProjectInstallment::effectiveHst()
                // for why HST-exempt quotation lines must not be re-taxed.
                $gross = $inst->effectiveTotal();
                $d = $this->projectedInstallmentDate($project, $inst, $instRows->count(), $byInst);
                $this->addToProjectionBuckets($buckets, $d, $rangeStart, $rangeEnd, $gross, $ytdMargin);
            }
        }

        $labels = [];
        $revenueSeries = [];
        $profitSeries = [];
        $cumRev = 0.0;
        $cumProfit = 0.0;
        $cumulativeRevenue = [];
        $cumulativeProfit = [];
        foreach ($buckets as $row) {
            $labels[] = $row['label'];
            $revenueSeries[] = round($row['revenue'], 2);
            $profitSeries[] = round($row['profit'], 2);
            $cumRev += $row['revenue'];
            $cumProfit += $row['profit'];
            $cumulativeRevenue[] = round($cumRev, 2);
            $cumulativeProfit[] = round($cumProfit, 2);
        }

        $forwardTotal = round(array_sum($revenueSeries), 2);

        return [
            'labels' => $labels,
            'month_keys' => array_keys($buckets),
            'revenue' => $revenueSeries,
            'profit' => $profitSeries,
            'cumulative_revenue' => $cumulativeRevenue,
            'cumulative_profit' => $cumulativeProfit,
            'forward_total_revenue' => $forwardTotal,
            'forward_total_profit' => round(array_sum($profitSeries), 2),
            'disclaimer' => 'Based on scheduled payment plans and invoice due dates — actuals may vary.',
        ];
    }

    /**
     * Uses YTD net / revenue ratio to scale projected revenue into rough profit.
     * Returns null ratio → profit series stays zero.
     */
    private function estimateForwardMarginFromYtd(): ?float
    {
        $yearStart = now()->copy()->startOfYear();
        $now = now();

        $rev = (float) ClientProjectInvoice::query()
            ->where('status', ClientProjectInvoice::STATUS_PAID)
            ->whereBetween('paid_at', [$yearStart, $now])
            ->sum('paid_amount');

        if ($rev <= 0) {
            return null;
        }

        $costs = (float) ClientProjectExpense::query()
            ->whereBetween('occurred_on', [$yearStart->toDateString(), $now->toDateString()])
            ->sum('amount');
        $returns = (float) ClientProjectReturn::query()
            ->whereBetween('occurred_on', [$yearStart->toDateString(), $now->toDateString()])
            ->sum('amount');
        $net = $rev - max(0.0, $costs - $returns);

        return $net / $rev;
    }

    private function addToProjectionBuckets(
        array &$buckets,
        ?Carbon $date,
        Carbon $rangeStart,
        Carbon $rangeEnd,
        float $amount,
        ?float $marginRatio
    ): void {
        if ($date === null || $amount <= 0) {
            return;
        }

        if ($date->lt($rangeStart)) {
            $date = $rangeStart->copy();
        }
        if ($date->gt($rangeEnd)) {
            return;
        }

        $key = $date->copy()->startOfMonth()->format('Y-m');
        if (!isset($buckets[$key])) {
            return;
        }

        $buckets[$key]['revenue'] += $amount;
        if ($marginRatio !== null) {
            $buckets[$key]['profit'] += $amount * $marginRatio;
        }
    }

    private function expectedCollectionDateForOpenInvoice(ClientProjectInvoice $invoice): ?Carbon
    {
        if ($invoice->due_on) {
            return Carbon::parse($invoice->due_on)->startOfDay();
        }
        if ($invoice->sent_at) {
            return Carbon::parse($invoice->sent_at)->addDays(ClientProjectInvoice::DEFAULT_DUE_DAYS)->startOfDay();
        }

        return null;
    }

    /**
     * @param  Collection<int, ClientProjectInvoice>  $invoicesByInstallment  keyed by installment_number
     */
    private function projectedInstallmentDate(
        ClientProject $project,
        $inst,
        int $installmentCount,
        Collection $invoicesByInstallment
    ): ?Carbon {
        $num = (int) $inst->sort_order + 1;
        $inv = $invoicesByInstallment->get($num);
        if ($inv && $inv->isSent() && !$inv->isPaid()) {
            return $this->expectedCollectionDateForOpenInvoice($inv);
        }

        return $this->interpolatedScheduleDate($project, (int) $inst->sort_order, $installmentCount);
    }

    private function interpolatedScheduleDate(ClientProject $project, int $sortOrder, int $count): ?Carbon
    {
        if (!$project->hasSchedule()) {
            return null;
        }
        $start = Carbon::parse($project->start_date)->startOfDay();
        $end = Carbon::parse($project->end_date)->startOfDay();
        if ($count <= 1) {
            return $start->copy();
        }
        $days = max(0, $start->diffInDays($end));
        $frac = $sortOrder / ($count - 1);

        return $start->copy()->addDays((int) round($days * $frac));
    }

    private function buildYtdFinancials(Carbon $now, int $year): array
    {
        $yearStart = Carbon::create($year, 1, 1, 0, 0, 0, config('app.timezone'))->startOfDay();
        $currentMonth = (int) $now->month;

        $monthLabels = [];
        $monthRevenue = [];
        $monthCosts = [];
        for ($m = 1; $m <= $currentMonth; $m++) {
            $ms = Carbon::create($year, $m, 1, 0, 0, 0, config('app.timezone'))->startOfMonth();
            $me = $m === $currentMonth
                ? $now->copy()
                : $ms->copy()->endOfMonth()->endOfDay();

            $monthLabels[] = $ms->format('M');

            $monthRevenue[] = round((float) ClientProjectInvoice::query()
                ->where('status', ClientProjectInvoice::STATUS_PAID)
                ->whereBetween('paid_at', [$ms, $me])
                ->sum('paid_amount'), 2);

            $monthCosts[] = round((float) ClientProjectExpense::query()
                ->whereBetween('occurred_on', [$ms->toDateString(), $me->toDateString()])
                ->sum('amount')
                - (float) ClientProjectReturn::query()
                    ->whereBetween('occurred_on', [$ms->toDateString(), $me->toDateString()])
                    ->sum('amount'), 2);
        }

        $ytdEnd = $now->copy();
        $ytdRevenue = (float) ClientProjectInvoice::query()
            ->where('status', ClientProjectInvoice::STATUS_PAID)
            ->whereBetween('paid_at', [$yearStart, $ytdEnd])
            ->sum('paid_amount');

        $ytdCosts = (float) ClientProjectExpense::query()
            ->whereBetween('occurred_on', [$yearStart->toDateString(), $ytdEnd->toDateString()])
            ->sum('amount');
        $ytdReturns = (float) ClientProjectReturn::query()
            ->whereBetween('occurred_on', [$yearStart->toDateString(), $ytdEnd->toDateString()])
            ->sum('amount');
        $ytdNetCosts = max(0.0, $ytdCosts - $ytdReturns);
        $ytdProfit = $ytdRevenue - $ytdNetCosts;

        $prevYear = $year - 1;
        $prevStart = Carbon::create($prevYear, 1, 1, 0, 0, 0, config('app.timezone'))->startOfDay();
        $prevEnd = Carbon::create($prevYear, 12, 31, 23, 59, 59, config('app.timezone'))->endOfDay();
        $prevYearRevenue = (float) ClientProjectInvoice::query()
            ->where('status', ClientProjectInvoice::STATUS_PAID)
            ->whereBetween('paid_at', [$prevStart, $prevEnd])
            ->sum('paid_amount');

        $ytdCollected = $ytdRevenue;

        return [
            'year' => $year,
            'month_labels' => $monthLabels,
            'month_revenue' => $monthRevenue,
            'month_costs' => $monthCosts,
            'month_net' => array_map(fn ($r, $c) => round($r - max(0, $c), 2), $monthRevenue, $monthCosts),
            'revenue' => round($ytdRevenue, 2),
            'costs' => round($ytdNetCosts, 2),
            'profit' => round($ytdProfit, 2),
            'collected_invoice_basis' => round($ytdCollected, 2),
            'previous_year_revenue' => round($prevYearRevenue, 2),
            'previous_year_label' => (string) $prevYear,
        ];
    }

    private function buildSummaryCards(Collection $projects, Carbon $now, int $year): array
    {
        $active = $projects->filter(fn (ClientProject $p) => $this->isActiveProject($p));

        $activeValue = (float) $active->sum(fn (ClientProject $p) => (float) ($p->total_amount ?? 0));

        $outstanding = (float) ClientProjectInvoice::query()
            ->where('status', ClientProjectInvoice::STATUS_SENT)
            ->sum('total');

        $monthStart = $now->copy()->startOfMonth();
        $cashThisMonth = (float) ClientProjectInvoice::query()
            ->where('status', ClientProjectInvoice::STATUS_PAID)
            ->whereBetween('paid_at', [$monthStart, $now])
            ->sum('paid_amount');

        $withValue = $projects->filter(fn (ClientProject $p) => (float) ($p->total_amount ?? 0) > 0
            && $p->isAccepted());
        $avgValue = $withValue->isEmpty()
            ? 0.0
            : (float) $withValue->avg(fn (ClientProject $p) => (float) $p->total_amount);

        $ytdRev = (float) ClientProjectInvoice::query()
            ->where('status', ClientProjectInvoice::STATUS_PAID)
            ->whereYear('paid_at', $year)
            ->sum('paid_amount');
        $ytdCosts = (float) ClientProjectExpense::query()
            ->whereYear('occurred_on', $year)
            ->sum('amount');
        $ytdRet = (float) ClientProjectReturn::query()
            ->whereYear('occurred_on', $year)
            ->sum('amount');
        $netCosts = max(0.0, $ytdCosts - $ytdRet);
        $grossMarginPct = $ytdRev > 0
            ? round((($ytdRev - $netCosts) / $ytdRev) * 100, 1)
            : null;

        $today = $now->toDateString();
        $overdueCount = ClientProjectInvoice::query()
            ->where('status', ClientProjectInvoice::STATUS_SENT)
            ->whereNotNull('due_on')
            ->whereDate('due_on', '<', $today)
            ->count();

        $remindersSent = ClientProjectStatusHistoryEntry::query()
            ->where('note', 'like', '%Payment reminder for invoice%')
            ->count();

        return [
            'active_projects_count' => $active->count(),
            'active_projects_value' => round($activeValue, 2),
            'outstanding_receivables' => round($outstanding, 2),
            'cash_this_month' => round($cashThisMonth, 2),
            'average_project_value' => round($avgValue, 2),
            'gross_margin_ytd_pct' => $grossMarginPct,
            'overdue_invoice_count' => $overdueCount,
            'reminders_sent_all_time' => $remindersSent,
        ];
    }

    private function isActiveProject(ClientProject $p): bool
    {
        return !in_array($p->status, [
            ClientProject::STATUS_REJECTED,
            ClientProject::STATUS_COMPLETED,
        ], true);
    }

    private function buildUpcomingSnapshot(Collection $projects, Carbon $now): array
    {
        $from = $now->copy()->startOfDay();
        $to = $now->copy()->addDays(30)->endOfDay();

        $rows = [];

        foreach ($projects as $p) {
            if ($p->status === ClientProject::STATUS_REJECTED) {
                continue;
            }
            if ($p->start_date) {
                $sd = Carbon::parse($p->start_date)->startOfDay();
                if ($sd->gte($from) && $sd->lte($to)) {
                    $rows[] = $this->upcomingRow($p, 'Start', $sd);
                }
            }
            if ($p->end_date) {
                $ed = Carbon::parse($p->end_date)->startOfDay();
                if ($ed->gte($from) && $ed->lte($to)) {
                    $rows[] = $this->upcomingRow($p, 'End / completion', $ed);
                }
            }
        }

        usort($rows, fn ($a, $b) => strcmp($a['sort'], $b['sort']));

        return array_slice($rows, 0, 20);
    }

    private function upcomingRow(ClientProject $p, string $kind, Carbon $d): array
    {
        return [
            'sort' => $d->toDateString().'-'.$p->id,
            'project_id' => $p->id,
            'name' => $p->name,
            'address' => $p->client_address,
            'date' => $d->toDateString(),
            'date_display' => ClientProject::formatDate($d),
            'kind' => $kind,
            'status' => $p->statusLabel(),
            'status_key' => $p->status,
            'next_payment' => $this->nextPaymentSummary($p),
            'url' => route('admin.client-projects.show', $p),
        ];
    }

    private function nextPaymentSummary(ClientProject $p): ?string
    {
        if ($p->isHourly()) {
            $inv = $p->invoices->first(fn ($i) => $i->isSent() && !$i->isPaid());

            return $inv
                ? 'Invoice '.$inv->invoice_number.' — '.ClientProject::formatMoney((float) $inv->total).' due '.ClientProject::formatDate($inv->due_on)
                : null;
        }

        $inst = $p->installments->first(fn ($i) => !$i->is_paid);
        if (!$inst) {
            return null;
        }
        $gross = $inst->effectiveTotal();
        $label = $inst->label ?: 'Installment';

        return ClientProject::formatMoney($gross).' — '.$label;
    }

    private function buildRecentActivity(): array
    {
        $entries = ClientProjectStatusHistoryEntry::query()
            ->with('project')
            ->orderByDesc('changed_at')
            ->limit(8)
            ->get();

        return $entries->map(function (ClientProjectStatusHistoryEntry $e) {
            $p = $e->project;

            return [
                'changed_at' => $e->changed_at?->toIso8601String(),
                'changed_display' => ClientProject::formatDateTime($e->changed_at),
                'note' => $e->note,
                'project_id' => $p?->id,
                'project_name' => $p?->name,
                'url' => $p ? route('admin.client-projects.show', $p) : null,
            ];
        })->all();
    }

    private function buildStatusOverview(Collection $projects): array
    {
        $groups = [];
        foreach (ClientProject::statuses() as $st) {
            $groups[$st] = [
                'key' => $st,
                'label' => ClientProject::statusLabels()[$st] ?? $st,
                'color' => ClientProject::statusColors()[$st] ?? '#6b7280',
                'count' => 0,
            ];
        }

        foreach ($projects as $p) {
            if (!isset($groups[$p->status])) {
                continue;
            }
            $groups[$p->status]['count']++;
        }

        return array_values($groups);
    }

    private function buildPipelineSummary(Collection $projects): array
    {
        $openStatuses = array_merge(ClientProject::preQuoteSentStatuses(), [ClientProject::STATUS_QUOTE_SENT]);
        $openProjects = $projects->filter(fn (ClientProject $p) => in_array($p->status, $openStatuses, true));

        $potential = 0.0;
        foreach ($openProjects as $p) {
            $fromQuotes = (float) $p->quotations->sum(function ($q) {
                return $q->hasContent() ? $q->grandTotal() : 0.0;
            });
            if ($fromQuotes >= 0.01) {
                $potential += $fromQuotes;
            } elseif ((float) ($p->total_amount ?? 0) > 0) {
                $potential += (float) $p->total_amount * (1 + ClientProject::HST_RATE);
            }
        }
        $fenceOpen = (int) FenceQuotation::query()->whereNull('client_project_id')->count();
        $fenceValue = (float) FenceQuotation::query()->whereNull('client_project_id')->sum('grand_total');

        return [
            'open_quotations_count' => $openProjects->count(),
            'open_quotations_potential' => round($potential, 2),
            'standalone_fence_count' => $fenceOpen,
            'standalone_fence_value' => round($fenceValue, 2),
        ];
    }

    private function buildAttentionProjects(Collection $projects, Carbon $now): array
    {
        $today = $now->copy()->startOfDay();
        $items = [];

        foreach ($projects as $p) {
            if ($p->status === ClientProject::STATUS_REJECTED) {
                continue;
            }
            $reasons = [];

            $overdueInv = $p->invoices->filter(function ($inv) use ($today) {
                return $inv->isSent() && !$inv->isPaid() && $inv->due_on
                    && Carbon::parse($inv->due_on)->lt($today);
            });
            if ($overdueInv->isNotEmpty()) {
                $reasons[] = $overdueInv->count().' overdue invoice(s)';
            }

            if ($p->status === ClientProject::STATUS_IN_PROGRESS
                && $p->end_date
                && Carbon::parse($p->end_date)->lt($today)) {
                $reasons[] = 'Past scheduled end date';
            }

            if ($reasons !== []) {
                $items[] = [
                    'project_id' => $p->id,
                    'name' => $p->name,
                    'reasons' => $reasons,
                    'value' => round((float) ($p->total_amount ?? 0), 2),
                    'url' => route('admin.client-projects.show', $p),
                ];
            }
        }

        $high = $projects
            ->filter(fn (ClientProject $p) => $p->status !== ClientProject::STATUS_REJECTED)
            ->sortByDesc(fn (ClientProject $p) => (float) ($p->total_amount ?? 0))
            ->take(3)
            ->map(fn (ClientProject $p) => [
                'project_id' => $p->id,
                'name' => $p->name,
                'label' => 'High contract value',
                'value' => round((float) ($p->total_amount ?? 0), 2),
                'url' => route('admin.client-projects.show', $p),
            ])
            ->values()
            ->all();

        return [
            'alerts' => array_slice($items, 0, 12),
            'high_value' => $high,
        ];
    }

    private function buildOverdueInvoiceRows(): array
    {
        $today = now()->toDateString();

        return ClientProjectInvoice::query()
            ->with('project')
            ->where('status', ClientProjectInvoice::STATUS_SENT)
            ->whereNotNull('due_on')
            ->whereDate('due_on', '<', $today)
            ->orderBy('due_on')
            ->limit(25)
            ->get()
            ->map(function (ClientProjectInvoice $inv) {
                $p = $inv->project;

                return [
                    'invoice_id' => $inv->id,
                    'invoice_number' => $inv->invoice_number,
                    'due_on' => $inv->due_on?->toDateString(),
                    'due_display' => ClientProject::formatDate($inv->due_on),
                    'total' => round((float) $inv->total, 2),
                    'project_id' => $p?->id,
                    'project_name' => $p?->name,
                    'reminder_url' => $p ? route('admin.client-projects.invoices.reminder', [
                        'client_project' => $p->id,
                        'invoice' => $inv->id,
                    ]) : null,
                ];
            })
            ->all();
    }
}

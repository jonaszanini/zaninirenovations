<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ClientProject;
use App\Models\ClientProjectExpense;
use App\Models\ClientProjectInvoice;
use App\Support\CadMoney;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class GlobalFinancialsController extends Controller
{
    private const PER_PAGE = 50;

    public function invoices(Request $request)
    {
        $query = ClientProjectInvoice::query()
            ->with(['project'])
            ->orderByDesc('updated_at');

        $status = $request->input('status');
        if ($status && in_array($status, [
            ClientProjectInvoice::STATUS_DRAFT,
            ClientProjectInvoice::STATUS_SENT,
            ClientProjectInvoice::STATUS_PAID,
        ], true)) {
            $query->where('status', $status);
        }

        $search = trim((string) $request->input('search', ''));
        if (mb_strlen($search) >= 2) {
            $query->where(function ($q) use ($search) {
                $q->where('invoice_number', 'like', '%' . $search . '%')
                    ->orWhereHas('project', function ($sub) use ($search) {
                        $sub->where('name', 'like', '%' . $search . '%')
                            ->orWhere('client_name', 'like', '%' . $search . '%');
                    });
            });
        }

        $dateFrom = $request->input('date_from');
        $dateTo = $request->input('date_to');
        if ($dateFrom) {
            $query->where(function ($q) use ($dateFrom) {
                $q->whereDate('sent_at', '>=', $dateFrom)
                    ->orWhereDate('created_at', '>=', $dateFrom);
            });
        }
        if ($dateTo) {
            $query->where(function ($q) use ($dateTo) {
                $q->whereDate('sent_at', '<=', $dateTo)
                    ->orWhereDate('created_at', '<=', $dateTo);
            });
        }

        $invoices = $query->paginate(self::PER_PAGE)->withQueryString();

        $totals = $this->calculateInvoiceTotals($status, $search, $dateFrom, $dateTo);

        return view('admin.financials.invoices', compact(
            'invoices',
            'totals',
            'status',
            'search',
            'dateFrom',
            'dateTo'
        ));
    }

    private function calculateInvoiceTotals(?string $status, string $search, ?string $dateFrom, ?string $dateTo): array
    {
        $query = ClientProjectInvoice::query();

        if ($status) {
            $query->where('status', $status);
        }

        if (mb_strlen($search) >= 2) {
            $query->where(function ($q) use ($search) {
                $q->where('invoice_number', 'like', '%' . $search . '%')
                    ->orWhereHas('project', function ($sub) use ($search) {
                        $sub->where('name', 'like', '%' . $search . '%')
                            ->orWhere('client_name', 'like', '%' . $search . '%');
                    });
            });
        }

        if ($dateFrom) {
            $query->where(function ($q) use ($dateFrom) {
                $q->whereDate('sent_at', '>=', $dateFrom)
                    ->orWhereDate('created_at', '>=', $dateFrom);
            });
        }
        if ($dateTo) {
            $query->where(function ($q) use ($dateTo) {
                $q->whereDate('sent_at', '<=', $dateTo)
                    ->orWhereDate('created_at', '<=', $dateTo);
            });
        }

        $outstanding = (float) (clone $query)
            ->collectible()
            ->where('status', ClientProjectInvoice::STATUS_SENT)
            ->sum('total');

        $results = $query->select([
            DB::raw('COUNT(*) as count'),
            DB::raw('COALESCE(SUM(subtotal), 0) as subtotal'),
            DB::raw('COALESCE(SUM(hst), 0) as hst'),
            DB::raw('COALESCE(SUM(total), 0) as total'),
            DB::raw('COALESCE(SUM(CASE WHEN status = "paid" THEN total ELSE 0 END), 0) as paid'),
        ])->first();

        return [
            'count' => (int) $results->count,
            'subtotal' => round((float) $results->subtotal, 2),
            'hst' => round((float) $results->hst, 2),
            'total' => round((float) $results->total, 2),
            'paid' => round((float) $results->paid, 2),
            'outstanding' => round($outstanding, 2),
        ];
    }

    public function expenses(Request $request)
    {
        $query = ClientProjectExpense::query()
            ->with(['project'])
            ->orderByDesc('occurred_on')
            ->orderByDesc('id');

        $search = trim((string) $request->input('search', ''));
        if (mb_strlen($search) >= 2) {
            $query->where(function ($q) use ($search) {
                $q->where('description', 'like', '%' . $search . '%')
                    ->orWhereHas('project', function ($sub) use ($search) {
                        $sub->where('name', 'like', '%' . $search . '%')
                            ->orWhere('client_name', 'like', '%' . $search . '%');
                    });
            });
        }

        $dateFrom = $request->input('date_from');
        $dateTo = $request->input('date_to');
        if ($dateFrom) {
            $query->whereDate('occurred_on', '>=', $dateFrom);
        }
        if ($dateTo) {
            $query->whereDate('occurred_on', '<=', $dateTo);
        }

        $hasReceipt = $request->input('has_receipt');
        if ($hasReceipt === 'yes') {
            $query->whereNotNull('receipt_path')->where('receipt_path', '!=', '');
        } elseif ($hasReceipt === 'no') {
            $query->where(function ($q) {
                $q->whereNull('receipt_path')->orWhere('receipt_path', '');
            });
        }

        $expenses = $query->paginate(self::PER_PAGE)->withQueryString();

        $totals = $this->calculateExpenseTotals($search, $dateFrom, $dateTo, $hasReceipt);

        $expenseCategories = $this->getExpenseCategories();

        return view('admin.financials.expenses', compact(
            'expenses',
            'totals',
            'search',
            'dateFrom',
            'dateTo',
            'hasReceipt',
            'expenseCategories'
        ));
    }

    private function calculateExpenseTotals(string $search, ?string $dateFrom, ?string $dateTo, ?string $hasReceipt): array
    {
        $query = ClientProjectExpense::query();

        if (mb_strlen($search) >= 2) {
            $query->where(function ($q) use ($search) {
                $q->where('description', 'like', '%' . $search . '%')
                    ->orWhereHas('project', function ($sub) use ($search) {
                        $sub->where('name', 'like', '%' . $search . '%')
                            ->orWhere('client_name', 'like', '%' . $search . '%');
                    });
            });
        }

        if ($dateFrom) {
            $query->whereDate('occurred_on', '>=', $dateFrom);
        }
        if ($dateTo) {
            $query->whereDate('occurred_on', '<=', $dateTo);
        }

        if ($hasReceipt === 'yes') {
            $query->whereNotNull('receipt_path')->where('receipt_path', '!=', '');
        } elseif ($hasReceipt === 'no') {
            $query->where(function ($q) {
                $q->whereNull('receipt_path')->orWhere('receipt_path', '');
            });
        }

        $results = $query->select([
            DB::raw('COUNT(*) as count'),
            DB::raw('COALESCE(SUM(amount), 0) as total'),
        ])->first();

        return [
            'count' => (int) $results->count,
            'total' => round((float) $results->total, 2),
        ];
    }

    private function getExpenseCategories(): array
    {
        return [
            'materials' => 'Materials',
            'gas' => 'Gas',
            'tools' => 'Tools',
            'rental' => 'Equipment Rental',
            'other' => 'Other',
        ];
    }

    public function storeExpense(Request $request)
    {
        $request->merge([
            'amount' => CadMoney::stripGrouping($request->input('amount')),
        ]);

        $data = $request->validate([
            'client_project_id' => ['required', 'exists:client_projects,id'],
            'occurred_on' => ['required', 'date'],
            'description' => ['required', 'string', 'max:500'],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'receipt' => ['nullable', 'file', 'mimes:pdf,webp,jpeg,jpg,png', 'max:10240'],
        ]);

        $project = ClientProject::findOrFail($data['client_project_id']);

        if (! $project->isAccepted()) {
            return back()->with('error', 'Expenses are only available for accepted projects.');
        }

        DB::transaction(function () use ($request, $project, $data) {
            ClientProject::whereKey($project->id)->lockForUpdate()->first();

            $nextRef = (int) ClientProjectExpense::where('client_project_id', $project->id)->max('expense_ref') + 1;

            $expense = $project->expenses()->create([
                'expense_ref' => $nextRef,
                'occurred_on' => $data['occurred_on'],
                'description' => $data['description'],
                'amount' => $data['amount'],
                'receipt_path' => null,
            ]);

            if ($request->hasFile('receipt')) {
                $receiptPath = app(\App\Services\ProjectPhotoWebpStorage::class)->storeExpenseReceipt(
                    $request->file('receipt'),
                    $project->id,
                    'Receipt-' . $nextRef
                );
                $expense->update(['receipt_path' => $receiptPath]);
            }
        });

        return back()->with('success', 'Expense added to ' . $project->name . '.');
    }

    public function searchProjects(Request $request)
    {
        $q = trim((string) $request->input('q', ''));
        if (mb_strlen($q) < 2) {
            return response()->json([]);
        }

        $projects = ClientProject::query()
            ->whereIn('status', [
                ClientProject::STATUS_PROPOSAL_ACCEPTED,
                ClientProject::STATUS_PENDING_FIRST_PAYMENT,
                ClientProject::STATUS_SCHEDULED,
                ClientProject::STATUS_IN_PROGRESS,
                ClientProject::STATUS_AWAITING_FINAL_PAYMENT,
            ])
            ->where(function ($sub) use ($q) {
                $sub->where('name', 'like', '%' . $q . '%')
                    ->orWhere('client_name', 'like', '%' . $q . '%');
            })
            ->orderByDesc('updated_at')
            ->limit(20)
            ->get(['id', 'name', 'client_name']);

        return response()->json($projects->map(fn ($p) => [
            'id' => $p->id,
            'name' => $p->name,
            'label' => $p->client_name . ' — ' . $p->name,
        ])->values());
    }
}

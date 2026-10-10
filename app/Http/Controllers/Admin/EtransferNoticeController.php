<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ClientProjectInvoice;
use App\Models\EtransferNotice;
use App\Services\Etransfer\EtransferInvoiceMatcher;
use App\Services\Etransfer\EtransferNoticeService;
use App\Support\CadMoney;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class EtransferNoticeController extends Controller
{
    public function __construct(
        protected EtransferNoticeService $notices,
        protected EtransferInvoiceMatcher $matcher,
    ) {
    }

    public function index(Request $request): View
    {
        $status = (string) $request->input('status', EtransferNotice::STATUS_PENDING);
        if (! in_array($status, [
            EtransferNotice::STATUS_PENDING,
            EtransferNotice::STATUS_MATCHED,
            EtransferNotice::STATUS_IGNORED,
        ], true)) {
            $status = EtransferNotice::STATUS_PENDING;
        }

        $notices = EtransferNotice::query()
            ->with(['invoice.project'])
            ->where('status', $status)
            ->orderByDesc('received_at')
            ->orderByDesc('id')
            ->limit(100)
            ->get();

        $openInvoices = $status === EtransferNotice::STATUS_PENDING
            ? $this->matcher->openInvoices()
            : collect();

        $rows = $notices->map(function (EtransferNotice $notice) use ($openInvoices, $status) {
            if ($status !== EtransferNotice::STATUS_PENDING) {
                return [
                    'notice' => $notice,
                    'candidates' => [],
                    'best' => null,
                    'bestRecordable' => false,
                    'ambiguous' => false,
                    'alreadyPaid' => false,
                ];
            }

            $suggestion = $this->matcher->suggest($notice, $openInvoices);

            return [
                'notice' => $notice,
                'candidates' => $suggestion['candidates'],
                'best' => $suggestion['best'],
                'bestRecordable' => $suggestion['recordable'],
                'ambiguous' => $suggestion['ambiguous'],
                'alreadyPaid' => $suggestion['already_paid'],
            ];
        });

        $counts = [
            EtransferNotice::STATUS_PENDING => EtransferNotice::query()->pending()->count(),
            EtransferNotice::STATUS_MATCHED => EtransferNotice::query()->where('status', EtransferNotice::STATUS_MATCHED)->count(),
            EtransferNotice::STATUS_IGNORED => EtransferNotice::query()->where('status', EtransferNotice::STATUS_IGNORED)->count(),
        ];

        $invoiceOptions = $openInvoices->map(function (ClientProjectInvoice $invoice) {
            $project = $invoice->project;
            $client = $project?->invoiceBillToName() ?: ($project?->client_name ?? '');
            $label = trim($invoice->invoice_number.' · '.$client);

            return [
                'id' => $invoice->id,
                'name' => $label,
                'invoice_number' => $invoice->invoice_number,
                'label' => $label,
            ];
        })->values();

        return view('admin.financials.etransfers', [
            'rows' => $rows,
            'status' => $status,
            'counts' => $counts,
            'invoiceOptions' => $invoiceOptions,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $request->merge([
            'amount' => CadMoney::stripGrouping($request->input('amount')),
        ]);

        $data = $request->validate([
            'raw_text' => ['required', 'string', 'max:20000'],
            'sender_name' => ['nullable', 'string', 'max:180'],
            'sender_email' => ['nullable', 'email', 'max:180'],
            'amount' => ['nullable', 'numeric', 'min:0'],
            'reference_number' => ['nullable', 'string', 'max:80'],
            'message' => ['nullable', 'string', 'max:500'],
            'received_at' => ['nullable', 'date'],
        ]);

        $result = $this->notices->ingest($data, EtransferNotice::SOURCE_MANUAL);

        if ($result['status'] === 'duplicate') {
            return redirect()
                ->route('admin.financials.etransfers', ['status' => $result['notice']->status])
                ->with('warning', 'That Interac reference is already on file.');
        }

        return redirect()
            ->route('admin.financials.etransfers')
            ->with('success', 'E-transfer saved. Review the suggested invoice before recording payment.');
    }

    public function record(Request $request, EtransferNotice $etransfer_notice): RedirectResponse
    {
        $data = $request->validate([
            'invoice_id' => ['required', 'integer', 'exists:client_project_invoices,id'],
        ]);

        $invoice = ClientProjectInvoice::query()->with('project')->findOrFail($data['invoice_id']);
        $result = $this->notices->recordPayment($etransfer_notice, $invoice);

        if (! ($result['ok'] ?? false)) {
            return back()->with('error', $result['error'] ?? 'Could not record the payment.');
        }

        $redirect = redirect()
            ->route('admin.financials.etransfers')
            ->with('success', 'Payment recorded on '.$invoice->invoice_number.'.');

        if (! empty($result['warning'])) {
            $redirect->with('warning', $result['warning']);
        }

        return $redirect;
    }

    public function ignore(EtransferNotice $etransfer_notice): RedirectResponse
    {
        $this->notices->ignore($etransfer_notice);

        return redirect()
            ->route('admin.financials.etransfers')
            ->with('success', 'E-transfer ignored.');
    }

    public function searchInvoices(Request $request): JsonResponse
    {
        $q = trim((string) $request->input('q', ''));
        $invoices = $this->matcher->openInvoices();

        if (mb_strlen($q) >= 2) {
            $needle = mb_strtolower($q);
            $invoices = $invoices->filter(function (ClientProjectInvoice $invoice) use ($needle) {
                $project = $invoice->project;
                $hay = mb_strtolower(implode(' ', array_filter([
                    $invoice->invoice_number,
                    $project?->name,
                    $project?->client_name,
                    $project?->client_email,
                ])));

                return str_contains($hay, $needle);
            });
        }

        return response()->json($invoices->take(20)->map(function (ClientProjectInvoice $invoice) {
            $project = $invoice->project;
            $client = $project?->invoiceBillToName() ?: ($project?->client_name ?? '');
            $label = trim($invoice->invoice_number.' · '.$client);

            return [
                'id' => $invoice->id,
                'name' => $label,
                'invoice_number' => $invoice->invoice_number,
                'label' => $label,
            ];
        })->values());
    }
}

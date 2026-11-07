<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ClientProject;
use App\Services\Invoice\PrepareBillingService;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/**
 * Admin review that turns an accepted quotation's payment plan into DRAFT
 * {@see \App\Models\ClientProjectInvoice} rows. Nothing is issued, emailed,
 * or charged here — that stays on the existing invoice editor / Send action.
 */
class PrepareBillingController extends Controller
{
    public function show(ClientProject $client_project, PrepareBillingService $billing)
    {
        $this->authorizeAdmin();
        $client_project->load(['installments', 'invoices', 'quotations']);
        $preview = $billing->preview($client_project);

        return view('admin.client_projects.billing.prepare', [
            'project' => $client_project,
            'preview' => $preview,
        ]);
    }

    public function confirm(Request $request, ClientProject $client_project, PrepareBillingService $billing)
    {
        $this->authorizeAdmin();
        $dueOn = $request->input('due_on', []);
        if (! is_array($dueOn)) {
            $dueOn = [];
        }

        try {
            $invoices = $billing->confirm($client_project, $dueOn);
        } catch (ValidationException $e) {
            return redirect()
                ->route('admin.client-projects.billing.prepare', $client_project)
                ->withErrors($e->errors())
                ->withInput();
        }

        $n = count($invoices);
        $message = $n === 0
            ? 'No new drafts — every installment already has an invoice. Nothing was issued.'
            : ($n === 1
                ? '1 draft invoice is ready. Review and send from Invoices — nothing was issued.'
                : $n.' draft invoices are ready. Review and send from Invoices — nothing was issued.');

        return redirect()
            ->route('admin.client-projects.show', $client_project)
            ->withFragment('invoice')
            ->with('success', $message);
    }

    /**
     * Canonical admin gate used by user administration. This review/write
     * surface is admin-only; staff keep existing invoice send/mark-paid routes.
     */
    private function authorizeAdmin(): void
    {
        if (! auth()->user()?->isAdmin()) {
            abort(403);
        }
    }
}

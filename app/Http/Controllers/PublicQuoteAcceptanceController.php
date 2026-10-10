<?php

namespace App\Http\Controllers;

use App\Models\ClientProjectQuotation;
use App\Services\QuoteAcceptanceService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PublicQuoteAcceptanceController extends Controller
{
    public function handle(
        Request $request,
        ClientProjectQuotation $quotation,
        QuoteAcceptanceService $acceptance,
    ): View|RedirectResponse {
        if ($request->isMethod('post')) {
            return $this->store($request, $quotation, $acceptance);
        }

        return $this->show($request, $quotation);
    }

    public function show(Request $request, ClientProjectQuotation $quotation): View
    {
        return $this->page($request, $quotation, null);
    }

    public function store(
        Request $request,
        ClientProjectQuotation $quotation,
        QuoteAcceptanceService $acceptance,
    ): View|RedirectResponse {
        $status = $acceptance->accept(
            $quotation,
            ClientProjectQuotation::ACCEPTED_VIA_LINK,
            $request->ip(),
            null,
        );

        $quotation->refresh()->load('project');

        if ($status === QuoteAcceptanceService::STATUS_ACCEPTED) {
            return redirect()
                ->to($quotation->signedAcceptUrl())
                ->with('quote_accept_status', QuoteAcceptanceService::STATUS_ACCEPTED);
        }

        return $this->page($request, $quotation, $status);
    }

    private function page(Request $request, ClientProjectQuotation $quotation, ?string $postStatus): View
    {
        $quotation->loadMissing('project');
        $project = $quotation->project;

        $flashStatus = $request->session()->get('quote_accept_status');
        $status = $postStatus
            ?? ($flashStatus ? (string) $flashStatus : null)
            ?? $this->pageStatus($quotation, $project);

        return view('site.quote_accept.show', [
            'quotation' => $quotation,
            'project' => $project,
            'status' => $status,
            'formAction' => $request->fullUrl(),
        ]);
    }

    private function pageStatus(ClientProjectQuotation $quotation, $project): string
    {
        if ($quotation->isAccepted()) {
            return QuoteAcceptanceService::STATUS_ALREADY_ACCEPTED;
        }
        if ($project === null || $project->isClosed() || $quotation->isRejected() || $quotation->isSuperseded()) {
            return QuoteAcceptanceService::STATUS_INVALID_STATE;
        }
        if ($quotation->isSourceDraft()) {
            return QuoteAcceptanceService::STATUS_INVALID_STATE;
        }
        if ($quotation->source_type === \App\Models\ClientProjectQuotation::SOURCE_MILLWORK && $quotation->source_id) {
            $millwork = \App\Models\MillworkQuotation::query()->find($quotation->source_id);
            if ($millwork && $millwork->hasUnplacedCutParts()) {
                return QuoteAcceptanceService::STATUS_INCOMPLETE;
            }
        }

        return 'ready';
    }
}

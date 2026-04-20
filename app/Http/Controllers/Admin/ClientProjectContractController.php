<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Mail\ClientProjectContractClient;
use App\Models\ClientProject;
use App\Models\ClientProjectContract;
use App\Services\ClientProjectStatusService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;

/**
 * Manages the rich-text Contract attached to a ClientProject.
 *
 * Workflow:
 *   - edit()         shows the TinyMCE editor (creating the contract row on demand)
 *   - update()       saves the html_content (Save Draft / Save & Close)
 *   - downloadPdf()  generates a fresh PDF on the fly and returns it inline
 *   - send()         generates a fresh PDF, persists it under storage/app/contracts/,
 *                    emails it to the client, and updates sent_at + version
 */
class ClientProjectContractController extends Controller
{
    /**
     * Show the editor (TinyMCE). Creates an empty contract record if one
     * doesn't exist yet so the form has something to bind against.
     */
    public function edit(ClientProject $client_project)
    {
        $contract = $client_project->contract()->firstOrCreate([], [
            'html_content' => '',
            'version' => 1,
        ]);

        return view('admin.client_projects.contract.edit', [
            'project' => $client_project,
            'contract' => $contract,
        ]);
    }

    /**
     * Save the contract body. The "intent" field controls where we
     * redirect: "draft" stays on the editor, "close" goes back to the
     * project's Contract tab.
     *
     * No HTML sanitization is applied because the requirements explicitly
     * say only the site owner uses the admin.
     */
    public function update(Request $request, ClientProject $client_project)
    {
        $data = $request->validate([
            'html_content' => ['nullable', 'string'],
            'intent' => ['nullable', 'in:draft,close'],
        ]);

        $contract = $client_project->contract()->firstOrCreate([], [
            'html_content' => '',
            'version' => 1,
        ]);

        $contract->html_content = $data['html_content'] ?? '';
        $contract->save();

        $intent = $data['intent'] ?? 'draft';

        if ($intent === 'close') {
            return redirect()
                ->route('admin.client-projects.show', $client_project)
                ->with('success', 'Contract saved.')
                ->withFragment('contract');
        }

        return redirect()
            ->route('admin.client-projects.contract.edit', $client_project)
            ->with('success', 'Draft saved.');
    }

    /**
     * Render and stream the PDF inline (so the browser displays it instead
     * of downloading by default). Used by the "Download PDF" button.
     */
    public function downloadPdf(ClientProject $client_project)
    {
        $contract = $client_project->contract;

        if (!$contract || !$contract->hasContent()) {
            return back()->with('error', 'There is no contract to download yet. Please write the contract body first.');
        }

        $pdf = $this->renderPdf($client_project, $contract);

        $filename = $this->pdfFilename($client_project, $contract);

        // stream() returns the file inline in the browser; download() forces
        // a download. Inline is friendlier when previewing.
        return $pdf->stream($filename);
    }

    /**
     * Generate a fresh PDF, persist it under storage/app/contracts/, email
     * it to the client, and bump the version + sent_at.
     */
    public function send(ClientProject $client_project, ClientProjectStatusService $statusService)
    {
        $contract = $client_project->contract;

        if (!$contract || !$contract->hasContent()) {
            return back()->with('error', 'Please write the contract body before sending it.');
        }

        if (!$client_project->hasContactInfoForContract()) {
            $missing = implode(', ', $client_project->missingContactFieldsForContract());

            return back()->with('error', "Cannot send the contract — missing client info: {$missing}.");
        }

        // If the contract has already been sent at least once, the next save
        // is conceptually a new version (so we keep an audit trail of PDFs).
        if ($contract->isSent()) {
            $contract->version = (int) $contract->version + 1;
        }

        $pdf = $this->renderPdf($client_project, $contract);
        $bytes = $pdf->output();

        // Persist the PDF on the local disk so we can re-download the exact
        // file that was emailed even after the contract is edited again.
        $relativePath = sprintf(
            'contracts/project-%d-v%d.pdf',
            $client_project->id,
            $contract->version
        );

        Storage::disk('local')->put($relativePath, $bytes);

        $contract->pdf_path = $relativePath;
        $contract->sent_at = now();
        $contract->save();

        try {
            $mailer = Mail::to($client_project->client_email);
            if ($cc = $client_project->secondaryClientEmail()) {
                $mailer->cc($cc);
            }
            $mailer->send(new ClientProjectContractClient(
                $client_project,
                $bytes,
                $this->pdfFilename($client_project, $contract)
            ));
        } catch (\Throwable $e) {
            Log::error('Failed to email contract', [
                'project_id' => $client_project->id,
                'contract_id' => $contract->id,
                'exception' => $e->getMessage(),
            ]);

            return redirect()
                ->route('admin.client-projects.show', $client_project)
                ->withFragment('contract')
                ->with('error', 'The PDF was generated but the email could not be sent: '.$e->getMessage());
        }

        // Audit trail: contract sends aren't always tied to a status
        // change (e.g. re-sending a revised contract), so record an
        // explicit event row separate from any transition.
        try {
            $statusService->recordEvent(
                $client_project,
                "Contract v{$contract->version} emailed to {$client_project->clientEmailRecipientsLabel()}."
            );
        } catch (\Throwable $e) {
            Log::warning('Failed to record contract-send history entry', [
                'project_id' => $client_project->id,
                'exception' => $e->getMessage(),
            ]);
        }

        return redirect()
            ->route('admin.client-projects.show', $client_project)
            ->withFragment('contract')
            ->with('success', "Contract emailed to {$client_project->clientEmailRecipientsLabel()} (v{$contract->version}).");
    }

    /**
     * Build the PDF object from the Blade template using the Contractor /
     * Client info from the project. Centralised so download/email use the
     * exact same template.
     *
     * After loading the view we force ->render() so dompdf has finalised
     * pagination, then stamp the "Page X of Y" footer text via
     * $canvas->page_text(). We can't do that from the Blade template
     * because dompdf v2's CPDF::page_text() resolves the page count
     * IMMEDIATELY (it iterates $this->_pages at call time), so an inline
     * <script type="text/php"> only ever sees the pages laid out before
     * its position in the document — yielding "Page 1 of 1" on page 1
     * and a blank footer on page 2. Stamping after render() side-steps
     * the issue completely.
     */
    protected function renderPdf(ClientProject $project, ClientProjectContract $contract)
    {
        $pdf = Pdf::loadView('admin.client_projects.contract.pdf', [
            'project' => $project,
            'contract' => $contract,
        ])
            ->setPaper('a4')
            // Allow @font-face / file:// URLs (used for Roboto + the PNG logo).
            ->setOption('isRemoteEnabled', true)
            ->setOption('isHtml5ParserEnabled', true)
            // Roboto is registered via @font-face in the template and is the
            // default for everything except elements that explicitly opt out.
            ->setOption('defaultFont', 'Roboto');

        $pdf->render();

        $this->stampPageNumbers($pdf);

        return $pdf;
    }

    /**
     * Draw "Page X of Y" on every page of an already-rendered PDF.
     *
     * Coordinates are PDF points from the top-left of the page;
     * A4 = 595 × 842 pt. The right-edge x is computed against a
     * conservative max-width string so the text stays right-aligned
     * even when the count grows to two digits.
     */
    protected function stampPageNumbers($pdf): void
    {
        $dompdf = $pdf->getDomPDF();
        $canvas = $dompdf->getCanvas();
        $fontMetrics = $dompdf->getFontMetrics();

        $font = $fontMetrics->getFont('Roboto', 'normal') ?: $fontMetrics->getFont('Helvetica', 'normal');
        $size = 9;
        $color = [0.42, 0.45, 0.50]; // #6b7280

        $text = 'Page {PAGE_NUM} of {PAGE_COUNT}';
        $reservedWidth = $fontMetrics->getTextWidth('Page 99 of 99', $font, $size);

        $x = $canvas->get_width() - 60 - $reservedWidth;
        $y = $canvas->get_height() - 50;

        $canvas->page_text($x, $y, $text, $font, $size, $color);
    }

    /** Stable, human-friendly filename for the generated PDF. */
    protected function pdfFilename(ClientProject $project, ClientProjectContract $contract): string
    {
        $slug = \Illuminate\Support\Str::slug($project->name) ?: 'project-'.$project->id;

        return "contract-{$slug}-v{$contract->version}.pdf";
    }
}

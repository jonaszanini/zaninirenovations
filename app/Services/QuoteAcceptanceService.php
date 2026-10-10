<?php

namespace App\Services;

use App\Models\ClientProject;
use App\Models\ClientProjectQuotation;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Idempotent quotation acceptance (public signed link, API bot, admin).
 * Does not email or SMS the client.
 */
class QuoteAcceptanceService
{
    public const STATUS_ACCEPTED = 'accepted';

    public const STATUS_ALREADY_ACCEPTED = 'already_accepted';

    public const STATUS_NOT_FOUND = 'not_found';

    public const STATUS_INVALID_STATE = 'invalid_state';

    public const STATUS_INCOMPLETE = 'incomplete';

    public function __construct(
        private ClientProjectStatusService $statusService,
    ) {
    }

    /**
     * @return array{status: string, quotation: ?ClientProjectQuotation}
     */
    public function acceptByNumbers(
        string $projectNumber,
        string $quoteNumber,
        string $via,
        ?string $ip = null,
        ?string $sourceNote = null,
    ): array {
        $project = $this->findProjectByNumber($projectNumber);
        if ($project === null) {
            return ['status' => self::STATUS_NOT_FOUND, 'quotation' => null];
        }

        $quotation = ClientProjectQuotation::findForProjectAndQuoteNumber($project, $quoteNumber);
        if ($quotation === null) {
            return ['status' => self::STATUS_NOT_FOUND, 'quotation' => null];
        }

        return [
            'status' => $this->accept($quotation, $via, $ip, $sourceNote),
            'quotation' => $quotation->fresh(),
        ];
    }

    public function accept(
        ClientProjectQuotation $quotation,
        string $via,
        ?string $ip = null,
        ?string $sourceNote = null,
    ): string {
        $quotation->loadMissing('project');
        $project = $quotation->project;

        if ($quotation->isAccepted()) {
            return self::STATUS_ALREADY_ACCEPTED;
        }

        if ($project === null || $project->isClosed() || $quotation->isRejected() || $quotation->isSuperseded()) {
            return self::STATUS_INVALID_STATE;
        }

        if ($quotation->isSourceDraft()) {
            return self::STATUS_INVALID_STATE;
        }

        if ($quotation->source_type === ClientProjectQuotation::SOURCE_MILLWORK && $quotation->source_id) {
            $millwork = \App\Models\MillworkQuotation::query()->find($quotation->source_id);
            if ($millwork && $millwork->hasUnplacedCutParts()) {
                return self::STATUS_INCOMPLETE;
            }
        }

        $viaLabel = ClientProjectQuotation::acceptedViaLabel($via);
        $quoteLabel = $quotation->displayQuoteNumber();
        $note = "{$quotation->typeLabel()} {$quoteLabel} accepted via {$viaLabel}.";
        if (filled($sourceNote)) {
            $note .= ' Source: '.trim((string) $sourceNote).'.';
        }

        DB::transaction(function () use ($quotation, $project, $via, $ip, $sourceNote, $note) {
            $quotation->status = ClientProjectQuotation::STATUS_ACCEPTED;
            $quotation->accepted_at = now();
            $quotation->accepted_via = $via;
            $quotation->accepted_ip = $ip !== null && $ip !== '' ? substr($ip, 0, 45) : null;
            $quotation->accepted_source_note = filled($sourceNote) ? substr(trim((string) $sourceNote), 0, 255) : null;
            $quotation->save();

            $project->refresh();
            $project->syncFixedContractTotalsFromQuotation();

            if ($project->canPromoteToProposalAcceptedFromQuotation()) {
                $this->statusService->transition(
                    $project,
                    ClientProject::STATUS_PROPOSAL_ACCEPTED,
                    $note,
                );
            } else {
                $this->statusService->recordEvent($project, $note);
            }
        });

        Log::info('Quotation accepted', [
            'quotation_id' => $quotation->id,
            'quotation_number' => $quotation->quotation_number,
            'quote_number' => $quotation->displayQuoteNumber(),
            'project_id' => $project->id,
            'project_number' => $project->referenceCode(),
            'via' => $via,
            'ip' => $ip,
            'source_note' => $sourceNote,
        ]);

        return self::STATUS_ACCEPTED;
    }

    public function findProjectByNumber(string $projectNumber): ?ClientProject
    {
        $id = ClientProject::idFromReferenceSearch($projectNumber);
        if ($id === null) {
            return null;
        }

        return ClientProject::query()->find($id);
    }
}

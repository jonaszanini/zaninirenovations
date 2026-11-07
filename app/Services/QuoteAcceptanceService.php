<?php

namespace App\Services;

use App\Mail\ClientProjectQuoteAcceptedAdmin;
use App\Models\ClientCommunicationLog;
use App\Models\ClientProject;
use App\Models\ClientProjectQuotation;
use App\Support\BusinessContact;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

/**
 * Idempotent quotation acceptance (public signed link, API bot, admin).
 * Does not email or SMS the client. A new acceptance from the public
 * Accept this quote link (quotation email and PDF) emails the business inbox.
 */
class QuoteAcceptanceService
{
    public const STATUS_ACCEPTED = 'accepted';

    public const STATUS_ALREADY_ACCEPTED = 'already_accepted';

    public const STATUS_NOT_FOUND = 'not_found';

    public const STATUS_INVALID_STATE = 'invalid_state';

    public const STATUS_INCOMPLETE = 'incomplete';

    public const STATUS_REVISING = 'revising';

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
            return self::STATUS_REVISING;
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

        if ($via === ClientProjectQuotation::ACCEPTED_VIA_LINK) {
            $this->notifyBusinessOfLinkAcceptance($quotation->fresh(['project']) ?? $quotation);
        }

        return self::STATUS_ACCEPTED;
    }

    /**
     * Email the business inbox after a client accepts from the shared
     * email/PDF link. A mail failure does not undo the acceptance.
     */
    private function notifyBusinessOfLinkAcceptance(ClientProjectQuotation $quotation): void
    {
        $quotation->loadMissing('project');
        $project = $quotation->project;
        if ($project === null) {
            return;
        }

        $to = BusinessContact::DEFAULT_EMAIL;
        $mailable = new ClientProjectQuoteAcceptedAdmin($quotation, $project);
        $subject = $mailable->subjectLine();
        $description = "{$project->client_name} accepted {$quotation->displayQuoteNumber()}. Quote status is now {$quotation->statusLabel()}. Project status is now {$project->statusLabel()}.";

        try {
            Mail::to($to)->send($mailable);

            app(ClientCommunicationLogger::class)->logEmail(
                $project,
                ClientCommunicationLog::REASON_QUOTE_ACCEPTED_ADMIN,
                $to,
                null,
                $subject,
                $description,
                ClientCommunicationLog::DIRECTION_TO_ADMIN,
            );
        } catch (\Throwable $e) {
            Log::warning('Quote acceptance notice email failed', [
                'quotation_id' => $quotation->id,
                'client_project_id' => $project->id,
                'message' => $e->getMessage(),
            ]);

            app(ClientCommunicationLogger::class)->logEmail(
                $project,
                ClientCommunicationLog::REASON_QUOTE_ACCEPTED_ADMIN,
                $to,
                null,
                $subject,
                $description,
                ClientCommunicationLog::DIRECTION_TO_ADMIN,
                false,
                $e->getMessage(),
            );
        }
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

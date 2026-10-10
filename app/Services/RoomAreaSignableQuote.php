<?php

namespace App\Services;

use App\Models\ClientProjectQuotation;
use App\Models\RoomAreaProject;
use App\Models\StandardQuotation;
use Illuminate\Support\Collection;
use Symfony\Component\HttpKernel\Exception\HttpException;

class RoomAreaSignableQuote
{
    public const DRAFT_MESSAGE = 'Publish the quote before signing. Drafts cannot be signed.';

    public const REVISING_MESSAGE = 'This quote is being revised. Finish the revision and publish it before signing.';

    public const MISSING_QUOTE_MESSAGE = 'Choose the published quote version this signature belongs to.';

    public const SUPERSEDED_MESSAGE = 'This quote version has been replaced. Sign the current published version.';

    /**
     * @return list<array{id: int, quote_number: string, version: int, is_draft: bool, is_revising: bool, can_sign: bool}>
     */
    public function options(RoomAreaProject $project): array
    {
        if (! $project->client_project_id) {
            return [];
        }

        $quotes = StandardQuotation::query()
            ->where('client_project_id', $project->client_project_id)
            ->orderByDesc('id')
            ->get(['id', 'quote_number', 'version', 'is_draft']);

        $revisingIds = $this->revisingIds($quotes->pluck('id'));

        return $quotes->map(function (StandardQuotation $quote) use ($revisingIds) {
            $revising = $revisingIds->contains((int) $quote->id);
            $draft = (bool) $quote->is_draft;
            $superseded = $this->isSuperseded($quote);

            return [
                'id' => (int) $quote->id,
                'quote_number' => (string) $quote->quote_number,
                'version' => (int) ($quote->version ?: 1),
                'is_draft' => $draft,
                'is_revising' => $revising,
                'is_superseded' => $superseded,
                'can_sign' => ! $draft && ! $revising && ! $superseded,
            ];
        })->values()->all();
    }

    public function findForProject(RoomAreaProject $project, int $quoteId): StandardQuotation
    {
        if (! $project->client_project_id) {
            abort(404);
        }

        $quote = StandardQuotation::query()->find($quoteId);
        if (! $quote || (int) $quote->client_project_id !== (int) $project->client_project_id) {
            abort(404);
        }

        return $quote;
    }

    public function assertSignable(StandardQuotation $quote, ?int $expectedVersion = null): void
    {
        if ($expectedVersion !== null && (int) ($quote->version ?: 1) !== $expectedVersion) {
            throw new HttpException(422, self::SUPERSEDED_MESSAGE);
        }
        if ($this->isSuperseded($quote)) {
            throw new HttpException(422, self::SUPERSEDED_MESSAGE);
        }
        if ($this->isRevising($quote)) {
            throw new HttpException(422, self::REVISING_MESSAGE);
        }
        if ($quote->is_draft) {
            throw new HttpException(422, self::DRAFT_MESSAGE);
        }
    }

    public function isSuperseded(StandardQuotation $quote): bool
    {
        return StandardQuotation::query()
            ->where('quote_number', $quote->quote_number)
            ->where('id', '!=', $quote->id)
            ->where('version', '>', (int) ($quote->version ?: 1))
            ->exists();
    }

    public function isRevising(StandardQuotation $quote): bool
    {
        if (! $quote->is_draft) {
            return false;
        }

        return ClientProjectQuotation::query()
            ->where('source_type', ClientProjectQuotation::SOURCE_STANDARD)
            ->where('source_id', $quote->id)
            ->exists();
    }

    /**
     * @param  Collection<int, int|string>|array<int, int|string>  $quoteIds
     * @return Collection<int, int>
     */
    private function revisingIds($quoteIds): Collection
    {
        $ids = Collection::make($quoteIds)->map(fn ($id) => (int) $id)->filter()->values();
        if ($ids->isEmpty()) {
            return collect();
        }

        return ClientProjectQuotation::query()
            ->where('source_type', ClientProjectQuotation::SOURCE_STANDARD)
            ->whereIn('source_id', $ids)
            ->pluck('source_id')
            ->map(fn ($id) => (int) $id);
    }
}

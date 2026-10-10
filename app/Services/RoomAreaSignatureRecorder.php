<?php

namespace App\Services;

use App\Models\RoomAreaProject;
use App\Models\RoomAreaSignature;
use App\Models\StandardQuotation;
use App\Models\User;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\HttpException;

class RoomAreaSignatureRecorder
{
    public const ACTIVE_EXISTS_MESSAGE = 'This plan already has a signature. Void it with a reason before signing again.';

    public const VOID_REASON_MESSAGE = 'Enter a reason before voiding this signature.';

    public const MISSING_INK_MESSAGE = 'Draw the signature on the pad.';

    public const PNG_MESSAGE = 'Use a PNG signature image.';

    public const NAME_MESSAGE = 'Type the signer\'s name.';

    public const INACTIVE_MESSAGE = 'That signature is already voided.';

    private RoomAreaSignableQuote $quotes;

    private RoomAreaSignatureStorage $storage;

    private RoomAreaPlanDocument $document;

    public function __construct(
        ?RoomAreaSignableQuote $quotes = null,
        ?RoomAreaSignatureStorage $storage = null,
        ?RoomAreaPlanDocument $document = null
    ) {
        $this->quotes = $quotes ?? new RoomAreaSignableQuote;
        $this->storage = $storage ?? new RoomAreaSignatureStorage;
        $this->document = $document ?? new RoomAreaPlanDocument;
    }

    public function active(RoomAreaProject $project): ?RoomAreaSignature
    {
        $project->loadMissing('signatures');

        return $project->signatures->first(fn (RoomAreaSignature $row) => $row->isActive());
    }

    public function record(
        Request $request,
        RoomAreaProject $project,
        User $admin,
        StandardQuotation $quote,
        string $signerName,
        string $pngDataUrl
    ): RoomAreaSignature {
        if ($this->active($project)) {
            throw new HttpException(409, self::ACTIVE_EXISTS_MESSAGE);
        }

        $this->quotes->assertSignable($quote);

        $png = $this->storage->decodePngDataUrl($pngDataUrl);
        if ($png === null) {
            throw new HttpException(422, self::PNG_MESSAGE);
        }

        $signedAt = now();
        $pngPath = $this->storage->put($this->storage->uniquePngPath($project), $png);
        $pdf = $this->document->pdfBinary($project, [
            'name' => $signerName,
            'date' => $signedAt->format('F j, Y'),
            'image' => $this->storage->dataUriForPath($pngPath),
            'signed' => true,
        ]);
        $pdfPath = $this->storage->put($this->storage->uniquePdfPath($project), $pdf);

        return RoomAreaSignature::query()->create([
            'room_area_project_id' => $project->id,
            'standard_quotation_id' => $quote->id,
            'quote_version' => (int) ($quote->version ?: 1),
            'signer_name' => $signerName,
            'signed_at' => $signedAt,
            'admin_user_id' => $admin->id,
            'ip' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'snapshot_hash' => hash('sha256', $pdf),
            'pdf_path' => $pdfPath,
            'signature_path' => $pngPath,
        ]);
    }

    public function void(RoomAreaSignature $signature, User $admin, string $reason): RoomAreaSignature
    {
        if (! $signature->isActive()) {
            throw new HttpException(409, self::INACTIVE_MESSAGE);
        }
        $reason = trim($reason);
        if ($reason === '') {
            throw new HttpException(422, self::VOID_REASON_MESSAGE);
        }

        $signature->voided_at = now();
        $signature->void_reason = $reason;
        $signature->voided_by = $admin->id;
        $signature->save();

        return $signature->fresh();
    }

    public function frozenPdf(RoomAreaSignature $signature): ?string
    {
        return $this->storage->get($signature->pdf_path);
    }
}

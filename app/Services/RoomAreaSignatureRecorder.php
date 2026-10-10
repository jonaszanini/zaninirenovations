<?php

namespace App\Services;

use App\Models\RoomAreaProject;
use App\Models\RoomAreaSignature;
use App\Models\StandardQuotation;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\HttpException;

class RoomAreaSignatureRecorder
{
    public const ACTIVE_EXISTS_MESSAGE = 'This plan already has a signature. Void it with a reason before signing again.';

    public const VOID_REASON_MESSAGE = 'Enter a reason before voiding this signature.';

    public const MISSING_INK_MESSAGE = 'Draw the signature on the pad.';

    public const PNG_MESSAGE = 'Use a PNG signature image.';

    public const NAME_MESSAGE = 'Type the signer\'s name.';

    public const INACTIVE_MESSAGE = 'That signature is already voided.';

    public const MISSING_FROZEN_PDF_MESSAGE = 'The signed plan PDF is missing from storage. The live plan was not rebuilt.';

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
        string $pngDataUrl,
        ?int $expectedVersion = null
    ): RoomAreaSignature {
        $png = $this->storage->decodePngDataUrl($pngDataUrl);
        if ($png === null) {
            throw new HttpException(422, self::PNG_MESSAGE);
        }

        $written = [];
        try {
            return DB::transaction(function () use ($request, $project, $admin, $quote, $signerName, $png, $expectedVersion, &$written) {
                $locked = RoomAreaProject::query()->whereKey($project->id)->lockForUpdate()->first();
                if (! $locked) {
                    abort(404);
                }
                $locked->unsetRelation('signatures');
                if ($this->active($locked)) {
                    throw new HttpException(409, self::ACTIVE_EXISTS_MESSAGE);
                }

                $quote->refresh();
                $this->quotes->assertSignable($quote, $expectedVersion);

                $signedAt = now();
                $pngPath = $this->storage->put($this->storage->uniquePngPath($locked), $png);
                $written[] = $pngPath;
                $pdf = $this->document->pdfBinary($locked, [
                    'name' => $signerName,
                    'date' => $signedAt->format('F j, Y'),
                    'image' => $this->storage->dataUriForPath($pngPath),
                    'signed' => true,
                ]);
                $pdfPath = $this->storage->put($this->storage->uniquePdfPath($locked), $pdf);
                $written[] = $pdfPath;

                return RoomAreaSignature::query()->create([
                    'room_area_project_id' => $locked->id,
                    'standard_quotation_id' => $quote->id,
                    'quote_version' => (int) ($quote->version ?: 1),
                    'active' => 1,
                    'signer_name' => $signerName,
                    'signed_at' => $signedAt,
                    'admin_user_id' => $admin->id,
                    'ip' => $request->ip(),
                    'user_agent' => $request->userAgent(),
                    'snapshot_hash' => hash('sha256', $pdf),
                    'pdf_path' => $pdfPath,
                    'signature_path' => $pngPath,
                ]);
            });
        } catch (QueryException $e) {
            $this->storage->deleteMany($written);
            if ((int) ($e->errorInfo[1] ?? 0) === 1062) {
                throw new HttpException(409, self::ACTIVE_EXISTS_MESSAGE);
            }

            throw $e;
        } catch (\Throwable $e) {
            $this->storage->deleteMany($written);
            throw $e;
        }
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

        return DB::transaction(function () use ($signature, $admin, $reason) {
            $locked = RoomAreaSignature::query()->whereKey($signature->id)->lockForUpdate()->first();
            if (! $locked || ! $locked->isActive()) {
                throw new HttpException(409, self::INACTIVE_MESSAGE);
            }
            $locked->voided_at = now();
            $locked->void_reason = $reason;
            $locked->voided_by = $admin->id;
            $locked->active = null;
            $locked->save();

            return $locked->fresh();
        });
    }

    public function frozenPdf(RoomAreaSignature $signature): ?string
    {
        return $this->storage->get($signature->pdf_path);
    }
}

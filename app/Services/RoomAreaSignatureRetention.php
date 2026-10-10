<?php

namespace App\Services;

use App\Models\RoomAreaSignature;
use Illuminate\Database\QueryException;
use Symfony\Component\HttpKernel\Exception\HttpException;

class RoomAreaSignatureRetention
{
    public const QUOTE_DELETE_MESSAGE = 'This quote has a signature record and can\'t be deleted; archive it instead';

    public const USER_DELETE_MESSAGE = 'This admin has a signature record and can\'t be deleted; archive the account instead.';

    public const STALE_SIGNATURE_MESSAGE = 'This signature is for an older quote version. Void it before signing the current published version.';

    public function quoteHasSignatures(int $quoteId): bool
    {
        return RoomAreaSignature::query()->where('standard_quotation_id', $quoteId)->exists();
    }

    public function userHasSignatures(int $userId): bool
    {
        return RoomAreaSignature::query()->where('admin_user_id', $userId)->exists();
    }

    public function assertQuoteDeletable(int $quoteId): void
    {
        if ($this->quoteHasSignatures($quoteId)) {
            throw new HttpException(409, self::QUOTE_DELETE_MESSAGE);
        }
    }

    public function assertUserDeletable(int $userId): void
    {
        if ($this->userHasSignatures($userId)) {
            throw new HttpException(409, self::USER_DELETE_MESSAGE);
        }
    }

    public function messageFromQueryException(QueryException $e): ?string
    {
        if ((int) ($e->errorInfo[1] ?? 0) !== 1451) {
            return null;
        }
        $sql = $e->getMessage();
        if (! str_contains($sql, 'room_area_signatures')) {
            return null;
        }
        if (str_contains($sql, 'admin_user_id') || str_contains($sql, '`users`')) {
            return self::USER_DELETE_MESSAGE;
        }

        return self::QUOTE_DELETE_MESSAGE;
    }
}

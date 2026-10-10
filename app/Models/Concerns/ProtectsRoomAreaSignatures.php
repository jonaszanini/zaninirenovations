<?php

namespace App\Models\Concerns;

use App\Models\StandardQuotation;
use App\Models\User;
use App\Services\RoomAreaSignatureRetention;

trait ProtectsRoomAreaSignatures
{
    public static function bootProtectsRoomAreaSignatures(): void
    {
        static::deleting(function ($model) {
            $guard = app(RoomAreaSignatureRetention::class);
            if ($model instanceof StandardQuotation) {
                $guard->assertQuoteDeletable((int) $model->id);
            }
            if ($model instanceof User) {
                $guard->assertUserDeletable((int) $model->id);
            }
        });
    }
}

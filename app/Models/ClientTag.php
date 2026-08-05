<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * Free-form segmentation label for the client directory (e.g. "VIP",
 * "Repeat customer", "Referral source"). Many-to-many with Client.
 */
class ClientTag extends Model
{
    protected $fillable = [
        'name',
        'color',
    ];

    public function clients(): BelongsToMany
    {
        return $this->belongsToMany(Client::class);
    }
}

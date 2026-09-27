<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FlooringSettings extends Model
{
    protected $fillable = [
        'remove_carpet_dispose_rate',
        'rescrew_subfloor_rate',
        'install_shoe_moulding_rate',
        'caulk_paint_shoe_moulding_rate',
        'closet_door_reinstall_rate',
    ];

    protected $casts = [
        'remove_carpet_dispose_rate' => 'decimal:4',
        'rescrew_subfloor_rate' => 'decimal:2',
        'install_shoe_moulding_rate' => 'decimal:4',
        'caulk_paint_shoe_moulding_rate' => 'decimal:4',
        'closet_door_reinstall_rate' => 'decimal:2',
    ];

    public static function current(): self
    {
        $row = static::query()->find(1);
        if ($row) {
            return $row;
        }

        return static::query()->create([
            'id' => 1,
            'remove_carpet_dispose_rate' => 1.50,
            'rescrew_subfloor_rate' => 350.00,
            'install_shoe_moulding_rate' => 3.50,
            'caulk_paint_shoe_moulding_rate' => 2.25,
            'closet_door_reinstall_rate' => 75.00,
        ]);
    }

    public function removeCarpetRate(): float
    {
        return \App\Services\SharedWorkCatalog::flatRate('remove_floor', (float) $this->remove_carpet_dispose_rate);
    }

    public function shoeInstallRate(): float
    {
        return \App\Services\SharedWorkCatalog::flatRate('shoe_install', (float) $this->install_shoe_moulding_rate);
    }

    public function shoeCaulkPaintRate(): float
    {
        return \App\Services\SharedWorkCatalog::flatRate('shoe_caulk_paint', (float) $this->caulk_paint_shoe_moulding_rate);
    }

    public function baseboardInstallRate(): float
    {
        return \App\Services\SharedWorkCatalog::sellRate('baseboard', 6.35);
    }

    public function baseboardCaulkPaintRate(): float
    {
        return \App\Services\SharedWorkCatalog::sellRate('baseboard_caulk_paint', 2.25);
    }
}

<?php

namespace App\Console\Commands;

use App\Models\Material;
use App\Models\MaterialVendor;
use Illuminate\Console\Command;

class AssignHomeDepotMaterialVendors extends Command
{
    protected $signature = 'materials:assign-home-depot-vendor
                            {--dry-run : Show how many rows would be updated without saving}';

    protected $description = 'One-time helper: assign every material to the Home Depot vendor row';

    public function handle(): int
    {
        $vendorId = MaterialVendor::homeDepotId();
        if ($vendorId === null) {
            $this->error('Home Depot vendor not found. Run migrations first.');

            return self::FAILURE;
        }

        $query = Material::query()->where(function ($q) use ($vendorId) {
            $q->whereNull('vendor_id')->orWhere('vendor_id', '!=', $vendorId);
        });

        $count = (clone $query)->count();

        if ($count === 0) {
            $this->info('All materials already use the Home Depot vendor.');

            return self::SUCCESS;
        }

        if ($this->option('dry-run')) {
            $this->info("Would assign Home Depot vendor to {$count} material(s).");

            return self::SUCCESS;
        }

        $updated = $query->update(['vendor_id' => $vendorId]);
        $this->info("Assigned Home Depot vendor to {$updated} material(s).");

        return self::SUCCESS;
    }
}

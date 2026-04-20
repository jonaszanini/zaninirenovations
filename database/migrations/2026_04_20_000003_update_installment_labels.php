<?php

use App\Http\Controllers\Admin\ClientProjectFinanceController;
use App\Models\ClientProject;
use App\Models\ClientProjectInstallment;
use Illuminate\Database\Migrations\Migration;

/**
 * Rewrite every existing installment's label so historical rows reflect
 * the new plan-aware names ("On signing (holds the schedule)",
 * "Day before work begins", "Mid-project", "On completion", etc.).
 *
 * The controller already generates these labels on every save, so this
 * migration only exists to retro-fit projects that have not been
 * re-saved since the new names were introduced.
 */
return new class extends Migration {
    public function up(): void
    {
        ClientProject::query()
            ->with(['installments' => function ($q) {
                $q->orderBy('sort_order');
            }])
            ->chunk(100, function ($projects) {
                foreach ($projects as $project) {
                    $plan = (int) ($project->payment_plan ?? $project->installments->count() ?: 1);

                    foreach ($project->installments as $idx => $installment) {
                        $installment->label = ClientProjectFinanceController::installmentLabel($plan, $idx);
                        $installment->save();
                    }
                }
            });
    }

    public function down(): void
    {
        // Revert to the previous generic labels so a rollback is predictable.
        ClientProject::query()
            ->with(['installments' => function ($q) {
                $q->orderBy('sort_order');
            }])
            ->chunk(100, function ($projects) {
                foreach ($projects as $project) {
                    $plan = (int) ($project->payment_plan ?? $project->installments->count() ?: 1);
                    $ordinals = ['1st', '2nd', '3rd', '4th'];

                    foreach ($project->installments as $idx => $installment) {
                        if ($plan === 1) {
                            $installment->label = 'Full payment';
                        } else {
                            $installment->label = ($ordinals[$idx] ?? ($idx + 1).'th').' installment';
                        }
                        $installment->save();
                    }
                }
            });
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('client_project_expenses', function (Blueprint $table) {
            $table->unsignedInteger('expense_ref')->default(0)->after('client_project_id');
        });

        $projectIds = DB::table('client_project_expenses')
            ->distinct()
            ->orderBy('client_project_id')
            ->pluck('client_project_id');

        foreach ($projectIds as $projectId) {
            $rows = DB::table('client_project_expenses')
                ->where('client_project_id', $projectId)
                ->orderBy('occurred_on')
                ->orderBy('id')
                ->get(['id', 'receipt_path']);

            $ref = 1;
            foreach ($rows as $row) {
                $newRef = $ref++;
                $updates = ['expense_ref' => $newRef];
                $path = $row->receipt_path;

                if ($path !== null && $path !== '') {
                    $rel = ltrim(str_replace('\\', '/', (string) $path), '/');
                    $dir = dirname($rel);
                    $targetRel = $dir.'/Receipt-'.$newRef.'.webp';

                    if ($rel !== $targetRel && Storage::disk('public')->exists($rel)) {
                        if (Storage::disk('public')->exists($targetRel)) {
                            Storage::disk('public')->delete($targetRel);
                        }
                        Storage::disk('public')->move($rel, $targetRel);
                        $updates['receipt_path'] = $targetRel;

                        $mirrorOld = public_path('storage/'.$rel);
                        if (is_file($mirrorOld)) {
                            @unlink($mirrorOld);
                        }
                        $mirrorNew = public_path('storage/'.$targetRel);
                        $mirrorDir = dirname($mirrorNew);
                        if (! is_dir($mirrorDir)) {
                            @mkdir($mirrorDir, 0775, true);
                        }
                        $src = Storage::disk('public')->path($targetRel);
                        if (is_file($src)) {
                            @copy($src, $mirrorNew);
                        }
                    }
                }

                DB::table('client_project_expenses')->where('id', $row->id)->update($updates);
            }
        }
    }

    public function down(): void
    {
        Schema::table('client_project_expenses', function (Blueprint $table) {
            $table->dropColumn('expense_ref');
        });
    }
};

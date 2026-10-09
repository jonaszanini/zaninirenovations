<?php

use App\Models\SharedWork;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('shared_works')) {
            return;
        }

        if (! Schema::hasColumn('shared_works', 'label_normalized')) {
            Schema::table('shared_works', function (Blueprint $table) {
                $table->string('label_normalized', 160)->nullable()->after('label');
            });
        }

        SharedWork::query()->orderBy('id')->each(function (SharedWork $work) {
            $work->label_normalized = SharedWork::normalizeLabel((string) $work->label);
            $work->saveQuietly();
        });

        $this->suffixNearDuplicateLabels();

        if (! $this->indexExists('shared_works', 'shared_works_group_label_unique')) {
            Schema::table('shared_works', function (Blueprint $table) {
                $table->unique(['group_key', 'label_normalized'], 'shared_works_group_label_unique');
            });
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('shared_works')) {
            return;
        }

        if ($this->indexExists('shared_works', 'shared_works_group_label_unique')) {
            Schema::table('shared_works', function (Blueprint $table) {
                $table->dropUnique('shared_works_group_label_unique');
            });
        }

        if (Schema::hasColumn('shared_works', 'label_normalized')) {
            Schema::table('shared_works', function (Blueprint $table) {
                $table->dropColumn('label_normalized');
            });
        }
    }

    /**
     * Make (group_key, label_normalized) unique before the index is added so a
     * near-duplicate never leaves this migration half-applied.
     */
    private function suffixNearDuplicateLabels(): void
    {
        $seen = [];
        SharedWork::query()->orderBy('id')->each(function (SharedWork $work) use (&$seen) {
            $norm = SharedWork::normalizeLabel((string) $work->label);
            $bucket = $work->group_key.'|'.$norm;
            if (! isset($seen[$bucket])) {
                $seen[$bucket] = 1;

                return;
            }

            $candidate = SharedWork::uniquifyDisplayLabel((string) $work->label, function (string $newNorm) use ($work, &$seen) {
                $newBucket = $work->group_key.'|'.$newNorm;

                return isset($seen[$newBucket]) || SharedWork::query()
                    ->where('group_key', $work->group_key)
                    ->where('id', '!=', $work->id)
                    ->where('label_normalized', $newNorm)
                    ->exists();
            });
            $newNorm = SharedWork::normalizeLabel($candidate);
            $newBucket = $work->group_key.'|'.$newNorm;

            $work->label = $candidate;
            $work->label_normalized = $newNorm;
            $work->saveQuietly();
            $seen[$newBucket] = 1;
            $seen[$bucket]++;
        });
    }

    private function indexExists(string $table, string $index): bool
    {
        $rows = DB::select('SHOW INDEX FROM `'.$table.'` WHERE Key_name = ?', [$index]);

        return $rows !== [];
    }
};

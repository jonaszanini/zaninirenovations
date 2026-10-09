<?php

use App\Models\SharedWork;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const BACKUP_TABLE = 'shared_works_label_suffix_backup';

    private const COLLATION = 'utf8mb4_unicode_ci';

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

        $this->ensureBackupTable();

        SharedWork::query()->orderBy('id')->each(function (SharedWork $work) {
            $work->label_normalized = SharedWork::normalizeLabel((string) $work->label);
            $work->saveQuietly();
        });

        $duplicates = $this->nearDuplicateRows();
        foreach ($duplicates as $row) {
            $work = SharedWork::query()->find((int) $row->id);
            if (! $work) {
                continue;
            }
            $original = (string) $work->label;
            $candidate = SharedWork::uniquifyDisplayLabel($original, function (string $newNorm) use ($work) {
                return $this->normalizedNameTaken((string) $work->group_key, $newNorm, (int) $work->id);
            });
            $this->rememberOriginal((int) $work->id, $original);
            $work->label = $candidate;
            $work->label_normalized = SharedWork::normalizeLabel($candidate);
            $work->saveQuietly();
        }

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

        if (Schema::hasTable(self::BACKUP_TABLE)) {
            foreach (DB::table(self::BACKUP_TABLE)->get() as $row) {
                SharedWork::query()->where('id', $row->shared_work_id)->update([
                    'label' => $row->original_label,
                ]);
            }
            Schema::dropIfExists(self::BACKUP_TABLE);
        }

        if (Schema::hasColumn('shared_works', 'label_normalized')) {
            Schema::table('shared_works', function (Blueprint $table) {
                $table->dropColumn('label_normalized');
            });
        }
    }

    /**
     * Find near-duplicates with the same collation as the unique index
     * before any labels are rewritten.
     *
     * @return list<object{id: int|string}>
     */
    private function nearDuplicateRows(): array
    {
        $collation = self::COLLATION;

        return DB::select("
            SELECT sw.id
            FROM shared_works sw
            INNER JOIN (
                SELECT group_key, MIN(id) AS keep_id
                FROM shared_works
                GROUP BY group_key, label_normalized COLLATE {$collation}
                HAVING COUNT(*) > 1
            ) d ON d.group_key = sw.group_key AND sw.id <> d.keep_id
            INNER JOIN shared_works keeper ON keeper.id = d.keep_id
            WHERE sw.label_normalized COLLATE {$collation}
                = keeper.label_normalized COLLATE {$collation}
            ORDER BY sw.id
        ");
    }

    private function normalizedNameTaken(string $groupKey, string $norm, int $exceptId): bool
    {
        return SharedWork::query()
            ->where('group_key', $groupKey)
            ->where('id', '!=', $exceptId)
            ->whereRaw('label_normalized COLLATE '.self::COLLATION.' = ?', [$norm])
            ->exists();
    }

    private function ensureBackupTable(): void
    {
        if (Schema::hasTable(self::BACKUP_TABLE)) {
            return;
        }
        Schema::create(self::BACKUP_TABLE, function (Blueprint $table) {
            $table->unsignedBigInteger('shared_work_id')->primary();
            $table->string('original_label', 160);
        });
    }

    private function rememberOriginal(int $id, string $original): void
    {
        if (DB::table(self::BACKUP_TABLE)->where('shared_work_id', $id)->exists()) {
            return;
        }
        DB::table(self::BACKUP_TABLE)->insert([
            'shared_work_id' => $id,
            'original_label' => $original,
        ]);
    }

    private function indexExists(string $table, string $index): bool
    {
        $rows = DB::select('SHOW INDEX FROM `'.$table.'` WHERE Key_name = ?', [$index]);

        return $rows !== [];
    }
};

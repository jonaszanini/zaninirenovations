<?php

use App\Models\SharedWork;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('shared_works')) {
            return;
        }

        Schema::table('shared_works', function (Blueprint $table) {
            if (! Schema::hasColumn('shared_works', 'label_normalized')) {
                $table->string('label_normalized', 160)->nullable()->after('label');
            }
        });

        SharedWork::query()->orderBy('id')->each(function (SharedWork $work) {
            $work->label_normalized = SharedWork::normalizeLabel((string) $work->label);
            $work->saveQuietly();
        });

        Schema::table('shared_works', function (Blueprint $table) {
            $table->unique(['group_key', 'label_normalized'], 'shared_works_group_label_unique');
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('shared_works')) {
            return;
        }

        Schema::table('shared_works', function (Blueprint $table) {
            $table->dropUnique('shared_works_group_label_unique');
            if (Schema::hasColumn('shared_works', 'label_normalized')) {
                $table->dropColumn('label_normalized');
            }
        });
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\QueryException;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (! Schema::hasTable('standard_quotations')) {
            return;
        }

        // Each column is added independently and idempotently. We guard with
        // hasColumn AND swallow the MySQL "duplicate column" (1060) error so a
        // re-run is always safe, even when schema introspection disagrees with
        // the live table (e.g. a previously named migration already added the
        // columns on production).
        $this->addColumnIfMissing('document_type', function (Blueprint $table) {
            $table->string('document_type', 20)->default('quotation')->after('client_project_id');
        });
        $this->addColumnIfMissing('includes_material', function (Blueprint $table) {
            $table->boolean('includes_material')->default(true)->after('work_description');
        });
        $this->addColumnIfMissing('includes_labour', function (Blueprint $table) {
            $table->boolean('includes_labour')->default(true)->after('includes_material');
        });
    }

    private function addColumnIfMissing(string $column, callable $definition): void
    {
        if (Schema::hasColumn('standard_quotations', $column)) {
            return;
        }

        try {
            Schema::table('standard_quotations', $definition);
        } catch (QueryException $e) {
            // 1060 = duplicate column name. The column already exists, so this
            // migration is effectively a no-op; anything else is a real error.
            if (($e->errorInfo[1] ?? null) !== 1060) {
                throw $e;
            }
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('standard_quotations')) {
            return;
        }

        $columns = array_values(array_filter(
            ['document_type', 'includes_material', 'includes_labour'],
            fn (string $column) => Schema::hasColumn('standard_quotations', $column)
        ));

        if ($columns !== []) {
            Schema::table('standard_quotations', function (Blueprint $table) use ($columns) {
                $table->dropColumn($columns);
            });
        }
    }
};

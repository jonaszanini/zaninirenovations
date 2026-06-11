<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (! Schema::hasTable('standard_quotations')) {
            return;
        }

        Schema::table('standard_quotations', function (Blueprint $table) {
            if (! Schema::hasColumn('standard_quotations', 'document_type')) {
                $table->string('document_type', 20)->default('quotation')->after('client_project_id');
            }
            if (! Schema::hasColumn('standard_quotations', 'includes_material')) {
                $table->boolean('includes_material')->default(true)->after('work_description');
            }
            if (! Schema::hasColumn('standard_quotations', 'includes_labour')) {
                $table->boolean('includes_labour')->default(true)->after('includes_material');
            }
        });
    }

    public function down(): void
    {
        Schema::table('standard_quotations', function (Blueprint $table) {
            $table->dropColumn(['document_type', 'includes_material', 'includes_labour']);
        });
    }
};

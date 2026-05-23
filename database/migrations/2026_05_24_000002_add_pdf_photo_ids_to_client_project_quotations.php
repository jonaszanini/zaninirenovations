<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('client_project_quotations', function (Blueprint $table) {
            $table->json('pdf_photo_ids')->nullable()->after('work_description');
        });
    }

    public function down(): void
    {
        Schema::table('client_project_quotations', function (Blueprint $table) {
            $table->dropColumn('pdf_photo_ids');
        });
    }
};

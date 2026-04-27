<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Adds the optional "Description of the work" textarea to standard
 * quotations. Free-form notes about the service to be performed; when
 * the standard quotation is linked to a project, the description is
 * prepended to the line item that gets pushed onto the project's
 * quotation so the client sees it on the PDF.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::table('standard_quotations', function (Blueprint $table) {
            $table->text('work_description')->nullable()->after('client_name');
        });
    }

    public function down(): void
    {
        Schema::table('standard_quotations', function (Blueprint $table) {
            $table->dropColumn('work_description');
        });
    }
};

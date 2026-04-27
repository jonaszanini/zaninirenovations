<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Adds the optional "Description of the work" textarea to a project's
 * quotation. Free-form notes the client sees on the quotation PDF /
 * preview, rendered as its own block above the line items table.
 *
 * Mirrors the same field on standard_quotations: when a Standard
 * Quotation is linked to a project, its work_description is copied
 * into this column so the description shows up in the PDF as a proper
 * block (rather than being squeezed into a line item description).
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::table('client_project_quotations', function (Blueprint $table) {
            $table->text('work_description')->nullable()->after('items');
        });
    }

    public function down(): void
    {
        Schema::table('client_project_quotations', function (Blueprint $table) {
            $table->dropColumn('work_description');
        });
    }
};

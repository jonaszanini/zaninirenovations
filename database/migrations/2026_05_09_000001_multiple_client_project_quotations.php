<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('client_project_quotations', function (Blueprint $table) {
            $table->string('title', 160)->nullable()->after('client_project_id');
            $table->string('status', 24)->default('draft')->after('title');
            $table->decimal('total_amount', 12, 2)->nullable()->after('status');
            $table->string('source_type', 32)->nullable()->after('total_amount');
            $table->unsignedBigInteger('source_id')->nullable()->after('source_type');
            $table->json('source_snapshot')->nullable()->after('source_id');
            $table->index(['source_type', 'source_id']);
        });

        // InnoDB: add non-unique composite index so FK on client_project_id keeps working
        // after we drop UNIQUE(client_project_id).
        Schema::table('client_project_quotations', function (Blueprint $table) {
            $table->index(['client_project_id', 'status']);
        });

        Schema::table('client_project_quotations', function (Blueprint $table) {
            $table->dropUnique(['client_project_id']);
        });

        // Legacy rows: treat as accepted so financials behaviour matches pre-migration.
        DB::table('client_project_quotations')->update(['status' => 'accepted']);

        DB::table('client_project_quotations')
            ->whereNull('title')
            ->update(['title' => 'Quotation']);

        foreach (DB::table('client_project_quotations')->orderBy('id')->get() as $row) {
            $items = json_decode($row->items ?? '[]', true);
            $sub = 0.0;
            $hst = 0.0;
            if (is_array($items)) {
                foreach ($items as $item) {
                    if (! is_array($item)) {
                        continue;
                    }
                    $amt = round((float) ($item['amount'] ?? 0), 2);
                    $sub += $amt;
                    $apply = ! array_key_exists('apply_hst', $item)
                        || filter_var($item['apply_hst'], FILTER_VALIDATE_BOOLEAN);
                    if ($apply) {
                        $hst += round($amt * 0.13, 2);
                    }
                }
            }
            $grand = round($sub + $hst, 2);
            DB::table('client_project_quotations')
                ->where('id', $row->id)
                ->update(['total_amount' => $grand]);
        }

        Schema::table('client_project_invoices', function (Blueprint $table) {
            $table->json('included_project_quotation_ids')->nullable()->after('items');
            $table->json('included_quotations_snapshot')->nullable()->after('included_project_quotation_ids');
        });
    }

    public function down(): void
    {
        Schema::table('client_project_invoices', function (Blueprint $table) {
            $table->dropColumn(['included_project_quotation_ids', 'included_quotations_snapshot']);
        });

        Schema::table('client_project_quotations', function (Blueprint $table) {
            $table->dropIndex(['source_type', 'source_id']);
            $table->dropIndex(['client_project_id', 'status']);
        });

        Schema::table('client_project_quotations', function (Blueprint $table) {
            $table->dropColumn([
                'title',
                'status',
                'total_amount',
                'source_type',
                'source_id',
                'source_snapshot',
            ]);
        });

        Schema::table('client_project_quotations', function (Blueprint $table) {
            $table->unique('client_project_id');
        });
    }
};

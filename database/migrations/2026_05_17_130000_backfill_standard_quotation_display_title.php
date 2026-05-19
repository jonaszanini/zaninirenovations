<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Required titles: pre-fill legacy rows that have no display_title yet.
 */
return new class extends Migration
{
    public function up(): void
    {
        $rows = DB::table('standard_quotations')
            ->where(function ($q) {
                $q->whereNull('display_title')->orWhere('display_title', '');
            })
            ->get(['id', 'client_name', 'work_description', 'quote_number']);

        foreach ($rows as $row) {
            $t = trim((string) ($row->work_description ?? ''));
            if ($t === '') {
                $t = trim((string) ($row->client_name ?? ''));
            }
            if ($t === '') {
                $t = 'Quotation '.($row->quote_number ?? '');
            }
            DB::table('standard_quotations')->where('id', $row->id)->update([
                'display_title' => Str::limit($t, 255, ''),
            ]);
        }
    }

    public function down(): void
    {
        //
    }
};

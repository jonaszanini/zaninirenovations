<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('company_settings', function (Blueprint $table) {
            $table->string('contact_phone', 40)->nullable()->after('gas_price_per_liter');
            $table->string('contact_phone_tel', 40)->nullable()->after('contact_phone');
            $table->string('contact_email', 160)->nullable()->after('contact_phone_tel');
        });

        if (Schema::hasTable('company_settings')) {
            DB::table('company_settings')->where('id', 1)->update([
                'contact_phone' => DB::raw("COALESCE(NULLIF(contact_phone, ''), '(647) 834-7570')"),
                'contact_phone_tel' => DB::raw("COALESCE(NULLIF(contact_phone_tel, ''), '+16478347570')"),
                'contact_email' => DB::raw("COALESCE(NULLIF(contact_email, ''), 'zaninirenovations@gmail.com')"),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        Schema::table('company_settings', function (Blueprint $table) {
            $table->dropColumn(['contact_phone', 'contact_phone_tel', 'contact_email']);
        });
    }
};

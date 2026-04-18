<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('client_project_time_entries', function (Blueprint $table) {
            // Start / end can both be null while the entry is being edited:
            //   - start_time only       = "in progress" entry (no hours yet)
            //   - start_time + end_time = closed entry, hours computed automatically
            //   - hours alone           = legacy / manually-entered duration
            $table->string('start_time', 5)->nullable()->after('occurred_on');
            $table->string('end_time', 5)->nullable()->after('start_time');
        });

        // Hours becomes nullable so an open entry (start only) can be saved.
        // Using raw SQL because the project doesn't pull in doctrine/dbal.
        DB::statement('ALTER TABLE client_project_time_entries MODIFY hours DECIMAL(6,2) NULL');
    }

    public function down(): void
    {
        Schema::table('client_project_time_entries', function (Blueprint $table) {
            $table->dropColumn(['start_time', 'end_time']);
        });

        DB::statement('ALTER TABLE client_project_time_entries MODIFY hours DECIMAL(6,2) NOT NULL');
    }
};

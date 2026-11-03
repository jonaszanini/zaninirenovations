<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (! Schema::hasTable('jobs')) {
            Schema::create('jobs', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->string('queue')->index();
                $table->longText('payload');
                $table->unsignedTinyInteger('attempts');
                $table->unsignedInteger('reserved_at')->nullable();
                $table->unsignedInteger('available_at');
                $table->unsignedInteger('created_at');
            });
        }

        if (! Schema::hasTable('failed_jobs')) {
            Schema::create('failed_jobs', function (Blueprint $table) {
                $table->id();
                $table->string('uuid')->unique();
                $table->text('connection');
                $table->text('queue');
                $table->longText('payload');
                $table->longText('exception');
                $table->timestamp('failed_at')->useCurrent();
            });
        }

        if (Schema::hasTable('quotation_send_audits')) {
            try {
                Schema::table('quotation_send_audits', function (Blueprint $table) {
                    $table->dropUnique('quotation_send_audits_idempotency_key_unique');
                });
            } catch (\Throwable $e) {
                // Index may already be dropped
            }

            $driver = Schema::getConnection()->getDriverName();
            if ($driver === 'mysql' || $driver === 'mariadb') {
                DB::statement('ALTER TABLE `quotation_send_audits` MODIFY `idempotency_key` VARCHAR(128) NULL');
                if (! Schema::hasColumn('quotation_send_audits', 'active_idempotency_key')) {
                    DB::statement("ALTER TABLE `quotation_send_audits` ADD COLUMN `active_idempotency_key` VARCHAR(128) GENERATED ALWAYS AS (CASE WHEN `status` IN ('sending', 'sent') THEN `idempotency_key` ELSE NULL END) STORED");
                }
                DB::statement('CREATE UNIQUE INDEX `qsa_quote_idem_unique` ON `quotation_send_audits` (`client_project_quotation_id`, `active_idempotency_key`)');
                DB::statement('CREATE INDEX `qsa_quote_idem_status_idx` ON `quotation_send_audits` (`client_project_quotation_id`, `idempotency_key`, `status`)');

                DB::unprepared('DROP TRIGGER IF EXISTS `qsa_immutable_before_update`');
                DB::unprepared('DROP TRIGGER IF EXISTS `qsa_immutable_before_delete`');

                DB::unprepared(<<<'SQL'
CREATE TRIGGER `qsa_immutable_before_update`
BEFORE UPDATE ON `quotation_send_audits`
FOR EACH ROW
BEGIN
    IF OLD.status <> 'sending' OR NEW.status NOT IN ('sent', 'failed') THEN
        SIGNAL SQLSTATE '45000'
            SET MESSAGE_TEXT = 'QuotationSendAudit records are immutable and cannot be updated.';
    END IF;
END
SQL);

                DB::unprepared(<<<'SQL'
CREATE TRIGGER `qsa_immutable_before_delete`
BEFORE DELETE ON `quotation_send_audits`
FOR EACH ROW
BEGIN
    SIGNAL SQLSTATE '45000'
        SET MESSAGE_TEXT = 'QuotationSendAudit records are immutable and cannot be deleted.';
END
SQL);
            } else {
                Schema::table('quotation_send_audits', function (Blueprint $table) {
                    $table->index(['client_project_quotation_id', 'idempotency_key', 'status'], 'qsa_quote_idem_status_idx');
                });
            }
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('quotation_send_audits')) {
            $driver = Schema::getConnection()->getDriverName();
            if ($driver === 'mysql' || $driver === 'mariadb') {
                DB::unprepared('DROP TRIGGER IF EXISTS `qsa_immutable_before_update`');
                DB::unprepared('DROP TRIGGER IF EXISTS `qsa_immutable_before_delete`');
            }

            try {
                Schema::table('quotation_send_audits', function (Blueprint $table) {
                    $table->dropUnique('qsa_quote_idem_unique');
                });
            } catch (\Throwable $e) {
            }

            try {
                Schema::table('quotation_send_audits', function (Blueprint $table) {
                    $table->dropIndex('qsa_quote_idem_status_idx');
                });
            } catch (\Throwable $e) {
            }

            if ($driver === 'mysql' || $driver === 'mariadb') {
                if (Schema::hasColumn('quotation_send_audits', 'active_idempotency_key')) {
                    DB::statement('ALTER TABLE `quotation_send_audits` DROP COLUMN `active_idempotency_key`');
                }
                DB::statement('ALTER TABLE `quotation_send_audits` MODIFY `idempotency_key` VARCHAR(64) NULL');
            }

            try {
                Schema::table('quotation_send_audits', function (Blueprint $table) {
                    $table->unique('idempotency_key', 'quotation_send_audits_idempotency_key_unique');
                });
            } catch (\Throwable $e) {
            }
        }

        Schema::dropIfExists('failed_jobs');
        Schema::dropIfExists('jobs');
    }
};

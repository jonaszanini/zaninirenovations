<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * client_communication_logs — audit trail of every email + SMS the
 * application sends, displayed on the admin "Email / SMS log" page.
 *
 * Each row is appended at the moment of sending (success OR failure)
 * so the admin can answer "did the client get the receipt?" or
 * "when was the contract emailed and to which address?".
 *
 * Design notes:
 *   - client_project_id is nullable + ON DELETE SET NULL so deleting a
 *     project never erases its communication history. The denormalised
 *     {project_name, client_name, recipient} snapshots survive the FK
 *     being null'd out.
 *   - 'channel' is email|sms. 'direction' separates client-bound from
 *     internal admin-bound messages (calendar event, daily reminders).
 *   - 'reason' is a stable machine code (e.g. 'invoice_sent') with a
 *     friendly label resolved at view time.
 *   - 'description' is free-form and includes the dollar amounts /
 *     dates / invoice numbers that contextualise the message.
 *   - 'subject' is the email subject (NULL for SMS).
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::create('client_communication_logs', function (Blueprint $table) {
            $table->id();

            $table->foreignId('client_project_id')
                ->nullable()
                ->constrained('client_projects')
                ->nullOnDelete();

            // Snapshots so the log row survives even if the project /
            // client is renamed or deleted later.
            $table->string('project_name_snapshot', 255)->nullable();
            $table->string('client_name_snapshot', 255)->nullable();

            $table->string('channel', 16);          // 'email' | 'sms'
            $table->string('direction', 16);        // 'to_client' | 'to_admin'
            $table->string('reason', 64);           // 'invoice_sent', 'contract_sent', etc.

            $table->string('recipient', 255);       // email address OR phone digits
            $table->string('cc', 255)->nullable();  // secondary email (cc) when applicable
            $table->string('subject', 500)->nullable();
            $table->text('description');            // human summary with amounts / numbers

            $table->string('status', 16);           // 'sent' | 'failed'
            $table->text('error')->nullable();      // exception / API error detail when failed

            $table->timestamp('sent_at')->index();

            $table->timestamps();

            $table->index(['client_project_id', 'sent_at']);
            $table->index(['channel', 'sent_at']);
            $table->index(['direction', 'sent_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('client_communication_logs');
    }
};

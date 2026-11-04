<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Admin control center (briefing nieuwe functies): /admin wordt de plek
 * waar het team de hele zaak bestuurt.
 *
 * - staff_role: Eigenaar, Financiën, Klantenservice, Moderator, Marketing;
 * - ledger_entries: één grootboek; omzet wordt nooit uit losse
 *   bestellingen in het dashboard gerekend;
 * - cost_entries: kosten die niet uit de betaalpartij komen;
 * - metric_snapshots: de cijfers per dag, elke nacht gevuld;
 * - tickets: het contactformulier wordt klantenservice met type, prioriteit,
 *   toewijzing, reactietijd en berichten;
 * - customer_notes en customer_tasks: de klantkaart;
 * - report_schedules: vaste rapporten per mail;
 * - admin_access_logs: elke export en elke geopende klantkaart.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->string('staff_role', 12)->nullable()->after('is_staff');
        });

        // Wie nu beheerder is, wordt eigenaar: de rol met alle rechten.
        DB::table('users')->where('is_staff', true)->update(['staff_role' => 'owner']);

        Schema::create('ledger_entries', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('order_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('company_id')->nullable()->constrained()->nullOnDelete();
            $table->string('type', 20);
            $table->bigInteger('amount_cents');
            $table->bigInteger('vat_cents')->default(0);
            $table->string('external_id', 120)->nullable();
            $table->string('source_ref', 80)->unique();
            $table->dateTime('occurred_at');
            $table->timestamp('created_at')->nullable();

            $table->index(['type', 'occurred_at']);
            $table->index('occurred_at');
        });

        Schema::create('cost_entries', function (Blueprint $table): void {
            $table->id();
            $table->string('category', 20);
            $table->bigInteger('amount_cents');
            $table->date('period');
            $table->string('source', 20)->default('manual');
            $table->string('note', 300)->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['period', 'category']);
        });

        Schema::create('metric_snapshots', function (Blueprint $table): void {
            $table->id();
            $table->string('metric', 40);
            $table->date('date');
            $table->string('dimension', 60)->default('');
            $table->bigInteger('value');
            $table->timestamp('created_at')->nullable();

            $table->unique(['metric', 'date', 'dimension']);
        });

        Schema::table('support_tickets', function (Blueprint $table): void {
            $table->string('number', 16)->nullable()->unique()->after('id');
            $table->string('type', 12)->default('question')->after('number');
            $table->string('priority', 8)->default('normal')->after('type');
            $table->string('source', 12)->default('form')->after('priority');
            $table->foreignId('lot_id')->nullable()->after('order_number')->constrained()->nullOnDelete();
            $table->foreignId('order_id')->nullable()->after('lot_id')->constrained()->nullOnDelete();
            $table->foreignId('assigned_to')->nullable()->after('order_id')->constrained('users')->nullOnDelete();
            $table->dateTime('first_response_due_at')->nullable()->after('assigned_to');
            $table->dateTime('first_response_at')->nullable()->after('first_response_due_at');
            $table->dateTime('resolved_at')->nullable()->after('first_response_at');
            $table->string('mail_thread', 190)->nullable()->after('resolved_at');
            $table->dateTime('sla_task_at')->nullable()->after('mail_thread');

            $table->index(['status', 'first_response_due_at']);
        });

        Schema::create('ticket_messages', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('ticket_id')->constrained('support_tickets')->cascadeOnDelete();
            $table->string('sender_type', 10);
            $table->foreignId('sender_id')->nullable()->constrained('users')->nullOnDelete();
            $table->text('body');
            $table->boolean('internal')->default(false);
            $table->boolean('draft')->default(false);
            $table->foreignId('mail_delivery_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamp('created_at')->nullable();

            $table->index(['ticket_id', 'created_at']);
        });

        Schema::create('customer_notes', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('company_id')->nullable()->constrained()->cascadeOnDelete();
            $table->text('body');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('created_at')->nullable();

            $table->index('user_id');
            $table->index('company_id');
        });

        Schema::create('customer_tasks', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('company_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('title', 200);
            $table->string('agent', 10)->nullable();
            $table->string('url', 300)->nullable();
            $table->dateTime('due_at')->nullable();
            $table->foreignId('assigned_to')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('done_at')->nullable();
            $table->foreignId('done_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('source_ref', 80)->nullable()->unique();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['done_at', 'due_at']);
        });

        Schema::create('report_schedules', function (Blueprint $table): void {
            $table->id();
            $table->string('report_key', 40);
            $table->json('filters')->nullable();
            $table->string('frequency', 10)->default('weekly');
            $table->json('recipients');
            $table->dateTime('last_sent_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('admin_access_logs', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('staff_id')->constrained('users')->cascadeOnDelete();
            $table->string('subject_type', 60);
            $table->string('subject_id', 60);
            $table->string('reason', 300)->nullable();
            $table->string('ip', 45)->nullable();
            $table->dateTime('opened_at');

            $table->index(['subject_type', 'subject_id']);
            $table->index('opened_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('admin_access_logs');
        Schema::dropIfExists('report_schedules');
        Schema::dropIfExists('customer_tasks');
        Schema::dropIfExists('customer_notes');
        Schema::dropIfExists('ticket_messages');
        Schema::table('support_tickets', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('assigned_to');
            $table->dropConstrainedForeignId('order_id');
            $table->dropConstrainedForeignId('lot_id');
            $table->dropColumn(['number', 'type', 'priority', 'source', 'first_response_due_at', 'first_response_at', 'resolved_at', 'mail_thread', 'sla_task_at']);
        });
        Schema::dropIfExists('metric_snapshots');
        Schema::dropIfExists('cost_entries');
        Schema::dropIfExists('ledger_entries');
        Schema::table('users', function (Blueprint $table): void {
            $table->dropColumn('staff_role');
        });
    }
};

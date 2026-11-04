<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 | Ivy als gesprekspartner (Ivy-briefing hfst. 6): gesprekken, berichten,
 | voorstellen die de klant bevestigt, niveaus per kavel, alerts met
 | keuzes, voorkeuren per melding, kennisbank, leads en overdrachten.
 | Bedragen in centen, ID's als ULID, tijden in UTC.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ivy_conversations', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('guest_token', 64)->nullable()->index();
            $table->string('role', 16);
            $table->foreignId('lot_id')->nullable()->constrained()->nullOnDelete();
            $table->string('page_type', 40)->nullable();
            $table->string('status', 16)->default('open');
            $table->text('summary')->nullable();
            $table->boolean('pitched')->default(false);
            $table->timestamp('handoff_at')->nullable();
            $table->timestamp('last_message_at')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'status']);
        });

        Schema::create('ivy_messages', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('conversation_id')->constrained('ivy_conversations')->cascadeOnDelete();
            $table->string('sender', 16);
            $table->text('body');
            $table->json('cards')->nullable();
            $table->json('tool_calls')->nullable();
            $table->foreignId('ai_run_id')->nullable()->constrained('ai_runs')->nullOnDelete();
            $table->string('prompt_version', 20)->nullable();
            $table->timestamp('created_at');
        });

        Schema::create('ivy_action_proposals', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('conversation_id')->nullable()->constrained('ivy_conversations')->nullOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('type', 24);
            $table->foreignId('lot_id')->nullable()->constrained()->nullOnDelete();
            $table->json('payload')->nullable();
            $table->bigInteger('amount_cents')->nullable();
            $table->bigInteger('expected_current_cents')->nullable();
            $table->timestamp('expected_ends_at')->nullable();
            $table->string('status', 20)->default('proposed');
            $table->timestamp('expires_at')->nullable();
            $table->timestamp('confirmed_at')->nullable();
            $table->timestamp('executed_at')->nullable();
            $table->json('result')->nullable();
            $table->string('idempotency_key', 80)->nullable()->unique();
            $table->string('confirmed_ip', 45)->nullable();
            $table->timestamps();

            $table->index(['user_id', 'status']);
        });

        Schema::create('ivy_lot_modes', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('lot_id')->constrained()->cascadeOnDelete();
            $table->string('mode', 12)->default('notify');
            $table->bigInteger('limit_cents')->nullable();
            $table->string('consent_text_version', 20)->nullable();
            $table->timestamp('consented_at')->nullable();
            $table->timestamp('ended_at')->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'lot_id']);
        });

        Schema::create('ivy_alerts', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('key', 40);
            $table->string('role', 16);
            $table->foreignId('lot_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('order_id')->nullable()->constrained()->nullOnDelete();
            $table->string('dedupe_key', 120)->unique();
            $table->string('level', 12)->default('task');
            $table->json('payload')->nullable();
            $table->json('choices')->nullable();
            $table->timestamp('read_at')->nullable();
            $table->timestamp('acted_at')->nullable();
            $table->string('acted_choice', 40)->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'level', 'acted_at']);
        });

        Schema::create('ivy_alert_deliveries', function (Blueprint $table): void {
            $table->id();
            $table->foreignUlid('alert_id')->constrained('ivy_alerts')->cascadeOnDelete();
            $table->string('channel', 12);
            $table->string('status', 16)->default('sent');
            $table->string('provider_id')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('delivered_at')->nullable();
            $table->timestamp('clicked_at')->nullable();
            $table->string('failed_reason', 200)->nullable();
        });

        Schema::create('notification_preferences', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('alert_key', 40);
            $table->string('channel', 12);
            $table->boolean('enabled')->default(true);
            $table->time('quiet_start')->nullable();
            $table->time('quiet_end')->nullable();

            $table->unique(['user_id', 'alert_key', 'channel']);
        });

        Schema::create('kb_articles', function (Blueprint $table): void {
            $table->id();
            $table->string('slug', 80)->unique();
            $table->string('title', 160);
            $table->string('audience', 16)->default('all');
            $table->text('body');
            $table->string('source_url', 500)->nullable();
            $table->string('status', 16)->default('published');
            $table->unsignedInteger('current_version')->default(1);
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('kb_gaps', function (Blueprint $table): void {
            $table->id();
            $table->string('question_hash', 64)->unique();
            $table->string('sample_question', 300);
            $table->unsignedInteger('count')->default(1);
            $table->string('role', 16);
            $table->timestamp('first_seen_at');
            $table->timestamp('last_seen_at');
            $table->foreignId('resolved_article_id')->nullable()->constrained('kb_articles')->nullOnDelete();
        });

        Schema::create('ivy_leads', function (Blueprint $table): void {
            $table->id();
            $table->foreignUlid('conversation_id')->nullable()->constrained('ivy_conversations')->nullOnDelete();
            $table->string('name', 120);
            $table->string('email', 190);
            $table->string('phone', 40)->nullable();
            $table->string('intent', 40)->nullable();
            $table->string('temperature', 12)->default('warm');
            $table->text('summary')->nullable();
            $table->timestamp('consent_at')->nullable();
            $table->string('consent_version', 20)->nullable();
            $table->foreignId('assigned_to')->nullable()->constrained('users')->nullOnDelete();
            $table->string('status', 16)->default('new');
            $table->timestamps();
        });

        Schema::create('ivy_handoffs', function (Blueprint $table): void {
            $table->id();
            $table->foreignUlid('conversation_id')->constrained('ivy_conversations')->cascadeOnDelete();
            $table->string('reason', 60);
            $table->text('summary');
            $table->string('status', 16)->default('open');
            $table->foreignId('assigned_to')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('created_at');
            $table->timestamp('closed_at')->nullable();
        });

        Schema::create('ivy_guest_uploads', function (Blueprint $table): void {
            $table->id();
            $table->string('guest_token', 64)->index();
            $table->string('path');
            $table->string('mime', 60);
            $table->string('checksum', 64);
            $table->json('analysis')->nullable();
            $table->timestamp('expires_at');
            $table->foreignId('claimed_listing_id')->nullable()->constrained('lots')->nullOnDelete();
            $table->timestamp('created_at');
        });

        Schema::table('ai_runs', function (Blueprint $table): void {
            $table->string('conversation_id', 26)->nullable()->after('cache_key')->index();
            $table->string('ivy_role', 16)->nullable()->after('conversation_id');
        });
    }

    public function down(): void
    {
        Schema::table('ai_runs', fn (Blueprint $table) => $table->dropColumn(['conversation_id', 'ivy_role']));
        Schema::dropIfExists('ivy_guest_uploads');
        Schema::dropIfExists('ivy_handoffs');
        Schema::dropIfExists('ivy_leads');
        Schema::dropIfExists('kb_gaps');
        Schema::dropIfExists('kb_articles');
        Schema::dropIfExists('notification_preferences');
        Schema::dropIfExists('ivy_alert_deliveries');
        Schema::dropIfExists('ivy_alerts');
        Schema::dropIfExists('ivy_lot_modes');
        Schema::dropIfExists('ivy_action_proposals');
        Schema::dropIfExists('ivy_messages');
        Schema::dropIfExists('ivy_conversations');
    }
};

<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Berichten (briefing nieuwe functies, 3 november 2026): één
 * berichtensysteem met twee vormen, de korte vraag bij een onderdeel van
 * de kavel en het doorlopende gesprek. Elk gesprek hoort bij precies één
 * kavel en één koper-verkoper-paar.
 *
 * - conversation_messages: elk bericht met zijn staat (zichtbaar,
 *   gemaskeerd, vastgehouden, geweigerd) en de gemaskeerde tekst;
 * - message_moderations: elke beslissing van de bot, met regelversie,
 *   zekerheid, actie en wie hem eventueel terugdraaide;
 * - conversation_access_logs: elke inzage door een medewerker, met reden;
 * - lot_public_answers: antwoorden die de verkoper bij de kavel zet.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('conversations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('lot_id')->constrained()->cascadeOnDelete();
            $table->foreignId('order_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('buyer_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('seller_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('company_id')->nullable()->constrained()->nullOnDelete();
            $table->string('kind', 12);
            $table->string('status', 12)->default('open');
            $table->string('lot_part', 20)->nullable();
            $table->dateTime('last_message_at')->nullable();
            $table->dateTime('buyer_read_at')->nullable();
            $table->dateTime('seller_read_at')->nullable();
            $table->string('flag', 30)->nullable();
            $table->dateTime('flagged_at')->nullable();
            $table->foreignId('reported_by')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('reported_at')->nullable();
            $table->string('report_note', 500)->nullable();
            $table->foreignId('blocked_by')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('blocked_at')->nullable();
            $table->foreignId('operator_id')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('operator_joined_at')->nullable();
            $table->foreignId('dispute_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamps();

            $table->index(['lot_id', 'buyer_id', 'kind']);
            $table->index(['seller_id', 'last_message_at']);
            $table->index(['buyer_id', 'last_message_at']);
            $table->index(['flag', 'flagged_at']);
        });

        Schema::create('conversation_messages', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('conversation_id')->constrained()->cascadeOnDelete();
            $table->string('sender_type', 10);
            $table->foreignId('sender_id')->nullable()->constrained('users')->nullOnDelete();
            $table->text('body');
            $table->text('body_masked')->nullable();
            $table->string('state', 10)->default('visible');
            $table->string('hint', 30)->nullable();
            $table->boolean('internal')->default(false);
            $table->string('attachment_path')->nullable();
            $table->string('attachment_name')->nullable();
            $table->string('attachment_mime', 100)->nullable();
            $table->uuid('client_key')->nullable()->unique();
            $table->timestamp('created_at')->nullable();

            $table->index(['conversation_id', 'created_at']);
        });

        Schema::create('message_moderations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('message_id')->constrained('conversation_messages')->cascadeOnDelete();
            $table->string('rule_version', 10);
            $table->string('prompt_version', 20)->nullable();
            $table->string('signal', 30);
            $table->decimal('confidence', 3, 2);
            $table->string('action', 10);
            $table->string('explanation', 300)->nullable();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('reviewed_at')->nullable();
            $table->string('review_action', 10)->nullable();
            $table->timestamp('created_at')->nullable();
        });

        Schema::create('conversation_access_logs', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('conversation_id')->constrained()->cascadeOnDelete();
            $table->foreignId('staff_id')->constrained('users')->cascadeOnDelete();
            $table->string('reason', 300)->nullable();
            $table->dateTime('opened_at');
        });

        Schema::create('lot_public_answers', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('lot_id')->constrained()->cascadeOnDelete();
            $table->foreignId('conversation_id')->nullable()->constrained()->nullOnDelete();
            $table->string('lot_part', 20)->nullable();
            $table->text('question');
            $table->text('answer');
            $table->foreignId('published_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index('lot_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lot_public_answers');
        Schema::dropIfExists('conversation_access_logs');
        Schema::dropIfExists('message_moderations');
        Schema::dropIfExists('conversation_messages');
        Schema::dropIfExists('conversations');
    }
};

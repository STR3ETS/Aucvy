<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Reviewmoderatie volgens het Google-principe (besluit Raph, 2 november
 * 2026): een review gaat door een automatische controle vóór publicatie,
 * iedereen kan hem melden, en alleen beheer beslist. De verkoper reageert,
 * maar verwijdert niets.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('reviews', function (Blueprint $table): void {
            // published | held (automatisch tegengehouden) | removed (door beheer)
            $table->string('moderation_status', 16)->default('published')->after('published_at');
            $table->string('moderation_reason', 40)->nullable()->after('moderation_status');
            $table->text('moderation_note')->nullable()->after('moderation_reason');
            $table->foreignId('moderated_by')->nullable()->after('moderation_note')->constrained('users')->nullOnDelete();
            $table->dateTime('moderated_at')->nullable()->after('moderated_by');

            $table->index(['moderation_status', 'id']);
        });

        Schema::create('review_reports', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('review_id')->constrained()->cascadeOnDelete();
            $table->foreignId('reporter_id')->constrained('users')->cascadeOnDelete();
            $table->string('reason', 40);
            $table->text('note')->nullable();
            $table->dateTime('handled_at')->nullable();
            $table->datetimes();

            // Eén melding per persoon per review: herhaald melden telt niet dubbel.
            $table->unique(['review_id', 'reporter_id']);
            $table->index(['handled_at', 'review_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('review_reports');

        Schema::table('reviews', function (Blueprint $table): void {
            $table->dropIndex(['moderation_status', 'id']);
            $table->dropConstrainedForeignId('moderated_by');
            $table->dropColumn(['moderation_status', 'moderation_reason', 'moderation_note', 'moderated_at']);
        });
    }
};

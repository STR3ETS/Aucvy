<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Afleverlog van uitgaande mail (besluit Raph, 2 november 2026: Mailgun).
 * Eén rij per verzonden bericht; de webhook van Mailgun werkt de status
 * bij (afgeleverd, tijdelijk mislukt, gebounced, als spam gemeld).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mail_deliveries', function (Blueprint $table): void {
            $table->id();
            $table->string('message_id')->nullable()->index();
            $table->string('to_email')->index();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('subject', 300)->nullable();
            $table->string('kind', 120)->nullable();
            $table->string('mailer', 30);
            $table->string('status', 20)->default('sent')->index();
            $table->string('failure_reason', 500)->nullable();
            $table->dateTime('last_event_at')->nullable();
            $table->timestamps();

            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mail_deliveries');
    }
};

<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 | Live veilen (briefing nieuwe functies): een uitzending per kavel via een
 | mediaserver (LiveKit). De server blijft de enige waarheid: het bod loopt
 | via PlaceBid en Reverb, nooit via de video.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('live_sessions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('lot_id')->constrained()->cascadeOnDelete();
            $table->foreignId('auction_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('seller_id')->constrained('users')->cascadeOnDelete();
            $table->string('room', 80)->unique();
            $table->string('provider', 20);
            $table->string('status', 16)->default('preparing');
            $table->timestamp('started_at')->nullable();
            $table->timestamp('ended_at')->nullable();
            $table->unsignedInteger('viewers_now')->default(0);
            $table->unsignedInteger('peak_viewers')->default(0);
            $table->unsignedInteger('viewer_minutes')->default(0);
            $table->unsignedInteger('max_viewers')->default(0);
            $table->string('recording_id')->nullable();
            $table->string('end_reason', 60)->nullable();
            $table->timestamps();

            $table->index(['lot_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('live_sessions');
    }
};

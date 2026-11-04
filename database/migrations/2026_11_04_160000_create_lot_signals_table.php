<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 | Interesse-signalen per kavel (hoofdbriefing p. 7): kijkers, weergaven,
 | opgeslagen, gedeeld, documenten geopend. Bieders komen uit de biedingen
 | zelf. Alleen gemeten aantallen; verkoper, team en bots tellen niet mee.
 | De Hot-score van de afgelopen 48 uur staat op de kavel.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('lot_signals', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('lot_id')->constrained()->cascadeOnDelete();
            $table->string('kind', 16);
            $table->string('visitor_hash', 40);
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('source', 32)->nullable();
            $table->string('bucket', 32);
            $table->timestamp('occurred_at');

            $table->unique(['lot_id', 'kind', 'visitor_hash', 'bucket']);
            $table->index(['lot_id', 'kind', 'occurred_at']);
        });

        Schema::table('lots', function (Blueprint $table): void {
            $table->unsignedInteger('interest_score')->default(0)->after('published_at');
            $table->boolean('is_hot')->default(false)->after('interest_score');
            $table->timestamp('hot_since')->nullable()->after('is_hot');
        });
    }

    public function down(): void
    {
        Schema::table('lots', function (Blueprint $table): void {
            $table->dropColumn(['interest_score', 'is_hot', 'hot_since']);
        });

        Schema::dropIfExists('lot_signals');
    }
};

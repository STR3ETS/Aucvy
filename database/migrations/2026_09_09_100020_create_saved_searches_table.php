<?php

declare(strict_types=1);

use App\Enums\NotificationChannel;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /*
     | Dit is Ivy's "ik bewaak de markt voor je". Ook de uitweg uit een lege
     | zoekresultatenpagina: nooit een doodlopende pagina, altijd één concrete
     | verbreding plus de mogelijkheid een alert te zetten - en dat zonder
     | registratiemuur (hfst. 13.2).
     */
    public function up(): void
    {
        Schema::create('saved_searches', function (Blueprint $table) {
            $table->id();

            $table->foreignId('user_id')->constrained()->cascadeOnDelete();

            $table->string('name')->nullable();

            // De filters zoals ze in de URL stonden.
            $table->json('query');
            // Wat Ivy ervan maakte, zodat de interpretatie corrigeerbaar blijft
            // zonder een nieuwe LLM-call.
            $table->json('interpreted')->nullable();

            $table->string('channel', 20)->default(NotificationChannel::Email->value);

            $table->dateTime('last_run_at')->nullable();
            $table->dateTime('paused_at')->nullable();

            $table->datetimes();

            $table->index(['user_id', 'last_run_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('saved_searches');
    }
};

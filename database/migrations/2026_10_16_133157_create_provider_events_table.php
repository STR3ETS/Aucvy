<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
| Elk webhook-event van een externe partij, uniek per provider en event-id,
| zodat een dubbel of verlaat event niets meer doet en een replay mogelijk
| is (briefing sectie 7, flow D).
*/
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('provider_events', function (Blueprint $table): void {
            $table->id();
            $table->string('provider', 32);
            $table->string('event_id', 128);
            $table->string('type', 64)->nullable();
            $table->json('payload')->nullable();
            $table->dateTime('received_at');
            $table->dateTime('processed_at')->nullable();
            $table->string('status', 20)->default('received');
            $table->datetimes();

            $table->unique(['provider', 'event_id']);
            $table->index(['provider', 'received_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('provider_events');
    }
};

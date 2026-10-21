<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
| Bewaarde antwoorden op aanvragen met een Idempotency-Key: publiceren,
| betalen, delen. Biedingen hebben hun eigen sleutel op de bids-tabel.
*/
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('idempotency_keys', function (Blueprint $table): void {
            $table->id();
            $table->string('scope', 64);
            $table->string('key', 80);
            $table->json('response');
            $table->dateTime('expires_at');
            $table->datetimes();

            $table->unique(['scope', 'key']);
            $table->index('expires_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('idempotency_keys');
    }
};

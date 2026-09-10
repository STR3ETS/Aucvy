<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /*
     | Append-only. Dit is de bron voor replay na een netwerkdip én voor het
     | auditlog. Rijen worden nooit gewijzigd of verwijderd - vandaar ook geen
     | updated_at (hfst. 5).
     */
    public function up(): void
    {
        Schema::create('auction_events', function (Blueprint $table) {
            $table->id();

            $table->foreignId('auction_id')->constrained()->cascadeOnDelete();

            $table->unsignedBigInteger('sequence');
            $table->string('type', 32);

            /*
             | Wat er over de lijn gaat. Op het publieke kanaal staat hier nooit
             | een volledige naam, e-mailadres of maximumbod - een maximumbod
             | dat lekt, is een gebruiker die geld verliest (hfst. 7).
             */
            $table->json('payload');

            $table->dateTime('created_at');

            // De client vraagt "alles na 431" en moet een gat kunnen zien.
            $table->unique(['auction_id', 'sequence']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('auction_events');
    }
};

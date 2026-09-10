<?php

declare(strict_types=1);

use App\Enums\MediaType;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('lot_media', function (Blueprint $table) {
            $table->id();

            $table->foreignId('lot_id')->constrained()->cascadeOnDelete();

            $table->string('type', 20)->default(MediaType::Image->value);

            // Origineel privé, varianten publiek (hfst. 3).
            $table->string('path');
            $table->json('variants')->nullable();

            $table->unsignedInteger('width')->nullable();
            $table->unsignedInteger('height')->nullable();
            $table->unsignedSmallInteger('position')->default(0);
            $table->string('alt')->nullable();

            /*
             | Checksum nu al vullen, ook al gebruiken we hem nog niet: dit is
             | later de dubbele-fotodetectie voor fraud detection (hfst. 19).
             | Je hebt hem niet meer als je nu vergeet te loggen.
             */
            $table->string('checksum', 64)->nullable();

            // Originelen in volledige resolutie bewaren, niet alleen varianten -
            // anders kan de video studio later niets (hfst. 19).
            $table->unsignedBigInteger('size_bytes')->nullable();

            $table->datetimes();

            $table->index(['lot_id', 'position']);
            $table->index('checksum');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lot_media');
    }
};

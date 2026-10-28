<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Doorverwijzingen (masterbriefing 7.2): een oud pad dat niet meer bestaat
 * stuurt door naar een nieuw adres in plaats van een 404. Alleen voor paden
 * zonder route; een bestaande route wint altijd.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('redirects', function (Blueprint $table): void {
            $table->id();
            $table->string('from_path')->unique();
            $table->string('to_url', 2000);
            $table->unsignedSmallInteger('status')->default(301);
            $table->unsignedInteger('hits')->default(0);
            $table->dateTime('last_hit_at')->nullable();
            $table->datetimes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('redirects');
    }
};

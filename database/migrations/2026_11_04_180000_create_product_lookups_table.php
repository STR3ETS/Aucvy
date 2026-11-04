<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 | Camera, OCR en productherkenning (briefing nieuwe functies): een eigen
 | cache van EAN-opzoekingen, en op de kavel de GTIN en het serienummer.
 | Het serienummer wordt bewaard maar niet standaard getoond.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_lookups', function (Blueprint $table): void {
            $table->id();
            $table->string('gtin', 14)->unique();
            $table->string('provider', 30);
            $table->string('status', 16);
            $table->json('payload')->nullable();
            $table->timestamp('fetched_at');
        });

        Schema::table('lots', function (Blueprint $table): void {
            $table->string('gtin', 14)->nullable()->after('model');
            $table->string('serial_number', 80)->nullable()->after('gtin');
        });
    }

    public function down(): void
    {
        Schema::table('lots', fn (Blueprint $table) => $table->dropColumn(['gtin', 'serial_number']));
        Schema::dropIfExists('product_lookups');
    }
};

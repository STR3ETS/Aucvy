<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Vooraf modereren (besluit Raph, 2 november 2026): "de bot moet kijken of
 * het kavel aan alle criteria voldoet: prijs, hoeveelheid van een eenheid,
 * garanties; echtheid van documentatie moet worden aangevinkt; dan pas
 * publiceren."
 *
 * - unit: waar de hoeveelheid in geteld wordt (stuk, set, pallet, ...);
 * - warranty / warranty_until: de garantie die de verkoper opgeeft;
 * - documents_declared_at: wanneer de verkoper verklaarde dat de
 *   documenten echt en volledig zijn;
 * - submitted_at / screening: indienen en de uitkomst van de controle.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('lots', function (Blueprint $table): void {
            $table->string('unit', 20)->nullable()->after('quantity');
            $table->string('warranty', 20)->nullable()->after('condition');
            $table->date('warranty_until')->nullable()->after('warranty');
            $table->dateTime('documents_declared_at')->nullable()->after('warranty_until');
            $table->dateTime('submitted_at')->nullable()->after('published_at');
            $table->json('screening')->nullable()->after('submitted_at');
        });
    }

    public function down(): void
    {
        Schema::table('lots', function (Blueprint $table): void {
            $table->dropColumn(['unit', 'warranty', 'warranty_until', 'documents_declared_at', 'submitted_at', 'screening']);
        });
    }
};

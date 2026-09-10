<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Preview v4 schrijft "kijkdag mogelijk" voor als filterschakelaar, maar
     * het datamodel had er geen veld voor.
     *
     * Afleiden uit de `viewings`-tabel kan niet: dáár staan gemaakte
     * afspraken, niet of de verkoper bezichtiging aanbiedt. Een kavel zonder
     * afspraken zou dan onterecht uit de filter vallen.
     */
    public function up(): void
    {
        Schema::table('lots', function (Blueprint $table) {
            $table->boolean('viewing_available')->default(false)->after('pickup_only');

            // De filter draait altijd samen met "alleen lopende kavels".
            $table->index(['status', 'viewing_available']);
        });
    }

    public function down(): void
    {
        Schema::table('lots', function (Blueprint $table) {
            $table->dropIndex(['status', 'viewing_available']);
            $table->dropColumn('viewing_available');
        });
    }
};

<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * De funnel verzamelt gegevens die pas bij publicatie een plek hebben:
     * prijzen en looptijd horen op `auctions`, maar die rij bestaat nog niet
     * zolang het een concept is.
     *
     * Dit veld is de wachtkamer. PublishLot leest het uit, bouwt er de veiling
     * van en maakt het leeg. Een halve veilingrij aanmaken tijdens het
     * concept zou betekenen dat elke veilingquery rekening moet houden met
     * rijen die geen veiling zijn.
     */
    public function up(): void
    {
        Schema::table('lots', function (Blueprint $table) {
            $table->json('draft_meta')->nullable()->after('draft_token');
        });
    }

    public function down(): void
    {
        Schema::table('lots', function (Blueprint $table) {
            $table->dropColumn('draft_meta');
        });
    }
};

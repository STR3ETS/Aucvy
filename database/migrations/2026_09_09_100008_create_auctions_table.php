<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /*
     | Eén rij per kavel met een verkooproute. Dit is het object waarop gelockt
     | wordt bij elk bod; hij is bewust smal zodat de lock kort duurt (hfst. 5).
     */
    public function up(): void
    {
        Schema::create('auctions', function (Blueprint $table) {
            $table->id();

            $table->foreignId('lot_id')->unique()->constrained()->cascadeOnDelete();

            $table->dateTime('starts_at');
            // Schuift op bij verlenging.
            $table->dateTime('ends_at');
            // Schuift nooit op - nodig voor rapportage en voor "3x verlengd".
            $table->dateTime('original_ends_at');

            $table->unsignedBigInteger('start_price_cents')->default(0);
            // Nooit publiek. Wel publiek: of hij gehaald is.
            $table->unsignedBigInteger('reserve_price_cents')->nullable();
            $table->boolean('reserve_met')->default(false);

            // Afgeleide waarde, maar opgeslagen - anders kost elke lijstweergave
            // een aggregatie. Bijgewerkt binnen dezelfde transactie als het bod.
            $table->unsignedBigInteger('current_price_cents')->default(0);
            $table->unsignedInteger('bid_count')->default(0);
            $table->unsignedInteger('bidder_count')->default(0);

            /*
             | De staffel wordt per veiling vastgelegd en niet uit config
             | gelezen. Anders verandert de biedstap van een lopende veiling met
             | terugwerkende kracht mee zodra iemand de config aanpast.
             */
            $table->json('increment_table');

            // Om dezelfde reden per veiling opgeslagen.
            $table->unsignedSmallInteger('anti_snipe_window_s')->default(30);
            $table->unsignedSmallInteger('anti_snipe_extend_s')->default(60);
            $table->unsignedSmallInteger('extension_count')->default(0);

            // Vervalt zodra er bóven de vaste prijs geboden is. Dat is een
            // transitie, geen berekening - vandaar een eigen kolom.
            $table->unsignedBigInteger('buy_now_price_cents')->nullable();
            $table->boolean('buy_now_active')->default(false);

            /*
             | De afslag. Geen tikkende schrijfacties: de prijs is een functie
             | van de tijd -
             |   max(floor, start - step * floor((now - starts_at) / interval))
             */
            $table->unsignedBigInteger('dutch_start_cents')->nullable();
            $table->unsignedBigInteger('dutch_floor_cents')->nullable();
            $table->unsignedBigInteger('dutch_step_cents')->nullable();
            $table->unsignedSmallInteger('dutch_interval_s')->nullable();

            /*
             | Teller voor auction_events én bids. Verhoogt binnen de lock.
             |
             | Dit is wat reconnects redt: twee events in dezelfde milliseconde
             | zijn niet op tijd te ordenen, en een client die terugkomt na een
             | netwerkdip moet een gat kunnen zien (hfst. 5).
             */
            $table->unsignedBigInteger('last_sequence')->default(0);

            $table->foreignId('winner_id')->nullable()->constrained('users')->nullOnDelete();
            // De vreemde sleutel volgt in de bids-migratie: die tabel bestaat
            // hier nog niet en verwijst zelf terug naar auctions.
            $table->unsignedBigInteger('winning_bid_id')->nullable();
            $table->dateTime('closed_at')->nullable();

            $table->datetimes();

            // Voor het vangnet `auctions:close-overdue`, dat elke minuut zoekt
            // naar veilingen die door een herstart zijn blijven hangen.
            $table->index('ends_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('auctions');
    }
};

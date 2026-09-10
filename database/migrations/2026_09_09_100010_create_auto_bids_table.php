<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('auto_bids', function (Blueprint $table) {
            $table->id();

            $table->foreignId('auction_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();

            // Verhogen mag, verlagen niet: dat zou een nieuw maximum ónder een
            // bestaand bod zijn en dat kan niet (hfst. 5).
            $table->unsignedBigInteger('max_amount_cents');

            $table->boolean('active')->default(true);
            $table->dateTime('cancelled_at')->nullable();

            /*
             | De briefing vraagt: uniek op (auction_id, user_id) wáár active.
             | MySQL kent geen partiële unieke index, maar in een unieke index
             | botsen NULL-waarden niet met elkaar. Deze gegenereerde kolom is
             | 1 zolang de rij actief is en NULL zodra hij dat niet meer is.
             |
             | Resultaat: hooguit één actief maximum per bieder per veiling,
             | en onbeperkt veel ingetrokken maxima in de historie.
             */
            $table->unsignedTinyInteger('active_flag')
                ->nullable()
                ->virtualAs('CASE WHEN `active` = 1 THEN 1 ELSE NULL END');

            $table->datetimes();

            $table->unique(['auction_id', 'user_id', 'active_flag']);
            $table->index(['auction_id', 'max_amount_cents']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('auto_bids');
    }
};

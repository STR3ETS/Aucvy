<?php

declare(strict_types=1);

use App\Enums\BidStatus;
use App\Enums\BidType;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bids', function (Blueprint $table) {
            $table->id();

            $table->foreignId('auction_id')->constrained()->cascadeOnDelete();
            // Een bieder verwijderen mag de biedhistorie niet wegnemen; de
            // gebruiker wordt geanonimiseerd, het bod blijft staan (hfst. 15).
            $table->foreignId('user_id')->constrained()->restrictOnDelete();

            $table->unsignedBigInteger('amount_cents');

            $table->string('type', 20)->default(BidType::Manual->value);
            // Het enige veld dat na aanmaken nog wijzigt.
            $table->string('status', 20)->default(BidStatus::Winning->value);

            // Getrokken uit auctions.last_sequence, binnen de lock.
            $table->unsignedBigInteger('sequence');

            /*
             | Idempotentie wordt vóór de lock gecontroleerd: een dubbele submit
             | of een retry na een timeout geeft hetzelfde bod terug in plaats
             | van een tweede bod (hfst. 6).
             */
            $table->string('idempotency_key', 64)->unique();

            $table->dateTime('placed_at');

            // Niet om te tonen, wel om een patroon te kunnen bewijzen bij
            // vermoeden van shill bidding (hfst. 6).
            $table->string('ip', 45)->nullable();
            $table->text('user_agent')->nullable();

            $table->datetimes();

            // Twee biedingen kunnen nooit dezelfde plek in de volgorde krijgen.
            $table->unique(['auction_id', 'sequence']);

            // De biedhistorie op de kavelpagina, aflopend.
            $table->index(['auction_id', 'amount_cents']);
            $table->index(['user_id', 'status']);
        });

        // Nu bids bestaat, kan de sleutel vanuit auctions gelegd worden.
        Schema::table('auctions', function (Blueprint $table) {
            $table->foreign('winning_bid_id')
                ->references('id')
                ->on('bids')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('auctions', function (Blueprint $table) {
            $table->dropForeign(['winning_bid_id']);
        });

        Schema::dropIfExists('bids');
    }
};

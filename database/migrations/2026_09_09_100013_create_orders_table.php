<?php

declare(strict_types=1);

use App\Enums\OrderStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('orders', function (Blueprint $table) {
            $table->id();

            $table->string('order_number', 32)->unique();

            $table->foreignId('lot_id')->constrained()->restrictOnDelete();
            $table->foreignId('auction_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('buyer_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('seller_id')->constrained('users')->restrictOnDelete();

            /*
             | De kostenopbouw. Het totaal dat de koper zag vóór bevestigen, het
             | bedrag op deze rij en het bedrag bij de betaalprovider komen alle
             | drie uit App\Support\Money. Lopen ze uiteen, dan is dat per
             | definitie een bug en geen afronding (hfst. 9).
             */
            $table->unsignedBigInteger('hammer_cents');
            // Nul in de MVP. Het veld bestaat, de waarde is nul.
            $table->unsignedBigInteger('buyer_premium_cents')->default(0);
            $table->unsignedBigInteger('transport_cents')->default(0);
            $table->unsignedBigInteger('vat_cents')->default(0);
            $table->unsignedBigInteger('total_cents');

            // Vastgelegd op de order, niet gelezen van de kavel: de btw-regeling
            // op een factuur mag niet meeveranderen als de kavel later wijzigt.
            $table->string('vat_regime', 20);

            $table->string('status', 20)->default(OrderStatus::AwaitingPayment->value);

            $table->dateTime('payment_deadline_at')->nullable();
            $table->dateTime('paid_at')->nullable();
            $table->dateTime('delivered_at')->nullable();
            // Tot wanneer de koper een probleem kan melden. Na dit moment gaat
            // de order automatisch naar `completed` en komt de uitbetaling vrij.
            $table->dateTime('dispute_window_ends_at')->nullable();
            $table->dateTime('completed_at')->nullable();

            $table->datetimes();

            $table->index(['buyer_id', 'status']);
            $table->index(['seller_id', 'status']);
            // Voor de scheduled job die verlopen betaaltermijnen opruimt.
            $table->index(['status', 'payment_deadline_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('orders');
    }
};

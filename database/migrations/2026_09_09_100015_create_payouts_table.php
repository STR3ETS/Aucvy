<?php

declare(strict_types=1);

use App\Enums\PayoutStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payouts', function (Blueprint $table) {
            $table->id();

            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('seller_id')->constrained('users')->restrictOnDelete();

            $table->unsignedBigInteger('amount_cents');
            // De succesfee volgens het plan van de verkoper.
            $table->unsignedBigInteger('fee_cents')->default(0);

            $table->string('status', 20)->default(PayoutStatus::Scheduled->value);

            /*
             | De houdtermijn is een kolom en een scheduled job die vrijgeeft,
             | geen handmatige handeling. De zekerheidspagina belóóft die
             | termijn; het systeem dwingt hem hier af (hfst. 9).
             */
            $table->dateTime('hold_until')->nullable();
            $table->dateTime('released_at')->nullable();
            $table->dateTime('paid_at')->nullable();

            // Providervrij houden: welke partij het uitbetaalt hangt af van de
            // derdengeldenkeuze die nog open staat (hfst. 18).
            $table->string('provider', 32)->nullable();
            $table->string('provider_payout_id')->nullable();
            $table->string('failure_reason')->nullable();

            $table->datetimes();

            // Voor de job die vrijgeeft zodra de houdtermijn verstreken is.
            $table->index(['status', 'hold_until']);
            $table->index(['seller_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payouts');
    }
};

<?php

declare(strict_types=1);

use App\Enums\PaymentStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payments', function (Blueprint $table) {
            $table->id();

            $table->foreignId('order_id')->constrained()->cascadeOnDelete();

            $table->string('provider', 32)->default('mollie');
            $table->string('provider_payment_id')->nullable()->unique();

            $table->string('status', 20)->default(PaymentStatus::Pending->value);
            $table->string('method', 32)->nullable();

            $table->unsignedBigInteger('amount_cents');
            $table->unsignedBigInteger('refunded_cents')->default(0);

            $table->dateTime('paid_at')->nullable();

            /*
             | De ruwe webhook bewaren. Betaalstatus is nooit af te leiden uit
             | de redirect - alleen uit de webhook, en die moet idempotent
             | verwerkt worden (hfst. 14).
             */
            $table->json('webhook_payload')->nullable();

            $table->datetimes();

            $table->index(['order_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payments');
    }
};

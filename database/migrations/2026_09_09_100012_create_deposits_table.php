<?php

declare(strict_types=1);

use App\Enums\DepositStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /*
     | Boven een drempelbedrag vraagt het platform een reservering vóórdat
     | iemand mag bieden. Dat is de enige echte bescherming tegen een winnende
     | bieder die niet betaalt (hfst. 9).
     |
     | PlaceBid krijgt hiervoor een extra poortwachter guardDeposit(), naast
     | guardVerified().
     */
    public function up(): void
    {
        Schema::create('deposits', function (Blueprint $table) {
            $table->id();

            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->foreignId('auction_id')->constrained()->cascadeOnDelete();

            $table->unsignedBigInteger('amount_cents');

            $table->string('provider', 32)->nullable();
            $table->string('provider_id')->nullable();

            $table->string('status', 20)->default(DepositStatus::Pending->value);

            // Vrijgeven gebeurt automatisch bij verliezen; bij winnen wordt de
            // borg verrekend met de order.
            $table->dateTime('released_at')->nullable();
            $table->string('failure_reason')->nullable();

            $table->datetimes();

            $table->unique(['user_id', 'auction_id']);
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('deposits');
    }
};

<?php

declare(strict_types=1);

use App\Enums\ViewingStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('viewings', function (Blueprint $table) {
            $table->id();

            $table->foreignId('lot_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();

            $table->dateTime('slot_at');
            $table->string('status', 20)->default(ViewingStatus::Requested->value);
            $table->text('note')->nullable();

            // Het adres gaat pas per mail ná bevestiging - nooit op de
            // kavelpagina (hfst. 7). Dit legt vast wanneer dat gebeurd is.
            $table->dateTime('address_sent_at')->nullable();

            $table->datetimes();

            $table->index(['lot_id', 'slot_at']);
            $table->index(['user_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('viewings');
    }
};

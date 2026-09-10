<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('watchlist_items', function (Blueprint $table) {
            $table->id();

            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('lot_id')->constrained()->cascadeOnDelete();

            $table->datetimes();

            $table->unique(['user_id', 'lot_id']);
            // "Op je volglijst staat een kavel die bijna sluit" is een
            // pushmelding; die query loopt over lot_id.
            $table->index('lot_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('watchlist_items');
    }
};

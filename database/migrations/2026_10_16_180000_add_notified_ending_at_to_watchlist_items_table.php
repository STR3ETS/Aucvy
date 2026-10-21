<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
| "Een gevolgde veiling sluit bijna" gaat één keer per volger per kavel
| (briefing sectie 3). Dit is het vinkje dat die ene keer vastlegt.
*/
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('watchlist_items', function (Blueprint $table): void {
            $table->dateTime('notified_ending_at')->nullable()->after('lot_id');
        });
    }

    public function down(): void
    {
        Schema::table('watchlist_items', fn (Blueprint $table) => $table->dropColumn('notified_ending_at'));
    }
};

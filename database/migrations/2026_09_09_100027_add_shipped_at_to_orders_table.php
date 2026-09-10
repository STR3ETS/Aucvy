<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /*
     | Wanneer de verkoper de order op "verzonden" of "klaar voor ophalen"
     | zette. Het automatisch leveren na zeven dagen (hfst. 9) telt vanaf dít
     | moment - vanaf de betaling tellen zou een trage verkoper belonen.
     */
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dateTime('shipped_at')->nullable()->after('paid_at');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn('shipped_at');
        });
    }
};

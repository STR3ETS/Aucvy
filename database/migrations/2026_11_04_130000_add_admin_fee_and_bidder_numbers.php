<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Besluiten Raph en Don (2 november 2026):
 *  - administratiekosten voor de koper per order (N4), vastgelegd op de
 *    order zodat een latere wijziging van het tarief oude orders niet raakt;
 *  - een biednaam die Aucvy maakt, zoals "Bieder 4821" (N7); de koper kan
 *    hem wijzigen via display_name.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table): void {
            $table->unsignedInteger('admin_fee_cents')->default(0)->after('buyer_premium_cents');
        });

        Schema::table('users', function (Blueprint $table): void {
            $table->unsignedInteger('bidder_number')->nullable()->unique()->after('display_name');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->dropColumn('bidder_number');
        });

        Schema::table('orders', function (Blueprint $table): void {
            $table->dropColumn('admin_fee_cents');
        });
    }
};

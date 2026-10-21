<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
| Blokkade op een account (beheer), de reden waarom een uitbetaling niet
| vrijgegeven wordt, en de checkout-URL van een betaling zodat "betalen"
| idempotent dezelfde sessie terug kan geven.
*/
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->dateTime('blocked_at')->nullable()->after('can_bid');
            $table->string('blocked_reason')->nullable()->after('blocked_at');
        });

        Schema::table('payouts', function (Blueprint $table): void {
            $table->string('blocked_reason', 40)->nullable()->after('failure_reason');
        });

        Schema::table('payments', function (Blueprint $table): void {
            $table->text('checkout_url')->nullable()->after('provider_payment_id');
        });
    }

    public function down(): void
    {
        Schema::table('payments', fn (Blueprint $table) => $table->dropColumn('checkout_url'));
        Schema::table('payouts', fn (Blueprint $table) => $table->dropColumn('blocked_reason'));
        Schema::table('users', fn (Blueprint $table) => $table->dropColumn(['blocked_at', 'blocked_reason']));
    }
};

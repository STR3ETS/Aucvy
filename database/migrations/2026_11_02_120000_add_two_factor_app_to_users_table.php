<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tweede factor via een authenticator-app (besluit Raph, 2 november 2026):
 * TOTP met herstelcodes; de mailcode blijft als terugval.
 *
 * - two_factor_secret: het gedeelde geheim, versleuteld met de app-sleutel;
 * - two_factor_recovery_codes: alleen hashes, de codes zelf ziet de
 *   gebruiker één keer;
 * - two_factor_last_step: het laatst gebruikte tijdvak, zodat een
 *   onderschepte code niet nog een keer werkt.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->text('two_factor_secret')->nullable()->after('remember_token');
            $table->text('two_factor_recovery_codes')->nullable()->after('two_factor_secret');
            $table->dateTime('two_factor_confirmed_at')->nullable()->after('two_factor_recovery_codes');
            $table->unsignedBigInteger('two_factor_last_step')->nullable()->after('two_factor_confirmed_at');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->dropColumn(['two_factor_secret', 'two_factor_recovery_codes', 'two_factor_confirmed_at', 'two_factor_last_step']);
        });
    }
};

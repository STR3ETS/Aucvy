<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
| UBO-verklaring bij de bedrijfscontrole (briefing sectie 2: KYB met KvK,
| UBO en IBAN). Het bedrijf verklaart wie de uiteindelijk belanghebbenden
| zijn; compliance kijkt het na bij het KvK-besluit.
*/
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('companies', function (Blueprint $table): void {
            $table->json('ubo_declaration')->nullable()->after('ubo_verified_at');
            $table->dateTime('ubo_declared_at')->nullable()->after('ubo_declaration');
        });
    }

    public function down(): void
    {
        Schema::table('companies', function (Blueprint $table): void {
            $table->dropColumn(['ubo_declaration', 'ubo_declared_at']);
        });
    }
};

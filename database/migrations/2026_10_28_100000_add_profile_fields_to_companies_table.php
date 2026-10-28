<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Het publieke verkopersprofiel (masterbriefing 4.1): beeldmerk, cover en
 * een omschrijving van de organisatie. Geen KYC- of financiële gegevens;
 * die blijven waar ze zijn.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('companies', function (Blueprint $table): void {
            $table->text('description')->nullable()->after('vat_number');
            $table->string('logo_path')->nullable()->after('description');
            $table->string('cover_path')->nullable()->after('logo_path');
        });
    }

    public function down(): void
    {
        Schema::table('companies', function (Blueprint $table): void {
            $table->dropColumn(['description', 'logo_path', 'cover_path']);
        });
    }
};

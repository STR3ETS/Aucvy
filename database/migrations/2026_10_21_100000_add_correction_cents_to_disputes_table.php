<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Een prijscorrectie is een bedrag, geen zin in de motivering: het gaat van
 * de uitbetaling af en support ziet wat er naar de koper terug moet.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('disputes', function (Blueprint $table): void {
            $table->unsignedInteger('correction_cents')->nullable()->after('resolution_notes');
        });
    }

    public function down(): void
    {
        Schema::table('disputes', function (Blueprint $table): void {
            $table->dropColumn('correction_cents');
        });
    }
};

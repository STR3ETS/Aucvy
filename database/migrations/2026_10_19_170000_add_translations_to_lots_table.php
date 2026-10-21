<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
| Vertalingen van de advertentie (briefing sectie 5, stap 4: vertaling).
| Per taal titel en tekst, met een hash van de Nederlandse bron zodat een
| verouderde vertaling nooit getoond wordt.
*/
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('lots', function (Blueprint $table): void {
            $table->json('translations')->nullable()->after('field_sources');
        });
    }

    public function down(): void
    {
        Schema::table('lots', function (Blueprint $table): void {
            $table->dropColumn('translations');
        });
    }
};

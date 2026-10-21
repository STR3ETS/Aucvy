<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
| Herkomst per veld (briefing sectie 5): bron, zekerheid en of de verkoper
| het nog moet bevestigen. Zie App\Lots\Provenance.
*/
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('lots', function (Blueprint $table): void {
            $table->json('field_sources')->nullable()->after('draft_meta');
        });
    }

    public function down(): void
    {
        Schema::table('lots', fn (Blueprint $table) => $table->dropColumn('field_sources'));
    }
};

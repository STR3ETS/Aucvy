<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
| Pauzeren (briefing sectie 7: published naar paused). Zolang dit gezet is,
| sluit de veiling niet en telt de tijd niet door; bij hervatten schuift de
| eindtijd met de pauzeduur op.
*/
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('auctions', function (Blueprint $table): void {
            $table->dateTime('paused_at')->nullable()->after('closed_at');
        });
    }

    public function down(): void
    {
        Schema::table('auctions', fn (Blueprint $table) => $table->dropColumn('paused_at'));
    }
};

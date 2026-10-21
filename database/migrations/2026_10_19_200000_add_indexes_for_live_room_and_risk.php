<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
| De live control room telt biedingen op tijd en de risicoscan zoekt op
| IP-adres; beide liepen zonder index over de hele biedtabel.
*/
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bids', function (Blueprint $table): void {
            $table->index('placed_at');
            $table->index('ip');
        });

        Schema::table('failed_jobs', function (Blueprint $table): void {
            $table->index('failed_at');
        });
    }

    public function down(): void
    {
        Schema::table('bids', function (Blueprint $table): void {
            $table->dropIndex(['placed_at']);
            $table->dropIndex(['ip']);
        });

        Schema::table('failed_jobs', function (Blueprint $table): void {
            $table->dropIndex(['failed_at']);
        });
    }
};

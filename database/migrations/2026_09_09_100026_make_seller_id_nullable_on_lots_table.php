<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * De funnel werkt zonder account: stap 1 t/m 4 vóórdat er wordt ingelogd
     * (briefing hfst. 13.4). Een anoniem concept heeft dus nog geen verkoper -
     * die wordt bij het inloggen of publiceren ingevuld.
     *
     * De oorspronkelijke migratie maakte de kolom verplicht; corrigeren doe je
     * met een nieuwe migratie, niet door de oude aan te passen (hfst. 4).
     */
    public function up(): void
    {
        Schema::table('lots', function (Blueprint $table) {
            $table->dropForeign(['seller_id']);
            $table->foreignId('seller_id')->nullable()->change();
            $table->foreign('seller_id')->references('id')->on('users')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('lots', function (Blueprint $table) {
            $table->dropForeign(['seller_id']);
            $table->foreignId('seller_id')->nullable(false)->change();
            $table->foreign('seller_id')->references('id')->on('users')->restrictOnDelete();
        });
    }
};

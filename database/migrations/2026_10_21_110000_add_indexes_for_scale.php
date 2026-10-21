<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Indexen uit de prestatiereview (blok V): de query's die elke minuut of
 * elke paar seconden draaien mogen niet met de hele tabel meegroeien.
 */
return new class extends Migration
{
    public function up(): void
    {
        // Het vangnet (auctions:close-overdue, elke minuut) en de live room:
        // closed_at IS NULL als eerste kolom, dan alleen de open veilingen.
        Schema::table('auctions', function (Blueprint $table): void {
            $table->index(['closed_at', 'ends_at'], 'auctions_open_ends_at_index');
        });

        // Verlengingen in het laatste uur (live room) en de dagelijkse opruiming.
        Schema::table('auction_events', function (Blueprint $table): void {
            $table->index('created_at');
        });

        // De activiteitenfeed en de sitemap sorteren op publicatie.
        Schema::table('lots', function (Blueprint $table): void {
            $table->index('published_at');
        });

        // De compliance-queue en het gebruikersfilter.
        Schema::table('users', function (Blueprint $table): void {
            $table->index('kyc_status');
        });

        // De auditlogpagina (telling en filter op periode) en de bewaartermijn.
        Schema::table('audit_logs', function (Blueprint $table): void {
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::table('auctions', fn (Blueprint $table) => $table->dropIndex('auctions_open_ends_at_index'));
        Schema::table('auction_events', fn (Blueprint $table) => $table->dropIndex(['created_at']));
        Schema::table('lots', fn (Blueprint $table) => $table->dropIndex(['published_at']));
        Schema::table('users', fn (Blueprint $table) => $table->dropIndex(['kyc_status']));
        Schema::table('audit_logs', fn (Blueprint $table) => $table->dropIndex(['created_at']));
    }
};

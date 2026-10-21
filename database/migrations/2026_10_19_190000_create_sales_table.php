<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
| Auction Studio (briefing sectie 4 en 1): een veiling als verzameling
| kavels van één verkoper, met een sluitschema (eerste sluiting plus een
| vaste tussentijd per kavel), bezichtiging, ophalen en eigen voorwaarden.
| De kavels houden hun eigen biedronde in `auctions`; de veiling plant ze.
*/
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sales', function (Blueprint $table): void {
            $table->id();
            $table->string('public_id', 12)->unique();
            $table->foreignId('seller_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('company_id')->nullable()->constrained()->nullOnDelete();
            $table->string('title', 150);
            $table->string('slug')->unique();
            $table->text('description')->nullable();
            $table->dateTime('ends_at');
            $table->unsignedSmallInteger('closing_gap_seconds')->default(120);
            $table->dateTime('viewing_at')->nullable();
            $table->string('viewing_note', 500)->nullable();
            $table->date('pickup_from')->nullable();
            $table->date('pickup_until')->nullable();
            $table->string('pickup_note', 500)->nullable();
            $table->text('terms')->nullable();
            $table->dateTime('published_at')->nullable();
            $table->dateTime('cancelled_at')->nullable();
            $table->datetimes();

            $table->index(['seller_id', 'published_at']);
        });

        Schema::table('lots', function (Blueprint $table): void {
            $table->foreignId('sale_id')->nullable()->after('category_id')->constrained('sales')->nullOnDelete();
            $table->unsignedSmallInteger('sale_position')->nullable()->after('sale_id');
        });
    }

    public function down(): void
    {
        Schema::table('lots', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('sale_id');
            $table->dropColumn('sale_position');
        });

        Schema::dropIfExists('sales');
    }
};

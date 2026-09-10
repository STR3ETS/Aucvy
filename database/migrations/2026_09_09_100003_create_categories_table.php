<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('categories', function (Blueprint $table) {
            $table->id();

            $table->foreignId('parent_id')
                ->nullable()
                ->constrained('categories')
                // Een hoofdcategorie met subcategorieën eronder mag niet zomaar
                // verdwijnen; dat moet een expliciete actie zijn.
                ->restrictOnDelete();

            $table->string('name');
            $table->string('slug')->unique();
            $table->unsignedSmallInteger('position')->default(0);

            /*
             | Categoriespecifieke velden: bij graafmachines urenstand en
             | bouwjaar, bij voertuigen kenteken en kilometerstand. Beschrijft
             | de vorm van lots.specifications.
             |
             | Begin met drie categorieën volledig uitgewerkt en de rest
             | generiek - een half schema is erger dan geen schema, want het
             | suggereert volledigheid die er niet is (hfst. 5).
             */
            $table->json('specifications_schema')->nullable();

            $table->datetimes();

            $table->index(['parent_id', 'position']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('categories');
    }
};

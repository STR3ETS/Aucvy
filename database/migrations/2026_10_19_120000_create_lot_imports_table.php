<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
| CSV-import van kavels (briefing sectie 4: /studio/import met kolommapping
| en foutrapport). Elke import is een rij: het bestand, de kolommen, de
| gekozen mapping en het rapport.
*/
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('lot_imports', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('filename');
            $table->string('path');
            $table->json('headers');
            $table->json('mapping')->nullable();
            $table->string('status', 16)->default('uploaded');
            $table->unsignedInteger('rows_total')->default(0);
            $table->unsignedInteger('rows_created')->default(0);
            $table->json('errors')->nullable();
            $table->datetimes();

            $table->index(['user_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lot_imports');
    }
};

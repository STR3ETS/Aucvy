<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /*
     | Alles wat geld, status of eigendom raakt: wie, wat, wanneer, oude en
     | nieuwe waarde. Append-only en niet muteerbaar vanuit de applicatie
     | (hfst. 15) - vandaar geen updated_at.
     */
    public function up(): void
    {
        Schema::create('audit_logs', function (Blueprint $table) {
            $table->id();

            // Nullable: een systeemhandeling zoals het vrijgeven van een
            // uitbetaling heeft geen menselijke actor.
            $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();

            $table->string('action', 64);
            $table->morphs('subject');

            $table->json('before')->nullable();
            $table->json('after')->nullable();

            $table->string('ip', 45)->nullable();

            $table->dateTime('created_at');

            $table->index(['action', 'created_at']);
            $table->index(['actor_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('audit_logs');
    }
};

<?php

declare(strict_types=1);

use App\Enums\AiRunStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /*
     | Elke AI-taak landt hier, geslaagd of niet. Zonder dit log kun je later
     | niet uitleggen waarom Ivy iets zei, en dat ga je een keer moeten kunnen
     | (hfst. 8).
     */
    public function up(): void
    {
        Schema::create('ai_runs', function (Blueprint $table) {
            $table->id();

            // Polymorf aan lot / auction / user.
            $table->nullableMorphs('subject');
            // Wie de taak opriep - nodig voor het dagbudget per gebruiker.
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();

            $table->string('agent', 20);
            $table->string('task', 64);
            $table->string('model', 64)->nullable();

            $table->json('input');
            // Pas gevuld ná validatie tegen het JSON-schema. Ongevalideerde
            // modeluitvoer komt nooit op een scherm.
            $table->json('output')->nullable();

            // Ruwe score 0.000-1.000. ConfidencePolicy vertaalt hem naar wat de
            // UI mag tonen; bij laag mag er geen exact bedrag als advies uit.
            $table->decimal('confidence', 4, 3)->nullable();

            $table->unsignedInteger('tokens_in')->nullable();
            $table->unsignedInteger('tokens_out')->nullable();
            $table->unsignedInteger('cost_cents')->default(0);
            $table->unsignedInteger('latency_ms')->nullable();

            $table->string('status', 20)->default(AiRunStatus::Queued->value);
            $table->text('error')->nullable();

            // Hash van input plus promptversie. Dezelfde foto's twee keer
            // analyseren kost dan niets extra.
            $table->string('cache_key', 64)->nullable();

            $table->datetimes();

            $table->index(['agent', 'task']);
            $table->index(['user_id', 'created_at']);
            $table->index('cache_key');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_runs');
    }
};

<?php

declare(strict_types=1);

use App\Enums\InsightSeverity;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /*
     | De kaarten op het dashboard. Gevuld door een wekelijkse job plus
     | gebeurtenisgedreven signalen (transport ontbreekt, document ontbreekt,
     | uitbetaling klaar). De dashboardpagina doet zelf geen AI-calls; hij
     | leest alleen (hfst. 13.5).
     */
    public function up(): void
    {
        Schema::create('ai_insights', function (Blueprint $table) {
            $table->id();

            $table->foreignId('user_id')->constrained()->cascadeOnDelete();

            // De specialist die het opmerkte staat erbij, met zijn kleur. Dat
            // maakt een advies navolgbaar in plaats van magisch.
            $table->string('agent', 20);
            $table->string('type', 64);

            $table->string('title');
            $table->text('body')->nullable();

            // Geen kaart zonder actie. Kun je geen concrete handeling bedenken,
            // dan is het geen signaal maar een cijfer.
            $table->string('action_route')->nullable();
            $table->string('action_label')->nullable();
            $table->json('action_params')->nullable();

            $table->string('severity', 20)->default(InsightSeverity::Info->value);

            // Een signaal dat niet meer klopt hoort te verdwijnen, niet te
            // blijven staan tot iemand hem wegklikt.
            $table->dateTime('valid_until')->nullable();
            $table->dateTime('dismissed_at')->nullable();

            $table->datetimes();

            $table->index(['user_id', 'dismissed_at', 'severity']);
            $table->index(['user_id', 'agent']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_insights');
    }
};

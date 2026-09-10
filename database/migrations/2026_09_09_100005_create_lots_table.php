<?php

declare(strict_types=1);

use App\Enums\LotStatus;
use App\Enums\VatRegime;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('lots', function (Blueprint $table) {
            $table->id();

            // Het kavelnummer dat de gebruiker ziet, bv. 8842. Het autonummer
            // wordt nooit getoond (hfst. 5).
            $table->string('public_id', 32)->unique();

            $table->foreignId('seller_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('company_id')->nullable()->constrained()->nullOnDelete();

            // Nullable: tijdens de funnel is de categorie nog niet gekozen.
            $table->foreignId('category_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('location_id')->nullable()->constrained()->nullOnDelete();

            /*
             | Vooruitgeschoven voor de liquidatieplanner (hfst. 19): kavels
             | moeten aan een overkoepelend project kunnen hangen. Nu nog zonder
             | vreemde sleutel, want de tabel bestaat nog niet - de eis is
             | alleen dat het datamodel het niet uitsluit.
             */
            $table->unsignedBigInteger('project_id')->nullable();

            $table->string('title')->nullable();
            // De slug bevat het public_id, zodat een titelwijziging de URL niet breekt.
            $table->string('slug')->nullable()->unique();
            $table->text('description')->nullable();

            $table->string('brand')->nullable();
            $table->string('model')->nullable();
            $table->unsignedSmallInteger('year')->nullable();
            $table->unsignedInteger('quantity')->default(1);

            $table->string('condition', 20)->nullable();

            /*
             | Categoriespecifiek: urenstand, typeplaatje, vermogen. Per veld
             | {value, source: ai|seller, confidence} - de herkomst moet
             | navolgbaar blijven (hfst. 5).
             |
             | De briefing noemt deze kolom `attributes`. Dat is bewust
             | hernoemd: Eloquent heeft zelf een protected $attributes, en
             | binnen het model levert $this->attributes dan de interne rauwe
             | array op in plaats van deze kolom. Van buiten valt dat niet op,
             | wat het juist gevaarlijk maakt. `specifications` is ook de naam
             | die de tab op de kavelpagina al draagt.
             |
             | Zoeken hierop loopt via Meilisearch (stap 7), niet via de
             | database. MySQL kent geen GIN-index; een multi-valued index per
             | attribuut is meer onderhoud dan hij oplevert.
             */
            $table->json('specifications')->nullable();

            $table->string('status', 20)->default(LotStatus::Draft->value);
            $table->string('sale_type', 20)->nullable();
            $table->string('vat_regime', 20)->default(VatRegime::Margin->value);

            // 0-100, berekend in PHP uit tellbare feiten - niet door de LLM.
            // Een score die per aanroep varieert is geen score (hfst. 13.4).
            $table->unsignedTinyInteger('completeness_score')->default(0);

            // Ivy-waarderange. Altijd samen tonen of niet tonen.
            $table->unsignedBigInteger('value_min_cents')->nullable();
            $table->unsignedBigInteger('value_max_cents')->nullable();
            $table->string('value_confidence', 10)->nullable();

            $table->boolean('pickup_only')->default(true);
            // Ontbreekt transportinfo, dan is dat een dashboardsignaal (Flow).
            $table->unsignedBigInteger('transport_from_cents')->nullable();

            /*
             | De funnel werkt zonder account: stap 1 t/m 4 hangen aan een
             | getekend cookie-token dat bij registratie wordt overgezet naar de
             | gebruiker. Bouw hier geen muur voor (hfst. 13.4).
             */
            $table->string('draft_token', 64)->nullable()->unique();

            $table->dateTime('published_at')->nullable();

            $table->datetimes();

            // Soft delete is verplicht: een verwijderde kavel met biedhistorie
            // moet reconstrueerbaar blijven.
            $table->softDeletesDatetime();

            /*
             | MySQL kent geen partiële index, dus waar de briefing
             | `WHERE status = 'live'` voorschrijft wordt het een gewone
             | samengestelde index. Die dekt dezelfde query - hij is alleen
             | groter omdat ook niet-live kavels erin staan.
             */
            $table->index(['status', 'published_at']);
            $table->index(['category_id', 'status']);
            $table->index(['seller_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lots');
    }
};

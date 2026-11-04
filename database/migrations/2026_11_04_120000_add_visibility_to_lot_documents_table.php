<?php

declare(strict_types=1);

use App\Enums\DocumentKind;
use App\Lots\Documents\DocumentVisibility;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Documenten-oogje (briefing nieuwe functies): elk document krijgt een
 * niveau. Openbaar (iedereen op de kavelpagina), na gunning (winnaar,
 * verkoper, medewerkers) of privé (verkoper, Lex, medewerkers). De
 * plaatsingsflow belooft dat facturen en eigendomsbewijzen privé blijven;
 * die belofte blijft staan.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('lot_documents', function (Blueprint $table): void {
            $table->string('visibility', 12)->default(DocumentVisibility::PUBLIC)->after('kind');
            $table->string('checked_by', 10)->nullable()->after('ai_findings');
            $table->unsignedInteger('view_count')->default(0)->after('checked_by');
            $table->dateTime('last_viewed_at')->nullable()->after('view_count');
        });

        // Bestaande documenten krijgen het standaardniveau van hun soort.
        foreach (DocumentKind::cases() as $kind) {
            DB::table('lot_documents')->where('kind', $kind->value)->update(['visibility' => DocumentVisibility::defaultFor($kind)]);
        }

        DB::table('lot_documents')->whereNotNull('ai_checked_at')->update(['checked_by' => 'lex']);
    }

    public function down(): void
    {
        Schema::table('lot_documents', function (Blueprint $table): void {
            $table->dropColumn(['visibility', 'checked_by', 'view_count', 'last_viewed_at']);
        });
    }
};

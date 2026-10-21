<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
| Moderatie en compliance (briefing sectie 7, flow E, en de admin uit de
| bouwlijst): wanneer een identiteits- of bedrijfscontrole is aangevraagd en
| met welke toelichting hij is beoordeeld, en wie een kavel wanneer heeft
| beoordeeld.
*/
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->dateTime('kyc_requested_at')->nullable()->after('kyc_verified_at');
            $table->string('kyc_note')->nullable()->after('kyc_requested_at');
        });

        Schema::table('companies', function (Blueprint $table): void {
            $table->dateTime('kyb_requested_at')->nullable()->after('verified_at');
            $table->string('kyb_note')->nullable()->after('kyb_requested_at');
        });

        Schema::table('lots', function (Blueprint $table): void {
            $table->dateTime('reviewed_at')->nullable()->after('published_at');
            $table->foreignId('reviewed_by')->nullable()->after('reviewed_at')->constrained('users')->nullOnDelete();
            $table->string('moderation_note')->nullable()->after('reviewed_by');
            $table->index(['status', 'reviewed_at']);
        });
    }

    public function down(): void
    {
        Schema::table('lots', function (Blueprint $table): void {
            $table->dropIndex(['status', 'reviewed_at']);
            $table->dropConstrainedForeignId('reviewed_by');
            $table->dropColumn(['reviewed_at', 'moderation_note']);
        });
        Schema::table('companies', fn (Blueprint $table) => $table->dropColumn(['kyb_requested_at', 'kyb_note']));
        Schema::table('users', fn (Blueprint $table) => $table->dropColumn(['kyc_requested_at', 'kyc_note']));
    }
};

<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Het kaveldossier (briefing nieuwe functies, 3 november 2026): één
 * tijdlijn per kavel waar elk bericht, elke mail en elke statuswijziging
 * op landt. Een projectie: de bronlogs (auditlog, veilingevents, orders,
 * mail, documenten) blijven staan; listeners vullen deze tabel.
 *
 * - visibility: wie de regel ziet (public, user, buyer, seller, parties, staff);
 * - user_id: bij visibility user de enige die hem ziet, naast beheer;
 * - source_ref: de bronregel ("audit:12"), zodat een projectie nooit dubbel
 *   komt en de tijdlijn opnieuw opgebouwd kan worden.
 *
 * De tijdlijn is alleen-lezen; een correctie is een nieuwe regel.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('lot_activities', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('lot_id')->constrained()->cascadeOnDelete();
            $table->foreignId('order_id')->nullable()->constrained()->nullOnDelete();
            $table->string('source', 20);
            $table->string('type', 40);
            $table->string('actor_type', 10)->default('system');
            $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('visibility', 16)->default('staff');
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->json('payload')->nullable();
            $table->string('source_ref', 60)->nullable()->unique();
            $table->dateTime('occurred_at');
            $table->timestamp('created_at')->nullable();

            $table->index(['lot_id', 'occurred_at']);
            $table->index(['order_id', 'occurred_at']);
            $table->index(['lot_id', 'source']);
        });

        // Mail aan de kavel koppelen: de webhook zet de status dan op de juiste regel.
        Schema::table('mail_deliveries', function (Blueprint $table): void {
            $table->foreignId('lot_id')->nullable()->after('user_id')->constrained()->nullOnDelete();
            $table->foreignId('order_id')->nullable()->after('lot_id')->constrained()->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('mail_deliveries', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('order_id');
            $table->dropConstrainedForeignId('lot_id');
        });

        Schema::dropIfExists('lot_activities');
    }
};

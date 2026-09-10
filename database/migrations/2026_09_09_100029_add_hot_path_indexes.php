<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /*
     | Uit de EXPLAIN-ronde van stap 13 (hfst. 16):
     |
     |  - de budgetbewaker telt bij élke AI-taak de dagkosten van de hele
     |    omgeving op - dat was een volledige tabelscan op ai_runs;
     |  - de categoriepagina sorteerde met filesort omdat de bestaande index
     |    (category_id, status) niet tot published_at reikt;
     |  - de auto-leverveeg filterde op shipped_at zonder index, in de stijl
     |    van de al bestaande (status, payment_deadline_at).
     */
    public function up(): void
    {
        Schema::table('ai_runs', function (Blueprint $table) {
            $table->index('created_at');
        });

        Schema::table('lots', function (Blueprint $table) {
            $table->index(['category_id', 'status', 'published_at']);
        });

        Schema::table('orders', function (Blueprint $table) {
            $table->index(['status', 'shipped_at']);
        });
    }

    public function down(): void
    {
        Schema::table('ai_runs', function (Blueprint $table) {
            $table->dropIndex(['created_at']);
        });

        Schema::table('lots', function (Blueprint $table) {
            $table->dropIndex(['category_id', 'status', 'published_at']);
        });

        Schema::table('orders', function (Blueprint $table) {
            $table->dropIndex(['status', 'shipped_at']);
        });
    }
};

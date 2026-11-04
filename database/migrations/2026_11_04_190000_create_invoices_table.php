<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 | Facturen (hoofdbriefing p. 7, voorstel): de verkoper factureert de kavel
 | aan de koper (Aucvy maakt die op namens de verkoper), Aucvy factureert
 | de administratiekosten aan de koper en de succesfee aan de verkoper. Een
 | prijscorrectie na een geschil wordt een creditnota. Elke factuur bewaart
 | de gegevens van beide partijen zoals ze op dat moment waren.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('invoices', function (Blueprint $table): void {
            $table->id();
            $table->string('number', 30)->unique();
            $table->string('series', 20);
            $table->unsignedInteger('sequence');
            $table->string('kind', 16);
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->string('issuer_type', 16);
            $table->foreignId('issuer_company_id')->nullable()->constrained('companies')->nullOnDelete();
            $table->foreignId('recipient_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('recipient_company_id')->nullable()->constrained('companies')->nullOnDelete();
            $table->foreignId('credits_invoice_id')->nullable()->constrained('invoices')->nullOnDelete();
            $table->date('issued_at');
            $table->date('delivery_date')->nullable();
            $table->string('vat_regime', 20);
            $table->json('parties');
            $table->json('lines');
            $table->bigInteger('subtotal_cents');
            $table->bigInteger('vat_cents');
            $table->bigInteger('total_cents');
            $table->string('note', 300)->nullable();
            $table->timestamps();

            $table->unique(['order_id', 'kind', 'credits_invoice_id']);
            $table->unique(['series', 'sequence']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('invoices');
    }
};

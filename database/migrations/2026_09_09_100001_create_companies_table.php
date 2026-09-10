<?php

declare(strict_types=1);

use App\Enums\CompanyPlan;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('companies', function (Blueprint $table) {
            $table->id();

            $table->string('name');
            $table->string('slug')->unique();

            // KvK-koppeling met controle op de tenaamstelling (hfst. 13.6).
            // Nullable tot de verificatie geslaagd is.
            $table->string('kvk_number', 20)->nullable()->unique();
            $table->string('vat_number', 20)->nullable();
            $table->dateTime('verified_at')->nullable();
            $table->dateTime('ubo_verified_at')->nullable();

            $table->string('plan', 20)->default(CompanyPlan::Start->value);

            // Succesfee. Staat als kolom zodat een afwijkende afspraak met een
            // grote verkoper geen codewijziging vraagt.
            $table->decimal('fee_percentage', 5, 2)->default(CompanyPlan::Start->defaultFeePercentage());

            // Alleen de laatste vier cijfers en de tenaamstelling. Het volledige
            // IBAN hoort bij de betaalprovider, niet hier.
            $table->string('payout_iban_last4', 4)->nullable();
            $table->string('payout_iban_name')->nullable();

            $table->datetimes();
            $table->softDeletesDatetime();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('companies');
    }
};

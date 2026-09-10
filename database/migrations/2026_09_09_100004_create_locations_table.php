<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('locations', function (Blueprint $table) {
            $table->id();

            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('company_id')->nullable()->constrained()->nullOnDelete();

            $table->string('label')->nullable();

            /*
             | Het volledige adres staat hier, maar wordt niet publiek getoond.
             | Op de kavelpagina zie je alleen plaats en provincie; het adres
             | gaat per mail ná een bevestigde bezichtiging (hfst. 7).
             */
            $table->string('street')->nullable();
            $table->string('house_number', 20)->nullable();
            $table->string('postal_code', 12)->nullable();
            $table->string('city');
            $table->string('province', 64)->nullable();
            $table->string('country', 2)->default('NL');

            // Voor de filter "binnen X km". Nullable tot geocodering gedraaid is.
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();

            $table->datetimes();

            $table->index(['province', 'city']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('locations');
    }
};

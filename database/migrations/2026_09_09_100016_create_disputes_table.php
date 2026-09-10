<?php

declare(strict_types=1);

use App\Enums\DisputeStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /*
     | De route uit de zekerheidspagina, met de termijnen die daar beloofd
     | worden, als statusmachine. Elke overgang schrijft naar audit_logs en
     | stuurt beide partijen een bericht. De uitbetaling staat de hele route
     | stil (hfst. 9).
     */
    public function up(): void
    {
        Schema::create('disputes', function (Blueprint $table) {
            $table->id();

            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('reported_by')->constrained('users')->restrictOnDelete();

            $table->string('reason', 64);
            $table->text('description')->nullable();
            // Foto's en documenten die de melder aanlevert.
            $table->json('evidence')->nullable();

            $table->string('status', 32)->default(DisputeStatus::Reported->value);

            $table->dateTime('seller_responded_at')->nullable();
            $table->text('seller_response')->nullable();

            // De 72-uurstermijn waarbinnen de verkoper mag reageren.
            $table->dateTime('sla_due_at')->nullable();

            $table->string('resolution', 64)->nullable();
            $table->text('resolution_notes')->nullable();
            // Een medewerker beoordeelt - geen algoritme. Dat staat zo op de
            // zekerheidspagina en moet waar blijven.
            $table->foreignId('handled_by')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('resolved_at')->nullable();

            $table->datetimes();

            $table->index(['order_id', 'status']);
            $table->index(['status', 'sla_due_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('disputes');
    }
};

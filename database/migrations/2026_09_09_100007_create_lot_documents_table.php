<?php

declare(strict_types=1);

use App\Enums\DocumentKind;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('lot_documents', function (Blueprint $table) {
            $table->id();

            $table->foreignId('lot_id')->constrained()->cascadeOnDelete();

            $table->string('kind', 20)->default(DocumentKind::Other->value);

            // Altijd op een private disk, uitsluitend via een getekende URL van
            // maximaal vijf minuten (hfst. 15). Nooit publiek.
            $table->string('path');
            $table->string('original_filename')->nullable();
            $table->string('mime_type', 120)->nullable();
            $table->unsignedBigInteger('size_bytes')->nullable();

            // "12 documenten gecontroleerd op volledigheid", plus wat ontbreekt.
            $table->dateTime('ai_checked_at')->nullable();
            $table->json('ai_findings')->nullable();

            $table->datetimes();

            $table->index(['lot_id', 'kind']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lot_documents');
    }
};

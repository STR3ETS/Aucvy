<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /*
     | Alleen kopers die de kavel daadwerkelijk afnamen kunnen beoordelen, en
     | reviews worden niet verwijderd op verzoek. Dat is het verschil met een
     | anoniem platform, en het is een technische regel - geen redactionele
     | (hfst. 13.6).
     */
    public function up(): void
    {
        Schema::create('reviews', function (Blueprint $table) {
            $table->id();

            // Eén review per order. De unieke sleutel dwingt af dat er geen
            // tweede beoordeling op dezelfde transactie kan ontstaan.
            $table->foreignId('order_id')->unique()->constrained()->restrictOnDelete();

            $table->foreignId('reviewer_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('seller_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('company_id')->nullable()->constrained()->nullOnDelete();

            $table->unsignedTinyInteger('rating');
            $table->text('body')->nullable();

            // De verkoper mag reageren. Kritische reviews blijven staan; een
            // weerwoord is het enige antwoord daarop.
            $table->text('seller_reply')->nullable();
            $table->dateTime('seller_replied_at')->nullable();

            $table->dateTime('published_at')->nullable();

            $table->datetimes();

            $table->index(['seller_id', 'published_at']);
            $table->index(['company_id', 'rating']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reviews');
    }
};

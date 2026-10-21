<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
| Berichten bij een bestelling (briefing sectie 7: in-app berichten tussen
| koper, verkoper en support). Eén draad per order; support schrijft mee
| vanuit de backoffice.
*/
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('order_messages', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->boolean('from_staff')->default(false);
            $table->text('body');
            $table->datetimes();

            $table->index(['order_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('order_messages');
    }
};

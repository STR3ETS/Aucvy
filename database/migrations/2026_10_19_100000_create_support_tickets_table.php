<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
| Support (briefing sectie 1 en 7): het contactformulier levert een ticket
| op dat beheer in de backoffice afhandelt. Zonder account kan ook; dan
| staat alleen naam en e-mail op het ticket.
*/
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('support_tickets', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name', 120);
            $table->string('email', 190);
            $table->string('subject', 150);
            $table->text('message');
            $table->string('order_number', 32)->nullable();
            $table->string('status', 16)->default('open');
            $table->dateTime('closed_at')->nullable();
            $table->foreignId('closed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('reply')->nullable();
            $table->string('ip', 45)->nullable();
            $table->datetimes();

            $table->index(['status', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('support_tickets');
    }
};

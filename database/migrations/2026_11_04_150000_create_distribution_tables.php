<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 | Delen en verspreiden (briefing nieuwe functies): een deling door een
 | koper met eigen token, de kanalen die een verkoper koppelt en wat er per
 | kanaal en moment is geplaatst.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('lot_shares', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('lot_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('token', 24)->unique();
            $table->string('channel', 24);
            $table->string('note', 200)->nullable();
            $table->unsignedInteger('open_count')->default(0);
            $table->timestamp('opened_at')->nullable();
            $table->string('reaction', 16)->nullable();
            $table->string('reaction_text', 300)->nullable();
            $table->timestamp('reacted_at')->nullable();
            $table->timestamp('created_at');

            $table->index(['lot_id', 'user_id']);
        });

        Schema::create('channel_connections', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('company_id')->nullable()->constrained()->nullOnDelete();
            $table->string('channel', 24);
            $table->text('tokens')->nullable();
            $table->string('external_id')->nullable();
            $table->string('external_name')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->string('status', 16)->default('pending');
            $table->text('last_error')->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'channel']);
        });

        Schema::create('lot_distributions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('lot_id')->constrained()->cascadeOnDelete();
            $table->string('channel', 24);
            $table->string('moment', 16)->default('kit');
            $table->string('client_key', 80)->unique();
            $table->string('status', 16)->default('draft');
            $table->text('body')->nullable();
            $table->string('body_source', 16)->default('template');
            $table->string('visual_path')->nullable();
            $table->string('url', 500)->nullable();
            $table->string('external_id')->nullable();
            $table->timestamp('scheduled_at')->nullable();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('posted_at')->nullable();
            $table->timestamp('withdrawn_at')->nullable();
            $table->text('last_error')->nullable();
            $table->unsignedInteger('clicks')->default(0);
            $table->timestamps();

            $table->index(['lot_id', 'moment']);
            $table->index(['status', 'scheduled_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lot_distributions');
        Schema::dropIfExists('channel_connections');
        Schema::dropIfExists('lot_shares');
    }
};

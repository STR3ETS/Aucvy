<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Een gesprek hoort bij één teamlid: Ivy of een van de vijf specialisten.
 * Bestaande gesprekken zijn van Ivy.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ivy_conversations', function (Blueprint $table): void {
            $table->string('agent', 16)->default('ivy')->after('role');
        });
    }

    public function down(): void
    {
        Schema::table('ivy_conversations', function (Blueprint $table): void {
            $table->dropColumn('agent');
        });
    }
};

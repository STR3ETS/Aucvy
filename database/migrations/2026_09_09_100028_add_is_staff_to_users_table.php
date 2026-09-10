<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /*
     | "Een medewerker beoordeelt - geen algoritme" (hfst. 9). Die medewerker
     | heeft een vlag nodig. Bewust een boolean en geen rollensysteem: er is
     | precies één onderscheid nodig en een rollenpakket erbij slepen voor één
     | vlag is de verkeerde ruil.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->boolean('is_staff')->default(false)->after('can_bid');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('is_staff');
        });
    }
};

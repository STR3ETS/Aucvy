<?php

declare(strict_types=1);

use App\Enums\CompanyRole;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/*
| Organisatierollen (briefing sectie 2): een rol per gebruiker binnen het
| bedrijf, en uitnodigingen per e-mail. Wie er al bij een bedrijf hoort
| wordt eigenaar (de eerste) of beheerder (de rest).
*/
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->string('company_role', 16)->nullable()->after('company_id');
        });

        $firstPerCompany = DB::table('users')
            ->selectRaw('company_id, min(id) as first_id')
            ->whereNotNull('company_id')
            ->groupBy('company_id')
            ->get();

        foreach ($firstPerCompany as $row) {
            DB::table('users')->where('id', $row->first_id)->update(['company_role' => CompanyRole::Owner->value]);
            DB::table('users')->where('company_id', $row->company_id)->where('id', '!=', $row->first_id)->update(['company_role' => CompanyRole::Admin->value]);
        }

        Schema::create('company_invitations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('invited_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('email');
            $table->string('role', 16);
            $table->string('token', 64)->unique();
            $table->dateTime('expires_at');
            $table->dateTime('accepted_at')->nullable();
            $table->datetimes();

            $table->index(['company_id', 'email']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('company_invitations');

        Schema::table('users', function (Blueprint $table): void {
            $table->dropColumn('company_role');
        });
    }
};

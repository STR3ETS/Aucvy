<?php

declare(strict_types=1);

use App\Enums\KycStatus;
use App\Enums\UserType;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('company_id')
                ->nullable()
                ->after('id')
                ->constrained()
                // Een bedrijf verwijderen mag de gebruiker niet meenemen -
                // die houdt zijn biedhistorie.
                ->nullOnDelete();

            $table->string('type', 20)->default(UserType::Private->value)->after('email');

            // Wat het publiek ziet: "R. Jansen". Nooit de volledige naam en
            // nooit een e-mailadres (hfst. 15).
            $table->string('display_name')->nullable()->after('name');

            $table->string('phone', 32)->nullable()->after('email_verified_at');
            $table->dateTime('phone_verified_at')->nullable()->after('phone');

            $table->string('kyc_status', 20)->default(KycStatus::Unverified->value)->after('type');
            $table->dateTime('kyc_verified_at')->nullable()->after('kyc_status');

            // Afgeleide waarde, bewust opgeslagen zodat het biedpad geen extra
            // query kost. Alleen de verificatieflow schrijft hem; nergens
            // anders. Zie hfst. 5.
            $table->boolean('can_bid')->default(false)->after('kyc_verified_at');

            $table->json('notification_prefs')->nullable();

            $table->softDeletesDatetime();

            $table->index(['company_id', 'type']);
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex(['company_id', 'type']);
            $table->dropConstrainedForeignId('company_id');

            $table->dropColumn([
                'type',
                'display_name',
                'phone',
                'phone_verified_at',
                'kyc_status',
                'kyc_verified_at',
                'can_bid',
                'notification_prefs',
                'deleted_at',
            ]);
        });
    }
};

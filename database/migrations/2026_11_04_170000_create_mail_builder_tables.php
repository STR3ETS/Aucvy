<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 | Mail Builder en automations (briefing nieuwe functies): templates met
 | versies, campagnes, reeksen met een doel, toestemmingen en de
 | suppressielijst. Het afleverlog (mail_deliveries) krijgt de template,
 | de stroom en de klik erbij.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mail_templates', function (Blueprint $table): void {
            $table->id();
            $table->string('key', 60)->unique();
            $table->unsignedSmallInteger('number');
            $table->string('stream', 16);
            $table->string('group', 60);
            $table->string('name', 120);
            $table->string('trigger', 200);
            $table->string('notification', 120)->nullable();
            $table->unsignedBigInteger('current_version_id')->nullable();
            $table->timestamps();
        });

        Schema::create('mail_template_versions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('template_id')->constrained('mail_templates')->cascadeOnDelete();
            $table->unsignedInteger('version');
            $table->string('subject', 200);
            $table->string('preheader', 200)->nullable();
            $table->string('sender', 16)->default('aucvy');
            $table->json('blocks');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('created_at');

            $table->unique(['template_id', 'version']);
        });

        Schema::create('mail_campaigns', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('template_version_id')->constrained('mail_template_versions')->cascadeOnDelete();
            $table->string('name', 120);
            $table->json('segment');
            $table->timestamp('scheduled_at')->nullable();
            $table->string('status', 16)->default('draft');
            $table->unsignedInteger('sent_count')->default(0);
            $table->unsignedInteger('skipped_count')->default(0);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('sent_at')->nullable();
            $table->timestamps();
        });

        Schema::create('mail_automations', function (Blueprint $table): void {
            $table->id();
            $table->string('key', 60)->unique();
            $table->unsignedSmallInteger('number');
            $table->string('name', 120);
            $table->string('stream', 16);
            $table->string('status', 16)->default('active');
            $table->json('steps');
            $table->string('goal_event', 120);
            $table->boolean('weekly_limit_applies')->default(true);
            $table->string('description', 300)->nullable();
            $table->timestamps();
        });

        Schema::create('automation_runs', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('automation_id')->constrained('mail_automations')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('lot_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('order_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedSmallInteger('current_step')->default(0);
            $table->timestamp('next_run_at')->nullable();
            $table->string('status', 16)->default('running');
            $table->string('stop_reason', 60)->nullable();
            $table->string('dedupe_key', 120)->unique();
            $table->timestamps();

            $table->index(['status', 'next_run_at']);
        });

        Schema::create('mail_consents', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('stream', 16);
            $table->string('status', 16);
            $table->string('source', 60)->nullable();
            $table->timestamp('changed_at');

            $table->unique(['user_id', 'stream']);
        });

        Schema::create('mail_suppressions', function (Blueprint $table): void {
            $table->id();
            $table->string('email_hash', 64)->unique();
            $table->string('reason', 60);
            $table->string('source', 60)->nullable();
            $table->timestamp('created_at');
        });

        Schema::table('mail_deliveries', function (Blueprint $table): void {
            $table->string('template_key', 60)->nullable()->after('kind');
            $table->unsignedBigInteger('template_version_id')->nullable()->after('template_key');
            $table->string('stream', 16)->nullable()->after('template_version_id');
            $table->unsignedBigInteger('automation_run_id')->nullable()->after('stream');
            $table->unsignedSmallInteger('automation_step')->nullable()->after('automation_run_id');
            $table->unsignedBigInteger('campaign_id')->nullable()->after('automation_step');
            $table->timestamp('delivered_at')->nullable()->after('last_event_at');
            $table->timestamp('clicked_at')->nullable()->after('delivered_at');

            $table->index(['automation_run_id', 'automation_step']);
        });

        Schema::table('users', function (Blueprint $table): void {
            $table->timestamp('last_login_at')->nullable()->after('remember_token');
        });
    }

    public function down(): void
    {
        Schema::table('users', fn (Blueprint $table) => $table->dropColumn('last_login_at'));
        Schema::table('mail_deliveries', fn (Blueprint $table) => $table->dropColumn(['template_key', 'template_version_id', 'stream', 'automation_run_id', 'automation_step', 'campaign_id', 'delivered_at', 'clicked_at']));
        Schema::dropIfExists('mail_suppressions');
        Schema::dropIfExists('mail_consents');
        Schema::dropIfExists('automation_runs');
        Schema::dropIfExists('mail_automations');
        Schema::dropIfExists('mail_campaigns');
        Schema::dropIfExists('mail_template_versions');
        Schema::dropIfExists('mail_templates');
    }
};

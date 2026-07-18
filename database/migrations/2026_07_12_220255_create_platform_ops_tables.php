<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('api_clients', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('client_id')->unique();
            $table->text('client_secret')->nullable();
            $table->string('type')->default('service');
            $table->json('scopes')->nullable();
            $table->unsignedInteger('rate_limit_per_minute')->default(60);
            $table->boolean('is_active')->default(true);
            $table->timestamp('last_used_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('webhooks', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('url');
            $table->text('secret');
            $table->json('events');
            $table->boolean('is_active')->default(true);
            $table->unsignedTinyInteger('max_retries')->default(3);
            $table->unsignedInteger('timeout_seconds')->default(10);
            $table->foreignId('api_client_id')->nullable()->constrained('api_clients')->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('webhook_deliveries', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('webhook_id')->constrained()->cascadeOnDelete();
            $table->string('event');
            $table->string('status')->default('pending')->index();
            $table->unsignedSmallInteger('attempt')->default(1);
            $table->unsignedSmallInteger('response_code')->nullable();
            $table->longText('payload')->nullable();
            $table->longText('response_body')->nullable();
            $table->text('error')->nullable();
            $table->timestamp('delivered_at')->nullable();
            $table->timestamp('next_retry_at')->nullable();
            $table->timestamps();

            $table->index(['webhook_id', 'status']);
        });

        Schema::create('ai_provider_settings', function (Blueprint $table): void {
            $table->id();
            $table->string('provider')->unique();
            $table->string('label');
            $table->boolean('is_enabled')->default(false);
            $table->text('api_key')->nullable();
            $table->string('default_model')->nullable();
            $table->unsignedInteger('monthly_token_limit')->nullable();
            $table->unsignedInteger('monthly_token_usage')->default(0);
            $table->decimal('credits_remaining', 12, 2)->default(0);
            $table->json('config')->nullable();
            $table->timestamps();
        });

        Schema::create('integrations', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('vendor')->nullable();
            $table->text('description')->nullable();
            $table->string('version')->default('1.0.0');
            $table->string('status')->default('pending')->index();
            $table->boolean('is_marketplace')->default(true);
            $table->decimal('price', 10, 2)->default(0);
            $table->json('permissions')->nullable();
            $table->json('config_schema')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('installed_integrations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('integration_id')->constrained()->cascadeOnDelete();
            $table->string('tenant_id')->nullable()->index();
            $table->string('status')->default('pending')->index();
            $table->string('installed_version')->nullable();
            $table->json('configuration')->nullable();
            $table->timestamp('activated_at')->nullable();
            $table->foreignId('installed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->foreign('tenant_id')->references('id')->on('tenants')->nullOnDelete();
            $table->unique(['integration_id', 'tenant_id']);
        });

        Schema::create('themes', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->text('description')->nullable();
            $table->string('version')->default('1.0.0');
            $table->string('status')->default('draft')->index();
            $table->string('preview_url')->nullable();
            $table->decimal('price', 10, 2)->default(0);
            $table->string('author')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('theme_installations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('theme_id')->constrained()->cascadeOnDelete();
            $table->string('tenant_id')->nullable()->index();
            $table->boolean('is_active')->default(false);
            $table->string('installed_version')->nullable();
            $table->timestamp('activated_at')->nullable();
            $table->foreignId('installed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->foreign('tenant_id')->references('id')->on('tenants')->nullOnDelete();
            $table->unique(['theme_id', 'tenant_id']);
        });

        Schema::create('backups', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('type')->index();
            $table->string('status')->default('pending')->index();
            $table->string('disk')->default('local');
            $table->string('path')->nullable();
            $table->unsignedBigInteger('size_bytes')->nullable();
            $table->boolean('is_automatic')->default(false);
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('restored_at')->nullable();
            $table->text('error')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->json('metadata')->nullable();
            $table->timestamps();
        });

        Schema::create('backup_schedules', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('type')->default('full');
            $table->string('cron_expression')->default('0 2 * * *');
            $table->unsignedInteger('retention_days')->default(30);
            $table->boolean('is_active')->default(true);
            $table->timestamp('last_run_at')->nullable();
            $table->timestamp('next_run_at')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
        });

        Schema::create('platform_versions', function (Blueprint $table): void {
            $table->id();
            $table->string('version')->unique();
            $table->string('status')->default('draft')->index();
            $table->text('release_notes')->nullable();
            $table->boolean('is_current')->default(false);
            $table->timestamp('released_at')->nullable();
            $table->timestamp('rolled_back_at')->nullable();
            $table->json('migration_status')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
        });

        if (! Schema::hasTable('failed_jobs')) {
            Schema::create('failed_jobs', function (Blueprint $table): void {
                $table->id();
                $table->string('uuid')->unique();
                $table->text('connection');
                $table->text('queue');
                $table->longText('payload');
                $table->longText('exception');
                $table->timestamp('failed_at')->useCurrent();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('platform_versions');
        Schema::dropIfExists('backup_schedules');
        Schema::dropIfExists('backups');
        Schema::dropIfExists('theme_installations');
        Schema::dropIfExists('themes');
        Schema::dropIfExists('installed_integrations');
        Schema::dropIfExists('integrations');
        Schema::dropIfExists('ai_provider_settings');
        Schema::dropIfExists('webhook_deliveries');
        Schema::dropIfExists('webhooks');
        Schema::dropIfExists('api_clients');
    }
};

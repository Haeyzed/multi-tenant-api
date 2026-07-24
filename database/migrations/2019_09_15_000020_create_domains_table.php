<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateDomainsTable extends Migration
{
    public function up(): void
    {
        Schema::create('domains', function (Blueprint $table): void {
            $table->increments('id');
            $table->string('domain', 255)->unique();
            $table->string('type')->default('subdomain');
            $table->string('status')->default('pending')->index();
            $table->boolean('is_primary')->default(false);
            $table->boolean('is_redirect')->default(false);
            $table->string('redirect_to')->nullable();
            $table->string('dns_verification_token')->nullable();
            $table->timestamp('dns_verified_at')->nullable();
            $table->boolean('ssl_enabled')->default(false);
            $table->string('ssl_status')->nullable();
            $table->timestamp('ssl_expires_at')->nullable();
            $table->boolean('force_https')->default(true);
            $table->string('tenant_id');
            $table->timestamps();

            $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnUpdate()->cascadeOnDelete();
            $table->index(['tenant_id', 'is_primary']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('domains');
    }
}

<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('domains', function (Blueprint $table): void {
            $table->string('type')->default('subdomain')->after('domain');
            $table->string('status')->default('pending')->index()->after('type');
            $table->boolean('is_primary')->default(false)->after('status');
            $table->boolean('is_redirect')->default(false)->after('is_primary');
            $table->string('redirect_to')->nullable()->after('is_redirect');
            $table->string('dns_verification_token')->nullable()->after('redirect_to');
            $table->timestamp('dns_verified_at')->nullable()->after('dns_verification_token');
            $table->boolean('ssl_enabled')->default(false)->after('dns_verified_at');
            $table->string('ssl_status')->nullable()->after('ssl_enabled');
            $table->timestamp('ssl_expires_at')->nullable()->after('ssl_status');
            $table->boolean('force_https')->default(true)->after('ssl_expires_at');
        });
    }

    public function down(): void
    {
        Schema::table('domains', function (Blueprint $table): void {
            $table->dropColumn([
                'type',
                'status',
                'is_primary',
                'is_redirect',
                'redirect_to',
                'dns_verification_token',
                'dns_verified_at',
                'ssl_enabled',
                'ssl_status',
                'ssl_expires_at',
                'force_https',
            ]);
        });
    }
};

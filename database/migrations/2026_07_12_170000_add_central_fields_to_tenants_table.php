<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tenants', function (Blueprint $table): void {
            $table->string('name')->nullable()->after('id');
            $table->string('slug')->nullable()->unique()->after('name');
            $table->string('email')->nullable()->after('slug');
            $table->string('phone')->nullable()->after('email');
            $table->string('status')->default('pending')->index()->after('phone');
            $table->json('tags')->nullable()->after('status');
            $table->json('metadata')->nullable()->after('tags');
            $table->timestamp('trial_ends_at')->nullable()->after('metadata');
            $table->timestamp('suspended_at')->nullable()->after('trial_ends_at');
            $table->string('suspended_reason')->nullable()->after('suspended_at');
            $table->timestamp('archived_at')->nullable()->after('suspended_reason');
            $table->softDeletes()->after('updated_at');
        });
    }

    public function down(): void
    {
        Schema::table('tenants', function (Blueprint $table): void {
            $table->dropColumn([
                'name',
                'slug',
                'email',
                'phone',
                'status',
                'tags',
                'metadata',
                'trial_ends_at',
                'suspended_at',
                'suspended_reason',
                'archived_at',
                'deleted_at',
            ]);
        });
    }
};

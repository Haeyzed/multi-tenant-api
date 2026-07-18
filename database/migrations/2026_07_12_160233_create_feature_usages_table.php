<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('feature_usages', function (Blueprint $table): void {
            $table->id();
            $table->string('tenant_id');
            $table->foreignId('feature_id')->constrained()->cascadeOnDelete();
            $table->foreignId('plan_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedBigInteger('used')->default(0);
            $table->timestamp('period_starts_at')->nullable();
            $table->timestamp('period_ends_at')->nullable();
            $table->timestamps();

            $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
            $table->unique(['tenant_id', 'feature_id', 'period_starts_at'], 'feature_usages_period_unique');
            $table->index(['tenant_id', 'feature_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('feature_usages');
    }
};

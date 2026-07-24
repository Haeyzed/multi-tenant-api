<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('plan_prices', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('plan_id')->constrained('plans')->cascadeOnDelete();
            $table->decimal('amount', 12, 2)->default(0);
            $table->string('currency', 3)->index();
            $table->string('billing_interval')->default('monthly')->index();
            $table->unsignedInteger('trial_days')->nullable();
            $table->string('status')->default('active')->index();
            $table->json('metadata')->nullable();
            $table->json('gateway_identifiers')->nullable();
            $table->timestamps();

            $table->unique(['plan_id', 'currency', 'billing_interval'], 'plan_prices_plan_currency_interval_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('plan_prices');
    }
};

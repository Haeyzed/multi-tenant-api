<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payment_gateways', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('driver')->index();
            $table->unsignedInteger('priority')->default(100)->index();
            $table->boolean('is_active')->default(true)->index();
            $table->boolean('is_fallback')->default(false)->index();
            $table->boolean('supports_subscription')->default(false);
            $table->boolean('supports_refund')->default(false);
            $table->boolean('supports_webhook')->default(false);
            $table->boolean('supports_partial_refund')->default(false);
            $table->json('config')->nullable();
            $table->timestamps();
        });

        Schema::create('payment_gateway_currencies', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('payment_gateway_id')->constrained('payment_gateways')->cascadeOnDelete();
            $table->foreignId('currency_id')->constrained('currencies')->cascadeOnDelete();
            $table->boolean('is_default')->default(false);
            $table->timestamps();

            $table->unique(['payment_gateway_id', 'currency_id'], 'payment_gateway_currency_unique');
            $table->index(['currency_id', 'payment_gateway_id']);
        });

        Schema::create('payment_gateway_countries', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('payment_gateway_id')->constrained('payment_gateways')->cascadeOnDelete();
            $table->foreignId('country_id')->constrained('countries')->cascadeOnDelete();
            $table->unsignedInteger('priority')->default(100);
            $table->timestamps();

            $table->unique(['payment_gateway_id', 'country_id'], 'payment_gateway_country_unique');
            $table->index(['country_id', 'priority']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_gateway_countries');
        Schema::dropIfExists('payment_gateway_currencies');
        Schema::dropIfExists('payment_gateways');
    }
};

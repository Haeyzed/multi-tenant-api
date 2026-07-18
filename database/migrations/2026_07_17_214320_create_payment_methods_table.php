<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payment_methods', function (Blueprint $table): void {
            $table->id();
            $table->string('tenant_id');
            $table->string('gateway');
            $table->string('status')->default('active')->index();
            $table->string('external_id')->nullable()->index();
            $table->string('customer_external_id')->nullable()->index();
            $table->string('authorization_code')->nullable();
            $table->string('brand')->nullable();
            $table->string('last_four', 4)->nullable();
            $table->unsignedTinyInteger('exp_month')->nullable();
            $table->unsignedSmallInteger('exp_year')->nullable();
            $table->boolean('is_default')->default(false);
            $table->json('meta')->nullable();
            $table->timestamps();

            $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
            $table->index(['tenant_id', 'is_default']);
        });

        Schema::table('subscriptions', function (Blueprint $table): void {
            $table->foreignId('default_payment_method_id')
                ->nullable()
                ->after('gateway_subscription_id')
                ->constrained('payment_methods')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('subscriptions', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('default_payment_method_id');
        });

        Schema::dropIfExists('payment_methods');
    }
};

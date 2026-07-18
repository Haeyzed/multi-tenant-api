<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('signup_intents', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('status')->default('pending')->index();
            $table->string('email')->index();
            $table->string('gateway');
            $table->string('currency', 3);
            $table->decimal('verification_amount', 12, 2)->default(0);
            $table->string('gateway_reference')->nullable()->index();
            $table->string('checkout_url')->nullable();
            $table->json('payload');
            $table->json('verification_meta')->nullable();
            $table->string('tenant_id')->nullable()->index();
            $table->timestamp('expires_at')->index();
            $table->timestamp('verified_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('signup_intents');
    }
};

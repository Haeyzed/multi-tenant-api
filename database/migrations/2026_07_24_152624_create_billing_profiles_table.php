<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('billing_profiles', function (Blueprint $table): void {
            $table->id();
            $table->string('tenant_id')->unique();
            $table->string('country_iso2', 2)->nullable()->index();
            $table->string('currency', 3)->nullable()->index();
            $table->string('preferred_gateway')->nullable()->index();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('billing_profiles');
    }
};

<?php

declare(strict_types=1);

namespace Database\Factories\Central;

use App\Enums\Central\ApiKeyType;
use App\Models\Central\ApiClient;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/** @extends Factory<ApiClient> */
class ApiClientFactory extends Factory
{
    protected $model = ApiClient::class;

    public function definition(): array
    {
        return [
            'name' => fake()->company().' API',
            'client_id' => 'cli_'.Str::lower(Str::random(24)),
            'client_secret' => Str::random(40),
            'type' => ApiKeyType::SERVICE,
            'scopes' => ['read', 'write'],
            'rate_limit_per_minute' => 60,
            'is_active' => true,
            'metadata' => [],
        ];
    }
}

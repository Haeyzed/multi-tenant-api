<?php

declare(strict_types=1);

namespace Database\Factories\Central;

use App\Enums\Central\TicketPriority;
use App\Enums\Central\TicketStatus;
use App\Models\Central\Ticket;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/** @extends Factory<Ticket> */
class TicketFactory extends Factory
{
    protected $model = Ticket::class;

    public function definition(): array
    {
        return [
            'number' => 'TCK-'.strtoupper(Str::random(8)),
            'subject' => fake()->sentence(6),
            'description' => fake()->paragraph(),
            'status' => TicketStatus::OPEN,
            'priority' => TicketPriority::MEDIUM,
            'metadata' => [],
        ];
    }
}

<?php

declare(strict_types=1);

namespace Database\Factories\Central;

use App\Models\Central\Ticket;
use App\Models\Central\TicketReply;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<TicketReply> */
class TicketReplyFactory extends Factory
{
    protected $model = TicketReply::class;

    public function definition(): array
    {
        return [
            'ticket_id' => Ticket::factory(),
            'body' => fake()->paragraph(),
            'is_internal' => false,
        ];
    }
}

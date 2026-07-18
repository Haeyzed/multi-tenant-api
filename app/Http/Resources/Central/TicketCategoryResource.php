<?php

declare(strict_types=1);

namespace App\Http\Resources\Central;

use App\Models\Central\TicketCategory;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * API representation of a support ticket category.
 *
 * @mixin TicketCategory
 */
class TicketCategoryResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            /**
             * Ticket category primary key.
             *
             * @var int
             *
             * @example 1
             */
            'id' => $this->id,

            /**
             * Display name of the category.
             *
             * @var string
             *
             * @example Billing
             */
            'name' => $this->name,

            /**
             * URL-safe unique identifier.
             *
             * @var string
             *
             * @example billing
             */
            'slug' => $this->slug,

            /**
             * Category description.
             *
             * @var string|null
             */
            'description' => $this->description,

            /**
             * Whether the category is active.
             *
             * @var bool
             */
            'is_active' => $this->is_active,

            /**
             * Display sort order.
             *
             * @var int
             */
            'sort_order' => $this->sort_order,
        ];
    }
}

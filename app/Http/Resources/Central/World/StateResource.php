<?php

declare(strict_types=1);

namespace App\Http\Resources\Central\World;

use App\Models\World\State;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin State
 */
class StateResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'country_id' => $this->country_id,
            'country_code' => $this->country_code ?? null,
            'state_code' => $this->state_code ?? null,
            'type' => $this->type ?? null,
            'latitude' => $this->latitude ?? null,
            'longitude' => $this->longitude ?? null,
            'country' => $this->whenLoaded('country', fn () => $this->country === null ? null : [
                'id' => $this->country->id,
                'name' => $this->country->name,
                'iso2' => $this->country->iso2,
            ]),
        ];
    }
}

<?php

declare(strict_types=1);

namespace App\Http\Resources\Central\World;

use App\Models\World\Country;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Country
 */
class CountryResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'iso2' => $this->iso2,
            'iso3' => $this->iso3,
            'status' => (int) $this->status,
            'phone_code' => $this->phone_code,
            'native' => $this->native,
            'region' => $this->region,
            'subregion' => $this->subregion,
            'latitude' => $this->latitude,
            'longitude' => $this->longitude,
            'emoji' => $this->emoji,
            'emojiU' => $this->emojiU,
            'currency_code' => $this->whenLoaded('currency', fn () => $this->currency?->code),
            'currency' => $this->whenLoaded('currency', fn () => $this->currency === null ? null : [
                'id' => $this->currency->id,
                'code' => $this->currency->code,
                'name' => $this->currency->name,
                'symbol' => $this->currency->symbol,
            ]),
        ];
    }
}

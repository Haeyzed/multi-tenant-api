<?php

declare(strict_types=1);

namespace App\Http\Resources\Central\World;

use App\Models\World\Currency;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Currency
 */
class CurrencyResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'country_id' => $this->country_id,
            'name' => $this->name,
            'code' => $this->code,
            'precision' => $this->precision,
            'symbol' => $this->symbol,
            'symbol_native' => $this->symbol_native,
            'symbol_first' => $this->symbol_first ?? null,
            'decimal_mark' => $this->decimal_mark ?? null,
            'thousands_separator' => $this->thousands_separator ?? null,
            'country' => $this->whenLoaded('country', fn () => $this->country === null ? null : [
                'id' => $this->country->id,
                'name' => $this->country->name,
                'iso2' => $this->country->iso2,
            ]),
        ];
    }
}

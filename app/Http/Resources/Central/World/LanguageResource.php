<?php

declare(strict_types=1);

namespace App\Http\Resources\Central\World;

use App\Models\World\Language;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Language
 */
class LanguageResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'code' => $this->code,
            'name' => $this->name,
            'name_native' => $this->name_native ?? null,
            'dir' => $this->dir ?? null,
        ];
    }
}

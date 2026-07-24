<?php

declare(strict_types=1);

namespace App\Http\Resources\Tenant;

use App\Models\Tenant\Brand;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Brand
 */
class BrandResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'slug' => $this->slug,
            'description' => $this->description,
            'summary' => $this->summary,
            'is_visible' => $this->is_visible,
            'is_featured' => $this->is_featured,
            'logo' => null,
            'banner' => null,
            'meta_title' => $this->meta_title,
            'meta_description' => $this->meta_description,
            'website_url' => $this->website_url,
            'country_of_origin' => $this->country_of_origin,
            'sort_order' => $this->sort_order,
            'products_count' => 0,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}

<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CategoryResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'business' => $this->business->name,
            'slug' => $this->slug,
            'children' => $this->children,
            'image_url' => $this->image_url,
            'parent' => $this->parent,
            'products' => ProductResource::collection($this->products),
        ];
    }
}

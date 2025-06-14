<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ProductResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $formatted_buying_price = $this->business->currency . ' ' . number_format($this->buying_price, 2);
        $formatted_selling_price = $this->business->currency . ' ' . number_format($this->selling_price, 2);

        return [
            'id' => $this->id,
            'name' => $this->name,
            'slug' => $this->slug,
            'formatted_buying_price' => $formatted_buying_price,
            'formatted_selling_price' => $formatted_selling_price,
            'buying_price' => $this->buying_price,
            'selling_price' => $this->selling_price,
            'stock_quantity' => $this->stock_quantity,
            'description' => $this->description,
            'published' => $this->published,
            'image_url' => $this->image_url,
            'category' => $this->category->name,
        ];
    }
}

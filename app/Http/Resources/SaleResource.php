<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SaleResource extends JsonResource
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
            'reference' => $this->reference,
            'customer' => $this->customer->first_name . ' ' . $this->customer->last_name,
            'total_amount' => $this->total_amount,
            'items' => SaleItemResource::collection($this->saleItems),
        ];
    }
}

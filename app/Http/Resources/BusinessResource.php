<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class BusinessResource extends JsonResource
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
            'slug' => $this->slug,
            'category' => $this->category,
            'phone' => $this->phone,
            'logo_url' => $this->logo_url,
            'email' => $this->email,
            'description' => $this->description,
            'address' => $this->address,
            'country' => $this->country,
            'website' => $this->website,
            'tagline' => $this->tagline,
            'user' => $this->user,
        ];
    }
}

<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class ProductResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id'       => $this->id,
            'name'     => $this->name,
            'price'    => $this->price,
            'stock'    => $this->stock,
            'category' => $this->whenLoaded('category', fn() => $this->category?->name),
        ];
    }
}
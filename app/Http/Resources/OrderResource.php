<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class OrderResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id'          => $this->id,
            'customer'    => $this->whenLoaded('customer', fn() => $this->customer?->name),
            'status'      => $this->status,
            'total'       => $this->total_amount,
            'items_count' => $this->whenLoaded('items', fn() => $this->items?->count()),
            'items'       => $this->whenLoaded('items', fn() => $this->items->map(fn($item) => [
                'product_name' => $item->product?->name,
                'quantity'     => $item->quantity,
                'unit_price'   => $item->unit_price,
                'total_price'  => $item->quantity * $item->unit_price,
            ])),
            'created_at'  => $this->created_at->toDateTimeString(),
        ];
    }
}
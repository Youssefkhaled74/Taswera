<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class OrderResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'status' => $this->status,
            'total_price' => $this->total_price,
            'barcode_prefix' => $this->barcode_prefix,
            'phone_number' => $this->phone_number,
            'photos_count' => $this->whenCounted('orderItems'),
            'type' => $this->type,
            'items' => OrderItemResource::collection($this->whenLoaded('orderItems')),
            'user' => $this->whenLoaded('user') ? [
                'id' => $this->user->id,
                'phone_number' => $this->user->phone_number,
                'barcode' => $this->user->barcode,
            ] : null,
            'shift' => $this->shift ? new ShiftResource($this->shift) : null,
            'pay_amount' => $this->whenLoaded('pay_amount') ? $this->pay_amount : null,
            'branch' => $this->whenLoaded('branch') ? new BranchResource($this->branch) : null,
            'employee' => $this->whenLoaded('employee') ? new StaffResource($this->employee) : null,
        ];
    }
}


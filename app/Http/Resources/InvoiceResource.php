<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class InvoiceResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'barcode_prefix' => $this->barcode_prefix,
            'num_photos' => $this->num_photos,
            'amount' => number_format($this->amount, 2) . ' EGP',
            'tax_rate' => ($this->tax_rate * 100) . '%',
            'tax_amount' => number_format($this->tax_amount, 2) . ' EGP',
            'total_amount' => number_format($this->total_amount, 2) . ' EGP',
            'invoice_method' => $this->invoice_method,
            'status' => $this->status,
            'created_at' => $this->created_at,
            'user' => new UserResource($this->whenLoaded('user')),
            'branch' => new BranchResource($this->whenLoaded('branch')),
            'staff' => new StaffResource($this->whenLoaded('staff')),
        ];
    }
} 
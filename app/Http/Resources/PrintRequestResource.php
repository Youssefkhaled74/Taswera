<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PrintRequestResource extends JsonResource
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
            'user' => new UserResource($this->whenLoaded('user')),
            'branch' => new BranchResource($this->whenLoaded('branch')),
            'package' => new PackageResource($this->whenLoaded('package')),
            'photos' => PhotoResource::collection($this->whenLoaded('photos')),
            'barcode_prefix' => $this->barcode_prefix,
            'status' => $this->status,
            'payment_method' => $this->payment_method,
            'total_amount' => $this->total_amount,
            'is_paid' => $this->is_paid,
            'metadata' => $this->metadata,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
            // Add pivot data when photos are loaded
            'photos_details' => $this->when($this->relationLoaded('photos'), function () {
                return $this->photos->map(function ($photo) {
                    return [
                        'photo_id' => $photo->id,
                        'quantity' => $photo->pivot->quantity,
                        'unit_price' => $photo->pivot->unit_price,
                    ];
                });
            }),
        ];
    }
} 
<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PhotoResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        // Calculate invoice data
        $numPhotos = 1; // Each photo resource represents 1 photo
        $amount = "10.00 EGP"; // Static price per photo
        $taxRate = "5%";
        $taxAmount = "0.50 EGP"; // 5% of 10.00 EGP
        $totalAmount = "10.50 EGP"; // Amount + tax

        return [
            'id' => $this->id,
            'file_path' => env('APP_URL') . $this->file_path,
            'status' => $this->status,
            'taken_by' => new StaffResource($this->whenLoaded('staff')),
            'branch' => new BranchResource($this->whenLoaded('branch')),
            'metadata' => $this->metadata,
            'sync_status' => $this->sync_status,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
} 
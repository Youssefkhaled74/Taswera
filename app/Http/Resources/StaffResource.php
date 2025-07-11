<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\DB;

class StaffResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        // Get unique customer count by counting distinct barcodes from photos
        $customerCount = $this->uploadedPhotos()
            ->select(DB::raw('SUBSTRING_INDEX(file_path, "_", 1) as barcode'))
            ->distinct()
            ->count();

        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'branch_id' => $this->branch_id,
            'role' => $this->role,
            'branch' => new BranchResource($this->whenLoaded('branch')),
            'stats' => [
                'total_photos' => $this->uploadedPhotos()->count(),
                'total_customers' => $customerCount,
            ],
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
} 
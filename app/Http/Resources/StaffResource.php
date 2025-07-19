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
        $customerCount = $this->when(
            $this->role === 'photographer',
            fn() => $this->uploadedPhotos()
                ->select(DB::raw('SUBSTRING_INDEX(file_path, "_", 1) as barcode'))
                ->distinct()
                ->count()
        );

        $data = [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'phone' => $this->phone,
            'branch_id' => $this->branch_id,
            'role' => $this->role,
            'status' => $this->status,
            'branch' => new BranchResource($this->whenLoaded('branch')),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
            'deleted_at' => $this->deleted_at,
        ];

        if ($this->role === 'photographer') {
            $data['stats'] = [
                'total_photos' => $this->uploadedPhotos()->count(),
                'total_customers' => $customerCount,
            ];
        }

        return $data;
    }
} 
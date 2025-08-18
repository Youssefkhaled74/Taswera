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
                ->selectRaw('SUBSTRING_INDEX(file_path, "_", 1) as barcode')
                ->distinct()
                ->count('barcode_prefix')
        );

        $data = [
            'id' => $this->id ?? 'N/A',
            'name' => $this->name ?? 'N/A',
            'email' => $this->email ?? 'N/A',
            'phone' => $this->phone ?? 'N/A',
            'branch_id' => $this->branch_id ?? 'N/A',
            'role' => $this->role ?? 'N/A',
            'status' => $this->status ?? 'N/A',
            'branch' => new BranchResource($this->whenLoaded('branch')) ?? 'N/A',
            'created_at' => $this->created_at ??  'N/A',
            'updated_at' => $this->updated_at ?? 'N/A',
            'deleted_at' => $this->deleted_at ?? 'N/A',
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
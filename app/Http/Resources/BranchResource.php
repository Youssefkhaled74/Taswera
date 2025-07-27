<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class BranchResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id ?? 'N/A',
            'name' => $this->name ?? 'N/A',
            'address' => $this->address ?? 'N/A',
            'phone' => $this->phone ?? 'N/A',
            'email' => $this->email ?? 'N/A',
            'is_active' => $this->is_active ?? 'N/A',
            'location' => $this->location ?? 'N/A',
            'created_at' => $this->created_at ?? 'N/A',
            'updated_at' => $this->updated_at ?? 'N/A',
            'deleted_at' => $this->deleted_at ?? 'N/A',
            // 'No.of_Employees' => $this->whenLoaded('staff', function () {
            //     return $this->staff->count() ?? 'N/A';
            // }),
            // 'No.of_photographers' => $this->whenLoaded('photographers', function () {
            //     return $this->staff->where('role', 'photographer')->count() ?? 'N/A';
            // }),
        ];
    }
} 
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
        
        return [
            'id' => $this->id ?? 'N/A',
            'file_path' => env('APP_URL') . $this->file_path ?? 'N/A',
            'status' => $this->status ?? 'N/A',
            'taken_by' => new StaffResource($this->whenLoaded('staff')) ?? 'N/A',
            'branch' => new BranchResource($this->whenLoaded('branch')) ?? 'N/A',
            'quantity' => isset($this->pivot) && isset($this->pivot->quantity) ? (int) $this->pivot->quantity : 1,
            'unit_price' => isset($this->pivot) && isset($this->pivot->unit_price) ? (float) $this->pivot->unit_price : null,
            'metadata' => $this->metadata ?? 'N/A',
            'sync_status' => $this->sync_status ?? 'N/A',
            'created_at' => $this->created_at ?? 'N/A',
            'updated_at' => $this->updated_at ?? 'N/A',
        ];
    }
} 
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
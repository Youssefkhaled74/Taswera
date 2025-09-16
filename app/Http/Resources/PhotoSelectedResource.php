<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PhotoSelectedResource extends JsonResource
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
            'original_photo_id' => $this->original_photo_id,
            'quantity' => (int) ($this->quantity ?? 1),
            'barcode_prefix' => $this->barcode_prefix,
            'file_path' => env('APP_URL') . $this->file_path,
            'original_filename' => $this->original_filename,
            'uploaded_by' => $this->uploaded_by,
            'branch_id' => $this->branch_id,
            'is_edited' => (bool) ($this->is_edited ?? false),
            'thumbnail_path' => $this->thumbnail_path,
            'type' => $this->type,
            'status' => $this->status,
            'sync_status' => $this->sync_status,
            'metadata' => $this->metadata,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}


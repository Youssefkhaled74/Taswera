<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class BranchManagerResource extends JsonResource
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
            'email' => $this->email ?? 'N/A',
            'phone' => $this->phone ?? 'N/A',
            'branch' => new BranchResource($this->branch) ?? 'N/A',
            'created_at' => $this->created_at ?? 'N/A',
            'updated_at' => $this->updated_at ?? 'N/A',
        ];
    }
} 
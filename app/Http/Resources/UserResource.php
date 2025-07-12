<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UserResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'barcode' => $this->barcode,
            'phone_number' => $this->phone_number,
            'branch_id' => $this->branch_id,
            'last_visit' => $this->last_visit,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
} 
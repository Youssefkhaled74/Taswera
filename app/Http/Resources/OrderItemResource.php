<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class OrderItemResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $selected = $this->whenLoaded('selected');
        return [
            'id' => $this->id,
            'selected_photo_id' => $this->selected_photo_id,
            'original_photo_id' => $this->original_photo_id,
            'edited_photo_path' => $this->edited_photo_path,
            'employee_id' => $selected ? $selected->uploaded_by : null,
            'frame' => $this->frame,
            'filter' => $this->filter,
            'selected_photo' => $selected ? new PhotoSelectedResource($selected) : null,
        ];
    }
}


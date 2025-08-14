<?php
namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class ShiftResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return array
     */
    public function toArray($request)
    {
        return [
            'id' => $this->id,
            'branch_id' => $this->branch_id,
            'name' => $this->name,
            'from' => $this->from,
            'to' => $this->to,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
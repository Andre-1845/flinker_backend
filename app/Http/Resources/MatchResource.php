<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MatchResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'flink_id' => $this->flink_id,
            'professional_id' => $this->professional_id,
            'status' => $this->status->value,
            'status_label' => $this->status->label(),
            'checked_in_at' => $this->checked_in_at,
            'flink' => new FlinkResource($this->whenLoaded('flink')),
            'professional' => new ProfessionalResource($this->whenLoaded('professional')),
            'created_at' => $this->created_at,
        ];
    }
}

<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class FlinkResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'company_id' => $this->company_id,
            'activity_type' => $this->activity_type,
            'location' => $this->location,
            'latitude' => (float) $this->latitude,
            'longitude' => (float) $this->longitude,
            'start_date_time' => $this->start_date_time,
            'end_date_time' => $this->end_date_time,
            'requirements' => $this->requirements,
            'status' => $this->status->value,
            'status_label' => $this->status->label(),
            'pricing' => [
                'net_value' => (float) $this->net_value,
                'platform_margin' => (float) $this->platform_margin,
                'total_value' => (float) $this->total_value,
            ],
            'distance_km' => $this->when(isset($this->distance_km), fn () => round((float) $this->distance_km, 2)),
            'company' => new CompanyResource($this->whenLoaded('company')),
            'created_at' => $this->created_at,
        ];
    }
}

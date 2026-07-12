<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ScheduleBlockResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'flink_id' => $this->flink_id,
            'start_date_time' => $this->start_date_time,
            'end_date_time' => $this->end_date_time,
            'reason' => $this->reason,
        ];
    }
}

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
            'name' => $this->name,
            'email' => $this->email,
            'profile' => $this->profile->value,
            'is_active' => $this->is_active,
            'professional' => new ProfessionalResource($this->whenLoaded('professional')),
            'company' => new CompanyResource($this->whenLoaded('company')),
            'created_at' => $this->created_at,
        ];
    }
}

<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MessageResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'match_id' => $this->match_id,
            'sender_user_id' => $this->sender_user_id,
            'is_mine' => $this->sender_user_id === $request->user()?->id,
            'body' => $this->body,
            'read_at' => $this->read_at,
            'created_at' => $this->created_at,
        ];
    }
}

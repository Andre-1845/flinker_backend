<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class RatingResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'match_id' => $this->match_id,
            'rater_name' => $this->whenLoaded('rater', fn () => $this->rater->name),
            'stars' => $this->stars,
            'comment' => $this->comment,
            'is_hidden' => $this->is_hidden,
            // Só quem foi avaliado pode alternar a visibilidade (ver
            // RatingController::toggleVisibility) — o frontend usa isto pra
            // decidir se mostra o botão de ocultar/exibir naquele review.
            'can_toggle_visibility' => $request->user()?->id === $this->rated_user_id,
            'created_at' => $this->created_at,
        ];
    }
}

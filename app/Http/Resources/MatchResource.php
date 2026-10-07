<?php

namespace App\Http\Resources;

use App\Domain\Rating\Models\Rating;
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
            'professional_confirmed_at' => $this->professional_confirmed_at,
            'company_confirmed_at' => $this->company_confirmed_at,
            'flink' => new FlinkResource($this->whenLoaded('flink')),
            'professional' => new ProfessionalResource($this->whenLoaded('professional')),
            // Achado de perfil mockado (02/10/2026): diz pro frontend se quem
            // está vendo já avaliou este flink, pra decidir entre mostrar o
            // formulário de avaliação ou "você avaliou X★". Uma query extra
            // por match na listagem — aceitável pro volume atual (ver
            // ChatContentFilter/PruneExpiredChats pra outros exemplos do
            // mesmo tipo de trade-off pragmático neste projeto).
            'my_rating' => $this->when($request->user(), function () use ($request) {
                $rating = Rating::query()
                    ->where('match_id', $this->id)
                    ->where('rater_user_id', $request->user()->id)
                    ->first();

                return $rating ? ['stars' => $rating->stars, 'comment' => $rating->comment] : null;
            }),
            'created_at' => $this->created_at,
        ];
    }
}

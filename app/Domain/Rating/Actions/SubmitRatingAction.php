<?php

namespace App\Domain\Rating\Actions;

use App\Domain\Match\Models\FlinkMatch;
use App\Domain\Rating\Models\Rating;
use App\Models\User;
use Illuminate\Validation\ValidationException;

/**
 * Cria a avaliação de um lado do match sobre o outro e recalcula a média de
 * reputação de quem foi avaliado (achado de perfil mockado, 02/10/2026). A
 * coluna `reputation` em professionals/companies já existia desde o início
 * do projeto (com filtro `min_reputation` pronto), só nunca era preenchida.
 */
class SubmitRatingAction
{
    public function handle(FlinkMatch $match, User $rater, int $stars, ?string $comment): Rating
    {
        $ratedUserId = $this->resolveRatedUserId($match, $rater);

        if (
            Rating::query()
                ->where('match_id', $match->id)
                ->where('rater_user_id', $rater->id)
                ->exists()
        ) {
            throw ValidationException::withMessages([
                'stars' => 'Você já avaliou este flink.',
            ]);
        }

        $rating = Rating::create([
            'match_id' => $match->id,
            'rater_user_id' => $rater->id,
            'rated_user_id' => $ratedUserId,
            'stars' => $stars,
            'comment' => $comment,
        ]);

        $this->recomputeReputation($ratedUserId);

        return $rating;
    }

    private function resolveRatedUserId(FlinkMatch $match, User $rater): int
    {
        if ($rater->isProfessional()) {
            return $match->flink->company->user_id;
        }

        if ($rater->isCompany()) {
            return $match->professional->user_id;
        }

        abort(403, 'Apenas profissionais e empresas podem avaliar.');
    }

    /**
     * Média sempre considera TODAS as avaliações recebidas, inclusive as que
     * a pessoa avaliada ocultou do próprio mural público (`is_hidden`) — ver
     * migration de ratings. Ocultar não pode ser usado pra esconder nota
     * ruim e inflar a média exibida.
     */
    private function recomputeReputation(int $ratedUserId): void
    {
        $user = User::find($ratedUserId);

        if (! $user) {
            return;
        }

        $average = (float) Rating::query()->where('rated_user_id', $ratedUserId)->avg('stars');

        if ($user->professional) {
            $user->professional->update(['reputation' => round($average, 2)]);
        } elseif ($user->company) {
            $user->company->update(['reputation' => round($average, 2)]);
        }
    }
}

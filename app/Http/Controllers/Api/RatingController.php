<?php

namespace App\Http\Controllers\Api;

use App\Domain\Flink\Enums\FlinkStatus;
use App\Domain\Match\Enums\MatchStatus;
use App\Domain\Match\Models\FlinkMatch;
use App\Domain\Rating\Actions\SubmitRatingAction;
use App\Domain\Rating\Models\Rating;
use App\Http\Controllers\Controller;
use App\Http\Resources\RatingResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Avaliações bilaterais de um flink concluído (achado de perfil mockado,
 * 02/10/2026). Liberado só depois que o match foi confirmado E o flink foi
 * executado (status completed) — antes disso não há o que avaliar.
 */
class RatingController extends Controller
{
    public function store(Request $request, FlinkMatch $match, SubmitRatingAction $action): JsonResponse
    {
        $this->authorizeParticipant($request, $match);
        $this->ensureRatingUnlocked($match);

        $data = $request->validate([
            'stars' => ['required', 'integer', 'min:1', 'max:5'],
            'comment' => ['nullable', 'string', 'max:1000'],
        ]);

        $rating = $action->handle($match, $request->user(), $data['stars'], $data['comment'] ?? null);

        return response()->json(['data' => new RatingResource($rating)], 201);
    }

    public function toggleVisibility(Request $request, Rating $rating): JsonResponse
    {
        abort_unless(
            $request->user()->isAdmin() || $request->user()->id === $rating->rated_user_id,
            403,
            'Só quem foi avaliado pode ocultar ou reexibir esta avaliação.'
        );

        $data = $request->validate([
            'is_hidden' => ['required', 'boolean'],
        ]);

        $rating->update($data);

        return response()->json(['data' => new RatingResource($rating)]);
    }

    private function authorizeParticipant(Request $request, FlinkMatch $match): void
    {
        $user = $request->user();

        abort_unless(
            $user->isAdmin()
                || ($user->isProfessional() && $user->professional?->id === $match->professional_id)
                || ($user->isCompany() && $user->company?->id === $match->flink->company_id),
            403,
            'Você não tem permissão para avaliar este flink.'
        );
    }

    private function ensureRatingUnlocked(FlinkMatch $match): void
    {
        abort_unless(
            $match->status === MatchStatus::Confirmed && $match->flink->status === FlinkStatus::Completed,
            403,
            'Só é possível avaliar depois que o serviço for concluído pelos dois lados.'
        );
    }
}

<?php

namespace App\Http\Controllers\Api;

use App\Domain\Flink\Models\Flink;
use App\Domain\Match\Actions\AcceptMatchAction;
use App\Domain\Match\Actions\CancelMatchAction;
use App\Domain\Match\Actions\CheckInAction;
use App\Domain\Match\Actions\ConfirmMatchAction;
use App\Domain\Match\Actions\ExpressInterestAction;
use App\Domain\Match\Models\FlinkMatch;
use App\Http\Controllers\Controller;
use App\Http\Requests\Match\CheckInRequest;
use App\Http\Requests\Match\StoreMatchRequest;
use App\Http\Resources\MatchResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MatchController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        $query = FlinkMatch::query()->with(['flink', 'professional']);

        if ($user->isProfessional()) {
            $query->where('professional_id', $user->professional->id);
        } elseif ($user->isCompany()) {
            $query->whereHas('flink', fn ($q) => $q->where('company_id', $user->company->id));
        }
        // Admin vê todos, sem filtro adicional.

        $matches = $query->latest()->paginate($request->integer('per_page', 15));

        return response()->json(MatchResource::collection($matches)->response()->getData(true));
    }

    public function store(StoreMatchRequest $request, ExpressInterestAction $action): JsonResponse
    {
        $flink = Flink::findOrFail($request->validated('flink_id'));
        $professional = $request->user()->professional;

        abort_unless($professional, 422, 'Complete o cadastro de profissional antes de demonstrar interesse.');

        $match = $action->handle($flink, $professional);

        return response()->json(['data' => new MatchResource($match->load(['flink', 'professional']))], 201);
    }

    public function accept(Request $request, FlinkMatch $match, AcceptMatchAction $action): JsonResponse
    {
        $this->authorizeCompanyOwnsFlink($request, $match);

        $match = $action->handle($match);

        return response()->json(['data' => new MatchResource($match)]);
    }

    public function confirm(Request $request, FlinkMatch $match, ConfirmMatchAction $action): JsonResponse
    {
        $this->authorizeProfessionalOwnsMatch($request, $match);

        $match = $action->handle($match);

        return response()->json(['data' => new MatchResource($match)]);
    }

    public function checkin(CheckInRequest $request, FlinkMatch $match, CheckInAction $action): JsonResponse
    {
        $this->authorizeProfessionalOwnsMatch($request, $match);

        $match = $action->handle($match, (float) $request->validated('latitude'), (float) $request->validated('longitude'));

        return response()->json(['data' => new MatchResource($match)]);
    }

    public function cancel(Request $request, FlinkMatch $match, CancelMatchAction $action): JsonResponse
    {
        $user = $request->user();
        abort_unless(
            $user->isAdmin()
                || ($user->isProfessional() && $user->professional?->id === $match->professional_id)
                || ($user->isCompany() && $user->company?->id === $match->flink->company_id),
            403,
            'Você não tem permissão para cancelar este match.'
        );

        $match = $action->handle($match);

        return response()->json(['data' => new MatchResource($match)]);
    }

    private function authorizeCompanyOwnsFlink(Request $request, FlinkMatch $match): void
    {
        $user = $request->user();

        abort_unless(
            $user->isAdmin() || ($user->isCompany() && $user->company?->id === $match->flink->company_id),
            403,
            'Você não tem permissão para gerenciar este match.'
        );
    }

    private function authorizeProfessionalOwnsMatch(Request $request, FlinkMatch $match): void
    {
        $user = $request->user();

        abort_unless(
            $user->isAdmin() || ($user->isProfessional() && $user->professional?->id === $match->professional_id),
            403,
            'Você não tem permissão para gerenciar este match.'
        );
    }
}

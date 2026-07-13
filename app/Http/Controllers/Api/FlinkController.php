<?php

namespace App\Http\Controllers\Api;

use App\Domain\Company\Models\Company;
use App\Domain\Flink\Actions\CompleteFlinkAction;
use App\Domain\Flink\Actions\CreateFlinkAction;
use App\Domain\Flink\Actions\UpdateFlinkAction;
use App\Domain\Flink\Enums\FlinkStatus;
use App\Domain\Flink\Models\Flink;
use App\Domain\Wallet\Actions\RefundFlinkReservationAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Flink\StoreFlinkRequest;
use App\Http\Requests\Flink\UpdateFlinkRequest;
use App\Http\Resources\FlinkResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class FlinkController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = Flink::query()->with('company');

        if ($request->filled(['latitude', 'longitude'])) {
            $query->near(
                (float) $request->query('latitude'),
                (float) $request->query('longitude'),
                (float) $request->query('radius_km', 20)
            );
        }

        $flinks = $query->paginate($request->integer('per_page', 15));

        return response()->json(FlinkResource::collection($flinks)->response()->getData(true));
    }

    /**
     * Flinks abertos, disponíveis para profissionais darem interesse (Fase 3).
     */
    public function active(Request $request): JsonResponse
    {
        $query = Flink::active()->with('company');

        if ($request->filled(['latitude', 'longitude'])) {
            $query->near(
                (float) $request->query('latitude'),
                (float) $request->query('longitude'),
                (float) $request->query('radius_km', 20)
            );
        }

        $flinks = $query->paginate($request->integer('per_page', 15));

        return response()->json(FlinkResource::collection($flinks)->response()->getData(true));
    }

    public function byCompany(Company $company): JsonResponse
    {
        $flinks = $company->flinks()->with('company')->latest()->paginate(15);

        return response()->json(FlinkResource::collection($flinks)->response()->getData(true));
    }

    public function show(Flink $flink): JsonResponse
    {
        return response()->json(['data' => new FlinkResource($flink->load('company'))]);
    }

    public function store(StoreFlinkRequest $request, CreateFlinkAction $action): JsonResponse
    {
        $company = $request->user()->company;

        abort_unless($company, 422, 'Complete o cadastro da empresa antes de publicar um Flink.');

        $flink = $action->handle($company, $request->validated());

        return response()->json(['data' => new FlinkResource($flink->load('company'))], 201);
    }

    public function update(UpdateFlinkRequest $request, Flink $flink, UpdateFlinkAction $action, RefundFlinkReservationAction $refundAction): JsonResponse
    {
        $this->authorizeOwnership($request, $flink);

        abort_unless($flink->status->isEditable(), 422, 'Este Flink não pode mais ser editado no estado atual.');

        $willCancel = ($request->validated('status') ?? null) === FlinkStatus::Cancelled->value;

        $flink = $action->handle($flink, $request->validated());

        if ($willCancel) {
            $refundAction->handle($flink);
        }

        return response()->json(['data' => new FlinkResource($flink->load('company'))]);
    }

    /**
     * Empresa confirma que o serviço foi executado — dispara o split de pagamento
     * (profissional recebe o valor líquido, margem fica registrada pra plataforma).
     */
    public function complete(Request $request, Flink $flink, CompleteFlinkAction $action): JsonResponse
    {
        $this->authorizeOwnership($request, $flink);

        $flink = $action->handle($flink);

        return response()->json(['data' => new FlinkResource($flink->load('company'))]);
    }

    public function destroy(Request $request, Flink $flink, RefundFlinkReservationAction $refundAction): JsonResponse
    {
        $this->authorizeOwnership($request, $flink);

        abort_unless($flink->status->isEditable(), 422, 'Este Flink não pode mais ser removido no estado atual.');

        $refundAction->handle($flink);
        $flink->delete();

        return response()->json(['message' => 'Flink removido com sucesso.']);
    }

    private function authorizeOwnership(Request $request, Flink $flink): void
    {
        $user = $request->user();

        abort_unless(
            $user->isAdmin() || ($user->isCompany() && $user->company?->id === $flink->company_id),
            403,
            'Você não tem permissão para alterar este Flink.'
        );
    }
}

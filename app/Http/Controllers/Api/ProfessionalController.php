<?php

namespace App\Http\Controllers\Api;

use App\Domain\Professional\Models\Professional;
use App\Http\Controllers\Controller;
use App\Http\Resources\ProfessionalResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProfessionalController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $professionals = Professional::query()
            ->when($request->query('min_reputation'), fn ($query, $value) => $query->where('reputation', '>=', $value))
            ->paginate($request->integer('per_page', 15));

        return response()->json(ProfessionalResource::collection($professionals)->response()->getData(true));
    }

    public function show(Professional $professional): JsonResponse
    {
        return response()->json(['data' => new ProfessionalResource($professional)]);
    }

    public function update(Request $request, Professional $professional): JsonResponse
    {
        $this->authorizeOwnership($request, $professional);

        $validated = $request->validate([
            'phone' => ['sometimes', 'string', 'max:20'],
            'address' => ['nullable', 'string', 'max:255'],
            'pix_key' => ['nullable', 'string', 'max:255'],
            'photo_url' => ['nullable', 'string', 'max:255'],
            'is_mei' => ['sometimes', 'boolean'],
            'cnpj' => ['nullable', 'string', 'max:20'],
        ]);

        $professional->update($validated);

        return response()->json(['data' => new ProfessionalResource($professional->fresh())]);
    }

    private function authorizeOwnership(Request $request, Professional $professional): void
    {
        abort_unless(
            $request->user()->isAdmin() || $request->user()->id === $professional->user_id,
            403,
            'Você não tem permissão para editar este perfil.'
        );
    }
}

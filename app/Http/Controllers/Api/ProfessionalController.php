<?php

namespace App\Http\Controllers\Api;

use App\Domain\Professional\Models\Professional;
use App\Http\Controllers\Controller;
use App\Http\Resources\ProfessionalResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

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

    /**
     * Upload da foto de perfil (achado da auditoria 2026-10-01: a tela de perfil já
     * tinha o botão de câmera, mas ele só gerava uma prévia local em memória — nunca
     * persistia nada, nem no backend nem entre sessões). Recebe o arquivo já recortado
     * pelo Cropper.js no frontend, então salva como veio, sem reprocessar.
     */
    public function uploadPhoto(Request $request, Professional $professional): JsonResponse
    {
        $this->authorizeOwnership($request, $professional);

        $request->validate([
            'photo' => ['required', 'image', 'max:5120'], // 5MB
        ]);

        // Apaga a foto antiga pra não acumular lixo no disco 'public'.
        if ($professional->photo_url) {
            $oldPath = Str::after($professional->photo_url, '/storage/');
            if ($oldPath !== $professional->photo_url) {
                Storage::disk('public')->delete($oldPath);
            }
        }

        $path = $request->file('photo')->store('professionals/' . $professional->id, 'public');

        $professional->update(['photo_url' => Storage::disk('public')->url($path)]);

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

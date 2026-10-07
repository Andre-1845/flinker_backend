<?php

namespace App\Http\Controllers\Api;

use App\Domain\Flink\Enums\FlinkStatus;
use App\Domain\Match\Enums\MatchStatus;
use App\Domain\Match\Models\FlinkMatch;
use App\Domain\Professional\Models\Professional;
use App\Domain\Rating\Models\Rating;
use App\Domain\Rating\Services\ReputationCalculator;
use App\Http\Controllers\Controller;
use App\Http\Resources\ProfessionalResource;
use App\Http\Resources\RatingResource;
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

    /**
     * Achado de segurança (02/10/2026, encontrado construindo o perfil público
     * de verdade): esta rota só exigia estar logado, sem checar dono — qualquer
     * profissional ou empresa autenticado conseguia buscar o CPF/telefone/
     * endereço/chave Pix de qualquer outro profissional só trocando o id na
     * URL. Agora só o próprio dono (ou admin) recebe os dados completos;
     * qualquer outra pessoa logada recebe o mesmo payload público e seguro
     * de publicProfile() (sem CPF/telefone/endereço/Pix).
     */
    public function show(Request $request, Professional $professional): JsonResponse
    {
        if ($this->isOwnerOrAdmin($request, $professional)) {
            return response()->json(['data' => new ProfessionalResource($professional->loadMissing('user'))]);
        }

        return $this->publicProfile($request, $professional);
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

    /**
     * Perfil público (achado de perfil mockado, 02/10/2026): substitui o
     * WorkerPublicProfile.tsx que mostrava sempre os mesmos dados fixos de
     * "Carlos Silva" pra qualquer profissional, e alimenta as próprias telas
     * de Profile.tsx com números reais (localização, flinks concluídos,
     * reputação, % de aprovação, medalha) em vez dos hardcoded que tinham
     * antes. Não expõe CPF/telefone/Pix — só dados que fazem sentido mostrar
     * pra qualquer pessoa na plataforma.
     */
    public function publicProfile(Request $request, Professional $professional): JsonResponse
    {
        $professional->loadMissing('user');

        $ratings = Rating::query()
            ->where('rated_user_id', $professional->user_id)
            ->with('rater')
            ->latest()
            ->get();

        $ratingsCount = $ratings->count();
        $approvedCount = $ratings->where('stars', '>=', 4)->count();

        $flinksCompleted = FlinkMatch::query()
            ->where('professional_id', $professional->id)
            ->where('status', MatchStatus::Confirmed)
            ->whereHas('flink', fn ($query) => $query->where('status', FlinkStatus::Completed))
            ->count();

        $calculator = new ReputationCalculator();

        // O próprio dono (ou admin) vê também as avaliações que ocultou do
        // mural público — precisa disso pra poder reexibi-las depois.
        // Qualquer outra pessoa só vê as não ocultadas.
        $visibleReviews = $this->isOwnerOrAdmin($request, $professional)
            ? $ratings
            : $ratings->where('is_hidden', false)->values();

        return response()->json(['data' => [
            'id' => $professional->id,
            'name' => $professional->user->name,
            'address' => $professional->address,
            'photo_url' => $professional->photo_url,
            'reputation' => (float) $professional->reputation,
            'ratings_count' => $ratingsCount,
            'approval_rate' => $calculator->approvalRate($approvedCount, $ratingsCount),
            'medal' => $calculator->medal((float) $professional->reputation, $ratingsCount),
            'flinks_completed' => $flinksCompleted,
            'reviews' => RatingResource::collection($visibleReviews),
        ]]);
    }

    private function isOwnerOrAdmin(Request $request, Professional $professional): bool
    {
        return $request->user()->isAdmin() || $request->user()->id === $professional->user_id;
    }

    private function authorizeOwnership(Request $request, Professional $professional): void
    {
        abort_unless(
            $this->isOwnerOrAdmin($request, $professional),
            403,
            'Você não tem permissão para editar este perfil.'
        );
    }
}

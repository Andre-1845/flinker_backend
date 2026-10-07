<?php

namespace App\Http\Controllers\Api;

use App\Domain\Company\Models\Company;
use App\Domain\Flink\Enums\FlinkStatus;
use App\Domain\Flink\Models\Flink;
use App\Domain\Rating\Models\Rating;
use App\Domain\Rating\Services\ReputationCalculator;
use App\Http\Controllers\Controller;
use App\Http\Resources\CompanyResource;
use App\Http\Resources\RatingResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class CompanyController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $companies = Company::query()
            ->when($request->query('min_reputation'), fn ($query, $value) => $query->where('reputation', '>=', $value))
            ->paginate($request->integer('per_page', 15));

        return response()->json(CompanyResource::collection($companies)->response()->getData(true));
    }

    /**
     * Achado de segurança (02/10/2026, mesmo problema do
     * ProfessionalController::show() — ver o comentário lá): esta rota só
     * exigia estar logado, sem checar dono. Agora só a própria empresa (ou
     * admin) recebe os dados completos; qualquer outra pessoa logada recebe
     * o mesmo payload público e seguro de publicProfile().
     */
    public function show(Request $request, Company $company): JsonResponse
    {
        if ($this->isOwnerOrAdmin($request, $company)) {
            return response()->json(['data' => new CompanyResource($company)]);
        }

        return $this->publicProfile($request, $company);
    }

    public function update(Request $request, Company $company): JsonResponse
    {
        $this->authorizeOwnership($request, $company);

        $validated = $request->validate([
            'responsible_name' => ['sometimes', 'string', 'max:255'],
            'responsible_cpf' => ['sometimes', 'string', 'size:11'],
            'phone' => ['sometimes', 'string', 'max:20'],
            'address' => ['nullable', 'string', 'max:255'],
            'pix_key' => ['nullable', 'string', 'max:255'],
        ]);

        $company->update($validated);

        return response()->json(['data' => new CompanyResource($company->fresh())]);
    }

    /**
     * Upload da logo da empresa (achado 02/10/2026 — paridade com
     * ProfessionalController::uploadPhoto(), que só existia pro profissional).
     */
    public function uploadPhoto(Request $request, Company $company): JsonResponse
    {
        $this->authorizeOwnership($request, $company);

        $request->validate([
            'photo' => ['required', 'image', 'max:5120'], // 5MB
        ]);

        if ($company->photo_url) {
            $oldPath = Str::after($company->photo_url, '/storage/');
            if ($oldPath !== $company->photo_url) {
                Storage::disk('public')->delete($oldPath);
            }
        }

        $path = $request->file('photo')->store('companies/' . $company->id, 'public');

        $company->update(['photo_url' => Storage::disk('public')->url($path)]);

        return response()->json(['data' => new CompanyResource($company->fresh())]);
    }

    /**
     * Perfil público (achado de perfil mockado, 02/10/2026) — mesma lógica
     * do ProfessionalController::publicProfile(), espelhada pro lado da
     * empresa. Alimenta CompanyProfile.tsx com números reais (localização,
     * flinks publicados e concluídos, reputação, % de aprovação, medalha)
     * em vez dos hardcoded que a tela tinha.
     */
    public function publicProfile(Request $request, Company $company): JsonResponse
    {
        $company->loadMissing('user');

        $ratings = Rating::query()
            ->where('rated_user_id', $company->user_id)
            ->with('rater')
            ->latest()
            ->get();

        $ratingsCount = $ratings->count();
        $approvedCount = $ratings->where('stars', '>=', 4)->count();

        $flinksCompleted = Flink::query()
            ->where('company_id', $company->id)
            ->where('status', FlinkStatus::Completed)
            ->count();

        $calculator = new ReputationCalculator();

        $visibleReviews = $this->isOwnerOrAdmin($request, $company)
            ? $ratings
            : $ratings->where('is_hidden', false)->values();

        return response()->json(['data' => [
            'id' => $company->id,
            // Nome comercial da empresa (ver useCompanyProfile.ts no frontend:
            // já usa `user.name` como "nome" da empresa, não o responsible_name).
            'name' => $company->user->name,
            'address' => $company->address,
            'photo_url' => $company->photo_url,
            'reputation' => (float) $company->reputation,
            'ratings_count' => $ratingsCount,
            'approval_rate' => $calculator->approvalRate($approvedCount, $ratingsCount),
            'medal' => $calculator->medal((float) $company->reputation, $ratingsCount),
            'flinks_completed' => $flinksCompleted,
            'reviews' => RatingResource::collection($visibleReviews),
        ]]);
    }

    private function isOwnerOrAdmin(Request $request, Company $company): bool
    {
        return $request->user()->isAdmin() || $request->user()->id === $company->user_id;
    }

    private function authorizeOwnership(Request $request, Company $company): void
    {
        abort_unless(
            $this->isOwnerOrAdmin($request, $company),
            403,
            'Você não tem permissão para editar esta empresa.'
        );
    }
}

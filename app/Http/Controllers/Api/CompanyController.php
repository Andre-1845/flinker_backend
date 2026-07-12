<?php

namespace App\Http\Controllers\Api;

use App\Domain\Company\Models\Company;
use App\Http\Controllers\Controller;
use App\Http\Resources\CompanyResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CompanyController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $companies = Company::query()
            ->when($request->query('min_reputation'), fn ($query, $value) => $query->where('reputation', '>=', $value))
            ->paginate($request->integer('per_page', 15));

        return response()->json(CompanyResource::collection($companies)->response()->getData(true));
    }

    public function show(Company $company): JsonResponse
    {
        return response()->json(['data' => new CompanyResource($company)]);
    }

    public function update(Request $request, Company $company): JsonResponse
    {
        $this->authorizeOwnership($request, $company);

        $validated = $request->validate([
            'responsible_name' => ['sometimes', 'string', 'max:255'],
            'responsible_cpf' => ['sometimes', 'string', 'size:11'],
            'phone' => ['sometimes', 'string', 'max:20'],
            'pix_key' => ['nullable', 'string', 'max:255'],
        ]);

        $company->update($validated);

        return response()->json(['data' => new CompanyResource($company->fresh())]);
    }

    private function authorizeOwnership(Request $request, Company $company): void
    {
        abort_unless(
            $request->user()->isAdmin() || $request->user()->id === $company->user_id,
            403,
            'Você não tem permissão para editar esta empresa.'
        );
    }
}

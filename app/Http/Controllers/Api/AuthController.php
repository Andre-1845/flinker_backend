<?php

namespace App\Http\Controllers\Api;

use App\Domain\Company\Actions\RegisterCompanyAction;
use App\Domain\Professional\Actions\RegisterProfessionalAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Http\Requests\Auth\RegisterCompanyRequest;
use App\Http\Requests\Auth\RegisterProfessionalRequest;
use App\Http\Resources\UserResource;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class AuthController extends Controller
{
    public function registerProfessional(RegisterProfessionalRequest $request, RegisterProfessionalAction $action): JsonResponse
    {
        $user = $action->handle($request->validated());

        return $this->respondWithToken($user, 201);
    }

    public function registerCompany(RegisterCompanyRequest $request, RegisterCompanyAction $action): JsonResponse
    {
        $user = $action->handle($request->validated());

        return $this->respondWithToken($user, 201);
    }

    public function login(LoginRequest $request): JsonResponse
    {
        $credentials = $request->validated();

        $user = User::where('email', $credentials['email'])->first();

        if (! $user || ! Hash::check($credentials['password'], $user->password)) {
            return response()->json([
                'message' => 'Credenciais inválidas.',
            ], 401);
        }

        if (! $user->is_active) {
            return response()->json([
                'message' => 'Esta conta está desativada.',
            ], 403);
        }

        return $this->respondWithToken($user->load(['professional', 'company']), 200);
    }

    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json(['message' => 'Sessão encerrada com sucesso.']);
    }

    private function respondWithToken(User $user, int $status): JsonResponse
    {
        $token = $user->createToken('flinker-api')->plainTextToken;

        return response()->json([
            'user' => new UserResource($user),
            'token' => $token,
        ], $status);
    }
}

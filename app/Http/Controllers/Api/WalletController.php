<?php

namespace App\Http\Controllers\Api;

use App\Domain\Wallet\Actions\DepositAction;
use App\Domain\Wallet\Actions\WithdrawAction;
use App\Domain\Wallet\Enums\TransactionType;
use App\Domain\Wallet\Services\WalletService;
use App\Http\Controllers\Controller;
use App\Http\Requests\Wallet\DepositRequest;
use App\Http\Requests\Wallet\WithdrawRequest;
use App\Http\Resources\TransactionResource;
use App\Http\Resources\WalletResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class WalletController extends Controller
{
    public function show(Request $request): JsonResponse
    {
        $wallet = $request->user()->wallet;

        abort_unless($wallet, 404, 'Carteira não encontrada.');

        return response()->json([
            'data' => new WalletResource($wallet),
        ]);
    }

    public function deposit(DepositRequest $request, DepositAction $action): JsonResponse
    {
        $wallet = $request->user()->wallet;

        abort_unless($wallet, 404, 'Carteira não encontrada.');

        $result = $action->handle($wallet, (float) $request->validated('amount'));

        return response()->json([
            'message' => 'Depósito iniciado. Complete o pagamento para que o saldo seja creditado.',
            'checkout_url' => $result['checkout_url'],
        ], 201);
    }

    public function withdraw(WithdrawRequest $request, WithdrawAction $action): JsonResponse
    {
        $wallet = $request->user()->wallet;

        abort_unless($wallet, 404, 'Carteira não encontrada.');

        $transaction = $action->handle($wallet, (float) $request->validated('amount'));

        return response()->json([
            'message' => 'Saque solicitado. O valor foi debitado e o Pix será processado em breve.',
            'data' => new TransactionResource($transaction),
        ], 201);
    }

    /**
     * ⚠️ SÓ FUNCIONA EM AMBIENTE LOCAL. Credita saldo diretamente, sem passar pelo
     * Mercado Pago — existe unicamente para testar o resto do fluxo (reserva de
     * pagamento ao criar Flink, split ao concluir) sem precisar configurar credenciais
     * reais e um túnel público (ngrok) pro webhook. Nunca habilitar isso fora de `local`.
     */
    public function devTopup(Request $request, WalletService $walletService): JsonResponse
    {
        abort_unless(app()->environment('local'), 404);

        $data = $request->validate(['amount' => ['required', 'numeric', 'min:1']]);

        $wallet = $request->user()->wallet;
        abort_unless($wallet, 404, 'Carteira não encontrada.');

        $walletService->credit($wallet, (float) $data['amount'], TransactionType::Deposit);

        return response()->json(['data' => new WalletResource($wallet->fresh())]);
    }
}

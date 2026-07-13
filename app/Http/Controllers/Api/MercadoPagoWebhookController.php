<?php

namespace App\Http\Controllers\Api;

use App\Domain\Wallet\Actions\ProcessMercadoPagoWebhookAction;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class MercadoPagoWebhookController extends Controller
{
    /**
     * Endpoint público (fora do middleware `auth:sanctum`) que o Mercado Pago chama
     * quando o status de um pagamento muda. Não confiamos no corpo da notificação —
     * a Action reconsulta a API do Mercado Pago pra confirmar o status de verdade.
     *
     * Sempre respondemos 200 rapidamente (mesmo em erro interno) pra evitar que o
     * Mercado Pago fique reenviando a notificação indefinidamente; o erro fica logado.
     */
    public function __invoke(Request $request, ProcessMercadoPagoWebhookAction $action): JsonResponse
    {
        $paymentId = $request->input('data.id') ?? $request->query('id');

        if (! $paymentId) {
            return response()->json(['status' => 'ignored'], 200);
        }

        try {
            $action->handle((string) $paymentId);
        } catch (\Throwable $e) {
            Log::error('Erro ao processar webhook do Mercado Pago', [
                'payment_id' => $paymentId,
                'error' => $e->getMessage(),
            ]);
        }

        return response()->json(['status' => 'ok'], 200);
    }
}

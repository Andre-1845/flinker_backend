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
     * quando o status de um pagamento muda. Protegido por rate limiting (ver
     * routes/api.php) e pela verificação de assinatura feita dentro da Action — nunca
     * confiamos só no corpo da notificação, ela sempre reconsulta a API do Mercado
     * Pago pra confirmar o status de verdade.
     *
     * Sempre respondemos 200 rapidamente (mesmo em erro interno ou assinatura
     * inválida) pra evitar que o Mercado Pago fique reenviando a notificação
     * indefinidamente; o erro fica logado.
     */
    public function __invoke(Request $request, ProcessMercadoPagoWebhookAction $action): JsonResponse
    {
        try {
            $action->handle($request);
        } catch (\Throwable $e) {
            Log::error('Erro ao processar webhook do Mercado Pago', [
                'error' => $e->getMessage(),
            ]);
        }

        return response()->json(['status' => 'ok'], 200);
    }
}

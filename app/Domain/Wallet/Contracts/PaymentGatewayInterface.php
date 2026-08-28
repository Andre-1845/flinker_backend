<?php

namespace App\Domain\Wallet\Contracts;

use App\Domain\Wallet\Models\Wallet;
use Illuminate\Http\Request;

/**
 * Contrato que qualquer gateway de pagamento precisa cumprir pra ser usado pela
 * plataforma. O restante do código (Actions, WalletService) nunca deve depender
 * diretamente de uma implementação concreta (ex: MercadoPagoService) — sempre
 * dessa interface, resolvida via `PaymentGatewayServiceProvider` a partir de
 * `config('services.payment_gateway.driver')`.
 *
 * Trocar de gateway (ex: Mercado Pago -> Pagar.me/Stripe) significa criar uma nova
 * classe que implementa essa interface e apontar o driver no `.env` — nenhuma
 * Action ou Controller precisa mudar.
 */
interface PaymentGatewayInterface
{
    /**
     * Cria uma cobrança/preferência de checkout para a empresa depositar na carteira.
     *
     * @return array{checkout_url: string, preference_id: string}
     */
    public function createDepositPreference(Wallet $wallet, float $amount, string $externalReference): array;

    /**
     * Busca um pagamento direto na API do gateway pra confirmar o status de verdade.
     * Nunca confiar só no corpo de um webhook — sempre reconsultar a fonte.
     */
    public function fetchPayment(string $paymentId): array;

    /**
     * Gera um identificador único pra correlacionar uma transação nossa com a
     * referência externa enviada ao gateway.
     */
    public function generateExternalReference(): string;

    /**
     * Verifica se uma notificação de webhook realmente veio do gateway (assinatura
     * criptográfica), não só se o payload "parece" válido. Deve retornar `true` sem
     * validar (e logar um aviso) quando não houver segredo configurado — situação
     * aceitável em ambiente local/dev, mas nunca em produção.
     */
    public function verifyWebhookSignature(Request $request): bool;

    /**
     * Nome curto do driver (ex: "mercadopago") — usado em logs e mensagens de erro.
     */
    public function driverName(): string;
}

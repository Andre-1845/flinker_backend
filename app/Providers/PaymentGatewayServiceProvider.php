<?php

namespace App\Providers;

use App\Domain\Wallet\Contracts\PaymentGatewayInterface;
use App\Domain\Wallet\Services\MercadoPagoService;
use Illuminate\Support\ServiceProvider;
use InvalidArgumentException;

/**
 * Escolhe qual implementação de `PaymentGatewayInterface` a aplicação usa, a partir
 * de `PAYMENT_GATEWAY_DRIVER` no `.env` (padrão: `mercadopago`).
 *
 * Pra trocar de gateway de pagamento: criar uma nova classe que implementa
 * `PaymentGatewayInterface`, registrar aqui com um novo nome de driver, e mudar a
 * variável de ambiente — nenhuma Action ou Controller do domínio de Carteira precisa
 * mudar.
 */
class PaymentGatewayServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(PaymentGatewayInterface::class, function ($app) {
            $driver = config('services.payment_gateway.driver', 'mercadopago');

            return match ($driver) {
                'mercadopago' => $app->make(MercadoPagoService::class),
                default => throw new InvalidArgumentException(
                    "Gateway de pagamento \"{$driver}\" não reconhecido. Verifique PAYMENT_GATEWAY_DRIVER no .env."
                ),
            };
        });
    }
}

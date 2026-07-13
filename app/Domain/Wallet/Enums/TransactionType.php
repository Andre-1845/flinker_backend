<?php

namespace App\Domain\Wallet\Enums;

enum TransactionType: string
{
    case Deposit = 'deposit';           // empresa deposita na carteira via gateway
    case Withdrawal = 'withdrawal';     // profissional saca via Pix
    case Reservation = 'reservation';   // valor do Flink reservado (debitado) da empresa na criação
    case Refund = 'refund';             // reserva devolvida à empresa (Flink cancelado)
    case Earning = 'earning';           // valor líquido creditado ao profissional (Flink concluído)
    case PlatformFee = 'platform_fee';  // margem retida pela plataforma (Flink concluído)

    public function label(): string
    {
        return match ($this) {
            self::Deposit => 'Depósito',
            self::Withdrawal => 'Saque',
            self::Reservation => 'Reserva de pagamento',
            self::Refund => 'Estorno',
            self::Earning => 'Ganho por Flink',
            self::PlatformFee => 'Margem da plataforma',
        };
    }
}

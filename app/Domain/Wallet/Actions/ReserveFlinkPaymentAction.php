<?php

namespace App\Domain\Wallet\Actions;

use App\Domain\Flink\Models\Flink;
use App\Domain\Wallet\Enums\TransactionType;
use App\Domain\Wallet\Models\Transaction;
use App\Domain\Wallet\Models\Wallet;
use App\Domain\Wallet\Services\WalletService;

class ReserveFlinkPaymentAction
{
    public function __construct(
        private readonly WalletService $walletService,
    ) {}

    /**
     * Debita o valor total do Flink da carteira da empresa, garantindo o pagamento antes
     * de publicá-lo (decisão de produto: "o pagamento será liberado apenas após a
     * execução do serviço... para publicar um flink, é necessário garantir o valor").
     *
     * Lança ValidationException (via WalletService::debit) se o saldo for insuficiente —
     * quem chama deve fazer isso dentro de uma transação de banco que também reverte a
     * criação do Flink nesse caso (ver CreateFlinkAction).
     */
    public function handle(Wallet $companyWallet, Flink $flink): Transaction
    {
        return $this->walletService->debit(
            $companyWallet,
            (float) $flink->total_value,
            TransactionType::Reservation,
            $flink,
        );
    }
}

<?php

namespace App\Domain\Wallet\Services;

use App\Domain\Flink\Models\Flink;
use App\Domain\Wallet\Enums\TransactionStatus;
use App\Domain\Wallet\Enums\TransactionType;
use App\Domain\Wallet\Models\Transaction;
use App\Domain\Wallet\Models\Wallet;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class WalletService
{
    /**
     * Credita um valor na carteira, registrando a transação. Sempre em transação de
     * banco com lock pessimista na wallet, pra evitar corrida quando duas operações
     * tentam mexer no mesmo saldo ao mesmo tempo.
     */
    public function credit(
        Wallet $wallet,
        float $amount,
        TransactionType $type,
        ?Flink $flink = null,
        ?string $externalReference = null,
        array $metadata = [],
    ): Transaction {
        return DB::transaction(function () use ($wallet, $amount, $type, $flink, $externalReference, $metadata) {
            $locked = Wallet::query()->whereKey($wallet->id)->lockForUpdate()->firstOrFail();
            $locked->increment('balance', $amount);

            return Transaction::create([
                'wallet_id' => $locked->id,
                'flink_id' => $flink?->id,
                'type' => $type,
                'amount' => $amount,
                'status' => TransactionStatus::Completed,
                'external_reference' => $externalReference,
                'metadata' => $metadata,
            ]);
        });
    }

    /**
     * Debita um valor da carteira. Lança ValidationException se o saldo for insuficiente.
     */
    public function debit(
        Wallet $wallet,
        float $amount,
        TransactionType $type,
        ?Flink $flink = null,
        ?string $externalReference = null,
        array $metadata = [],
    ): Transaction {
        return DB::transaction(function () use ($wallet, $amount, $type, $flink, $externalReference, $metadata) {
            $locked = Wallet::query()->whereKey($wallet->id)->lockForUpdate()->firstOrFail();

            if ($locked->balance < $amount) {
                throw ValidationException::withMessages([
                    'balance' => 'Saldo insuficiente para essa operação.',
                ]);
            }

            $locked->decrement('balance', $amount);

            return Transaction::create([
                'wallet_id' => $locked->id,
                'flink_id' => $flink?->id,
                'type' => $type,
                'amount' => $amount,
                'status' => TransactionStatus::Completed,
                'external_reference' => $externalReference,
                'metadata' => $metadata,
            ]);
        });
    }

    /**
     * Registra uma transação pendente sem mexer no saldo ainda (usado no depósito —
     * o saldo só é creditado quando o webhook do Mercado Pago confirmar o pagamento).
     */
    public function createPending(
        Wallet $wallet,
        float $amount,
        TransactionType $type,
        ?string $externalReference = null,
        array $metadata = [],
    ): Transaction {
        return Transaction::create([
            'wallet_id' => $wallet->id,
            'type' => $type,
            'amount' => $amount,
            'status' => TransactionStatus::Pending,
            'external_reference' => $externalReference,
            'metadata' => $metadata,
        ]);
    }

    /**
     * Debita o valor imediatamente (evita saque duplicado do mesmo saldo), mas marca a
     * transação como 'pending' — usado no saque, que só vira 'completed' depois da
     * confirmação manual/do gateway de que o Pix foi enviado de verdade.
     */
    public function debitPending(
        Wallet $wallet,
        float $amount,
        TransactionType $type,
        ?string $externalReference = null,
        array $metadata = [],
    ): Transaction {
        return DB::transaction(function () use ($wallet, $amount, $type, $externalReference, $metadata) {
            $locked = Wallet::query()->whereKey($wallet->id)->lockForUpdate()->firstOrFail();

            if ($locked->balance < $amount) {
                throw ValidationException::withMessages([
                    'balance' => 'Saldo insuficiente para essa operação.',
                ]);
            }

            $locked->decrement('balance', $amount);

            return Transaction::create([
                'wallet_id' => $locked->id,
                'type' => $type,
                'amount' => $amount,
                'status' => TransactionStatus::Pending,
                'external_reference' => $externalReference,
                'metadata' => $metadata,
            ]);
        });
    }

    /**
     * Confirma uma transação pendente (ex: saque processado com sucesso, depósito pago).
     * Para depósitos, credita o saldo agora (ele nunca foi debitado/creditado antes).
     */
    public function markCompleted(Transaction $transaction): Transaction
    {
        return DB::transaction(function () use ($transaction) {
            if ($transaction->type === TransactionType::Deposit) {
                $locked = Wallet::query()->whereKey($transaction->wallet_id)->lockForUpdate()->firstOrFail();
                $locked->increment('balance', $transaction->amount);
            }

            $transaction->update(['status' => TransactionStatus::Completed]);

            return $transaction->fresh();
        });
    }

    /**
     * Marca uma transação pendente como falha. Para saques (que já debitaram o saldo
     * imediatamente), devolve o valor pra carteira.
     */
    public function markFailed(Transaction $transaction): Transaction
    {
        return DB::transaction(function () use ($transaction) {
            if ($transaction->type === TransactionType::Withdrawal) {
                $locked = Wallet::query()->whereKey($transaction->wallet_id)->lockForUpdate()->firstOrFail();
                $locked->increment('balance', $transaction->amount);
            }

            $transaction->update(['status' => TransactionStatus::Failed]);

            return $transaction->fresh();
        });
    }
}

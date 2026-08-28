<?php

namespace App\Filament\Resources\Transactions\Tables;

use App\Domain\Wallet\Enums\TransactionStatus;
use App\Domain\Wallet\Enums\TransactionType;
use App\Domain\Wallet\Models\Transaction;
use App\Domain\Wallet\Services\WalletService;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class TransactionsTable
{
    /**
     * O ledger financeiro é só leitura pelo painel — a única ação disponível é
     * aprovar/rejeitar um saque pendente (o gap crítico apontado na auditoria: sem
     * isso, não havia NENHUMA forma de um profissional receber de verdade). Tudo o
     * mais (depósito, reserva, split) é escrito pelas Actions do domínio, nunca
     * editado manualmente aqui — evita que um clique errado corrompa o saldo.
     */
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('id')
                    ->label('#')
                    ->sortable(),
                TextColumn::make('wallet.user.name')
                    ->label('Titular da carteira')
                    ->searchable(),
                TextColumn::make('type')
                    ->label('Tipo')
                    ->badge()
                    ->formatStateUsing(fn (TransactionType $state) => $state->label())
                    ->color(fn (TransactionType $state) => match ($state) {
                        TransactionType::Deposit, TransactionType::Earning, TransactionType::Refund => 'success',
                        TransactionType::Withdrawal, TransactionType::Reservation => 'warning',
                        TransactionType::PlatformFee => 'gray',
                    }),
                TextColumn::make('amount')
                    ->label('Valor')
                    ->money('BRL')
                    ->sortable(),
                TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->formatStateUsing(fn (TransactionStatus $state) => $state->label())
                    ->color(fn (TransactionStatus $state) => match ($state) {
                        TransactionStatus::Completed => 'success',
                        TransactionStatus::Pending => 'warning',
                        TransactionStatus::Failed, TransactionStatus::Cancelled => 'danger',
                    }),
                TextColumn::make('flink_id')
                    ->label('Flink')
                    ->placeholder('—'),
                TextColumn::make('external_reference')
                    ->label('Referência externa')
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('created_at')
                    ->label('Data')
                    ->dateTime('d/m/Y H:i')
                    ->sortable(),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                SelectFilter::make('type')
                    ->label('Tipo')
                    ->options(TransactionType::class),
                SelectFilter::make('status')
                    ->label('Status')
                    ->options(TransactionStatus::class),
            ])
            ->recordActions([
                Action::make('approve_withdrawal')
                    ->label('Aprovar saque')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->visible(fn (Transaction $record) => $record->type === TransactionType::Withdrawal
                        && $record->status === TransactionStatus::Pending)
                    ->requiresConfirmation()
                    ->modalDescription('Confirma que o Pix foi enviado de verdade pro profissional? Essa ação marca a transação como concluída — o valor já foi debitado da carteira dele no momento do pedido de saque.')
                    ->action(function (Transaction $record, WalletService $walletService) {
                        $walletService->markCompleted($record);

                        Notification::make()
                            ->title('Saque aprovado')
                            ->success()
                            ->send();
                    }),
                Action::make('reject_withdrawal')
                    ->label('Rejeitar saque')
                    ->icon('heroicon-o-x-circle')
                    ->color('danger')
                    ->visible(fn (Transaction $record) => $record->type === TransactionType::Withdrawal
                        && $record->status === TransactionStatus::Pending)
                    ->requiresConfirmation()
                    ->modalDescription('O valor volta pra carteira do profissional (o saque não vai acontecer).')
                    ->action(function (Transaction $record, WalletService $walletService) {
                        $walletService->markFailed($record);

                        Notification::make()
                            ->title('Saque rejeitado — valor devolvido à carteira')
                            ->warning()
                            ->send();
                    }),
            ])
            ->toolbarActions([]);
    }
}

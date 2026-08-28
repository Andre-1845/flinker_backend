<?php

namespace App\Filament\Resources\Transactions;

use App\Domain\Wallet\Models\Transaction;
use App\Filament\Resources\Transactions\Pages\ListTransactions;
use App\Filament\Resources\Transactions\Tables\TransactionsTable;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

/**
 * Ledger financeiro — só leitura + aprovar/rejeitar saque (ver TransactionsTable).
 * Sem create/edit: toda transação nasce de uma Action do domínio (WalletService),
 * nunca de um formulário manual no painel.
 */
class TransactionResource extends Resource
{
    protected static ?string $model = Transaction::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBanknotes;

    protected static ?string $navigationLabel = 'Transações';

    protected static ?string $modelLabel = 'transação';

    protected static ?string $pluralModelLabel = 'transações';

    public static function table(Table $table): Table
    {
        return TransactionsTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListTransactions::route('/'),
        ];
    }

    public static function canCreate(): bool
    {
        return false;
    }
}

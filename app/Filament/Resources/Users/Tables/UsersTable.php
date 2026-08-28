<?php

namespace App\Filament\Resources\Users\Tables;

use App\Domain\Shared\Enums\UserProfile;
use App\Models\User;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;

class UsersTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->searchable(),
                TextColumn::make('email')
                    ->label('E-mail')
                    ->searchable(),
                TextColumn::make('profile')
                    ->label('Perfil')
                    ->badge()
                    ->formatStateUsing(fn (UserProfile $state) => $state->label())
                    ->color(fn (UserProfile $state) => match ($state) {
                        UserProfile::Admin => 'warning',
                        UserProfile::Company => 'info',
                        UserProfile::Professional => 'success',
                    }),
                IconColumn::make('is_active')
                    ->label('Ativo')
                    ->boolean(),
                TextColumn::make('created_at')
                    ->label('Cadastrado em')
                    ->dateTime('d/m/Y H:i')
                    ->sortable(),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                SelectFilter::make('profile')
                    ->label('Perfil')
                    ->options(UserProfile::class),
                TernaryFilter::make('is_active')
                    ->label('Ativo'),
            ])
            ->recordActions([
                EditAction::make()
                    ->label('Editar'),
                Action::make('block')
                    ->label('Bloquear')
                    ->icon('heroicon-o-lock-closed')
                    ->color('danger')
                    ->visible(fn (User $record) => $record->is_active && ! $record->isAdmin())
                    ->requiresConfirmation()
                    ->modalDescription('O usuário perde acesso imediatamente. A carteira e o histórico não são afetados — só o login.')
                    ->action(fn (User $record) => $record->update(['is_active' => false])),
                Action::make('unblock')
                    ->label('Desbloquear')
                    ->icon('heroicon-o-lock-open')
                    ->color('success')
                    ->visible(fn (User $record) => ! $record->is_active)
                    ->requiresConfirmation()
                    ->action(fn (User $record) => $record->update(['is_active' => true])),
            ])
            ->toolbarActions([
                //  Sem bulk delete: apagar um usuário arrasta carteira, Flinks e
                // transações junto — bloquear é a ação segura pro dia a dia do teste.
            ]);
    }
}

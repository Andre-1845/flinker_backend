<?php

namespace App\Filament\Resources\Flinks\Tables;

use App\Domain\Flink\Enums\FlinkStatus;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class FlinksTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('id')->label('#'),
                TextColumn::make('company.responsible_name')
                    ->label('Empresa')
                    ->searchable(),
                TextColumn::make('activity_type')
                    ->label('Atividade')
                    ->searchable(),
                TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->formatStateUsing(fn (FlinkStatus $state) => $state->label())
                    ->color(fn (FlinkStatus $state) => match ($state) {
                        FlinkStatus::Open, FlinkStatus::Matched => 'gray',
                        FlinkStatus::Confirmed, FlinkStatus::InProgress => 'warning',
                        FlinkStatus::Completed => 'success',
                        FlinkStatus::Cancelled => 'danger',
                    }),
                TextColumn::make('total_value')
                    ->label('Valor total')
                    ->money('BRL')
                    ->sortable(),
                TextColumn::make('start_date_time')
                    ->label('Início')
                    ->dateTime('d/m/Y H:i')
                    ->sortable(),
                TextColumn::make('created_at')
                    ->label('Publicado em')
                    ->dateTime('d/m/Y H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                SelectFilter::make('status')
                    ->label('Status')
                    ->options(FlinkStatus::class),
            ])
            ->recordActions([])
            ->toolbarActions([]);
    }
}

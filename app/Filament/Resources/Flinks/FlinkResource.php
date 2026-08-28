<?php

namespace App\Filament\Resources\Flinks;

use App\Domain\Flink\Models\Flink;
use App\Filament\Resources\Flinks\Pages\ListFlinks;
use App\Filament\Resources\Flinks\Tables\FlinksTable;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

/**
 * Visão geral só-leitura pro admin acompanhar/investigar disputas — Flinks são
 * criados e geridos pelas empresas através do app, nunca pelo painel.
 */
class FlinkResource extends Resource
{
    protected static ?string $model = Flink::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBriefcase;

    protected static ?string $navigationLabel = 'Flinks';

    protected static ?string $modelLabel = 'Flink';

    protected static ?string $pluralModelLabel = 'Flinks';

    public static function table(Table $table): Table
    {
        return FlinksTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListFlinks::route('/'),
        ];
    }

    public static function canCreate(): bool
    {
        return false;
    }
}

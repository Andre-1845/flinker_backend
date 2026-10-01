<?php

namespace App\Filament\Resources\Settings\Schemas;

use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class SettingForm
{
    /**
     * Enxuto de propósito: linhas de `settings` nascem pela migration (ver
     * 2026_10_01_000001_create_settings_table.php), nunca criadas livremente
     * pelo painel (ver SettingResource::canCreate) — só o valor é editável
     * aqui. `numeric()` reflete que a única config hoje (margem da plataforma)
     * é um percentual; se um dia existir uma config não-numérica, revisar isso.
     */
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('key')
                    ->label('Chave')
                    ->disabled()
                    ->dehydrated(false),
                TextInput::make('value')
                    ->label('Valor')
                    ->required()
                    ->numeric()
                    ->helperText('Para "platform_margin_percent": percentual aplicado sobre o valor líquido do Flink (ex: 7 = 7%).'),
            ]);
    }
}

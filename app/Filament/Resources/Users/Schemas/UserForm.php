<?php

namespace App\Filament\Resources\Users\Schemas;

use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class UserForm
{
    /**
     * Deliberadamente enxuto: perfil (professional/company/admin) é exclusivo por
     * conta e imutável no resto da aplicação (ver docs/ARCHITECTURE.md do backend) —
     * o admin não deve poder trocar isso por aqui. Senha também não é editável em
     * texto puro pelo painel; reset de senha passa pelo fluxo de
     * esqueci-minha-senha do próprio usuário.
     */
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->label('Nome')
                    ->required(),
                TextInput::make('email')
                    ->label('E-mail')
                    ->email()
                    ->required(),
                Toggle::make('is_active')
                    ->label('Conta ativa'),
            ]);
    }
}

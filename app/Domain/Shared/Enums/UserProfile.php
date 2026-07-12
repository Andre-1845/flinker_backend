<?php

namespace App\Domain\Shared\Enums;

enum UserProfile: string
{
    case Professional = 'professional';
    case Company = 'company';
    case Admin = 'admin';

    public function label(): string
    {
        return match ($this) {
            self::Professional => 'Profissional',
            self::Company => 'Empresa',
            self::Admin => 'Administrador',
        };
    }
}

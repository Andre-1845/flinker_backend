<?php

namespace App\Domain\Match\Enums;

enum MatchStatus: string
{
    case Pending = 'pending';       // profissional demonstrou interesse, aguardando escolha da empresa
    case Accepted = 'accepted';     // empresa escolheu este profissional, aguardando aceite dele
    case Confirmed = 'confirmed';   // aceite mútuo — os dois lados confirmaram
    case Rejected = 'rejected';     // não foi o escolhido (outro profissional foi selecionado)
    case Cancelled = 'cancelled';   // cancelado por qualquer uma das partes após aceite/confirmação

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Aguardando',
            self::Accepted => 'Aceito pela empresa',
            self::Confirmed => 'Confirmado',
            self::Rejected => 'Recusado',
            self::Cancelled => 'Cancelado',
        };
    }
}

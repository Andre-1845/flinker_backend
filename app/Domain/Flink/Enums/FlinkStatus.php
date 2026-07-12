<?php

namespace App\Domain\Flink\Enums;

enum FlinkStatus: string
{
    case Open = 'open';               // publicado, aceitando interesse de profissionais
    case Matched = 'matched';         // já tem match, aguardando aceite final da empresa
    case Confirmed = 'confirmed';     // aceite mútuo confirmado, aguardando execução
    case InProgress = 'in_progress';  // check-in feito, em execução
    case Completed = 'completed';     // executado e pago
    case Cancelled = 'cancelled';     // cancelado por empresa ou profissional

    public function label(): string
    {
        return match ($this) {
            self::Open => 'Aberto',
            self::Matched => 'Com match',
            self::Confirmed => 'Confirmado',
            self::InProgress => 'Em execução',
            self::Completed => 'Concluído',
            self::Cancelled => 'Cancelado',
        };
    }

    /**
     * Estados a partir dos quais o Flink ainda pode ser editado/cancelado livremente.
     */
    public function isEditable(): bool
    {
        return in_array($this, [self::Open, self::Matched], true);
    }
}

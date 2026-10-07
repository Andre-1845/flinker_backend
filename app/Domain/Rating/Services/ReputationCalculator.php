<?php

namespace App\Domain\Rating\Services;

/**
 * Regras simples e honestas pra exibir reputação (achado de perfil mockado,
 * 02/10/2026). Substitui os números fixos que existiam nas telas de perfil
 * (medalha, nota, % de aprovação) por algo calculado a partir de avaliações
 * reais. Deliberadamente simples pro MVP: thresholds fixos aqui, não
 * configuráveis pelo Filament ainda — dá pra promover depois se o Andre
 * quiser ajustar as faixas sem deploy.
 */
class ReputationCalculator
{
    public function medal(float $averageStars, int $ratingsCount): string
    {
        if ($ratingsCount >= 5 && $averageStars >= 4.5) {
            return 'Ouro';
        }

        if ($averageStars >= 3.5) {
            return 'Prata';
        }

        return 'Bronze';
    }

    /** % das avaliações recebidas com nota >= 4. */
    public function approvalRate(int $approvedCount, int $totalCount): float
    {
        if ($totalCount === 0) {
            return 0.0;
        }

        return round(($approvedCount / $totalCount) * 100);
    }
}

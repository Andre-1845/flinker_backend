<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ProfessionalResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            // Nome vem do User (Professional não tem coluna própria) — precisa de
            // ->with('professional.user') no controller pra não dar N+1 (ver
            // MatchController::index, achado #1 da auditoria de 2026-10-01).
            'name' => $this->whenLoaded('user', fn () => $this->user->name),
            'cpf' => $this->cpf,
            'phone' => $this->phone,
            'address' => $this->address,
            'pix_key' => $this->pix_key,
            'photo_url' => $this->photo_url,
            'is_mei' => $this->is_mei,
            'cnpj' => $this->cnpj,
            'reputation' => (float) $this->reputation,
        ];
    }
}

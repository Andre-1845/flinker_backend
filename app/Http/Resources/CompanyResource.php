<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CompanyResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            // Nome comercial da empresa (achado de nome errado, 02/10/2026):
            // várias telas do frontend mostravam `responsible_name` (a pessoa
            // responsável) como se fosse o nome da empresa. `user.name` é o
            // nome comercial de verdade (ver CompanyController::publicProfile()).
            'name' => $this->whenLoaded('user', fn () => $this->user->name),
            'cnpj' => $this->cnpj,
            'responsible_name' => $this->responsible_name,
            'responsible_cpf' => $this->responsible_cpf,
            'phone' => $this->phone,
            'address' => $this->address,
            'pix_key' => $this->pix_key,
            'photo_url' => $this->photo_url,
            'reputation' => (float) $this->reputation,
        ];
    }
}

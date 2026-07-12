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
            'cnpj' => $this->cnpj,
            'responsible_name' => $this->responsible_name,
            'responsible_cpf' => $this->responsible_cpf,
            'phone' => $this->phone,
            'address' => $this->address,
            'pix_key' => $this->pix_key,
            'reputation' => (float) $this->reputation,
        ];
    }
}

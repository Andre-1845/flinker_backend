<?php

namespace App\Http\Requests\Flink;

use Illuminate\Foundation\Http\FormRequest;

class UpdateFlinkRequest extends FormRequest
{
    public function authorize(): bool
    {
        // A checagem de dono (só a própria empresa) é feita no controller,
        // porque precisa comparar com o Flink já resolvido pela rota.
        return $this->user()->isCompany() || $this->user()->isAdmin();
    }

    public function rules(): array
    {
        return [
            'activity_type' => ['sometimes', 'string', 'max:255'],
            'location' => ['sometimes', 'string', 'max:255'],
            'latitude' => ['sometimes', 'numeric', 'between:-90,90'],
            'longitude' => ['sometimes', 'numeric', 'between:-180,180'],
            'start_date_time' => ['sometimes', 'date'],
            'end_date_time' => ['sometimes', 'date', 'after:start_date_time'],
            'requirements' => ['nullable', 'string'],
            'net_value' => ['sometimes', 'numeric', 'min:1'],
            'status' => ['sometimes', 'string', 'in:open,matched,confirmed,in_progress,completed,cancelled'],
        ];
    }
}

<?php

namespace App\Http\Requests\Flink;

use Illuminate\Foundation\Http\FormRequest;

class StoreFlinkRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->isCompany();
    }

    public function rules(): array
    {
        return [
            'activity_type' => ['required', 'string', 'max:255'],
            'location' => ['required', 'string', 'max:255'],
            'latitude' => ['required', 'numeric', 'between:-90,90'],
            'longitude' => ['required', 'numeric', 'between:-180,180'],
            'start_date_time' => ['required', 'date', 'after:now'],
            'end_date_time' => ['required', 'date', 'after:start_date_time'],
            'requirements' => ['nullable', 'string'],
            'net_value' => ['required', 'numeric', 'min:1'],
        ];
    }

    public function messages(): array
    {
        return [
            'end_date_time.after' => 'O horário de término deve ser depois do horário de início.',
        ];
    }
}

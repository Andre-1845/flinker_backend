<?php

namespace App\Http\Requests\Match;

use Illuminate\Foundation\Http\FormRequest;

class StoreMatchRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->isProfessional();
    }

    public function rules(): array
    {
        return [
            'flink_id' => ['required', 'integer', 'exists:flinks,id'],
        ];
    }
}

<?php

namespace App\Http\Requests\Schedule;

use Illuminate\Foundation\Http\FormRequest;

class BlockScheduleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->isProfessional();
    }

    public function rules(): array
    {
        return [
            'start_date_time' => ['required', 'date', 'after:now'],
            'end_date_time' => ['required', 'date', 'after:start_date_time'],
            'reason' => ['nullable', 'string', 'max:255'],
        ];
    }
}

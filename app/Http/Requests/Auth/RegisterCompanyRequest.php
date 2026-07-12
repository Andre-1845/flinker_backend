<?php

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;

class RegisterCompanyRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
            'cnpj' => ['required', 'string', 'size:14', 'unique:companies,cnpj'],
            'responsible_name' => ['required', 'string', 'max:255'],
            'responsible_cpf' => ['required', 'string', 'size:11'],
            'phone' => ['required', 'string', 'max:20'],
            'address' => ['nullable', 'string', 'max:255'],
            'pix_key' => ['nullable', 'string', 'max:255'],
        ];
    }
}

<?php

namespace App\Domain\Company\Actions;

use App\Domain\Company\Models\Company;
use App\Domain\Shared\Enums\UserProfile;
use App\Domain\Wallet\Models\Wallet;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class RegisterCompanyAction
{
    /**
     * @param  array{name: string, email: string, password: string, cnpj: string, responsible_name: string, responsible_cpf: string, phone: string, pix_key?: string}  $data
     */
    public function handle(array $data): User
    {
        return DB::transaction(function () use ($data) {
            $user = User::create([
                'name' => $data['name'],
                'email' => $data['email'],
                'password' => Hash::make($data['password']),
                'profile' => UserProfile::Company,
                'is_active' => true,
            ]);

            Company::create([
                'user_id' => $user->id,
                'cnpj' => $data['cnpj'],
                'responsible_name' => $data['responsible_name'],
                'responsible_cpf' => $data['responsible_cpf'],
                'phone' => $data['phone'],
                'address' => $data['address'] ?? null,
                'pix_key' => $data['pix_key'] ?? null,
            ]);

            Wallet::create(['user_id' => $user->id, 'balance' => 0]);

            return $user->load('company');
        });
    }
}

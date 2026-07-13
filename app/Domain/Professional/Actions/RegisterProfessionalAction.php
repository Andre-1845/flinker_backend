<?php

namespace App\Domain\Professional\Actions;

use App\Domain\Professional\Models\Professional;
use App\Domain\Shared\Enums\UserProfile;
use App\Domain\Wallet\Models\Wallet;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class RegisterProfessionalAction
{
    /**
     * @param  array{name: string, email: string, password: string, cpf: string, phone: string, address?: string, pix_key?: string}  $data
     */
    public function handle(array $data): User
    {
        return DB::transaction(function () use ($data) {
            $user = User::create([
                'name' => $data['name'],
                'email' => $data['email'],
                'password' => Hash::make($data['password']),
                'profile' => UserProfile::Professional,
                'is_active' => true,
            ]);

            Professional::create([
                'user_id' => $user->id,
                'cpf' => $data['cpf'],
                'phone' => $data['phone'],
                'address' => $data['address'] ?? null,
                'pix_key' => $data['pix_key'] ?? null,
            ]);

            Wallet::create(['user_id' => $user->id, 'balance' => 0]);

            return $user->load('professional');
        });
    }
}

<?php

namespace App\Console\Commands;

use App\Domain\Shared\Enums\UserProfile;
use App\Domain\Wallet\Models\Wallet;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;

/**
 * Cria a primeira conta admin (acesso ao painel Filament em /admin). Não existe
 * forma de criar um admin pelo cadastro público do app nem pelo próprio painel
 * (UserResource::canCreate() é false de propósito) — só por aqui, uma vez, no
 * servidor.
 */
class MakeAdminUser extends Command
{
    protected $signature = 'flinker:make-admin {email} {name} {--password=}';

    protected $description = 'Cria uma conta admin (acesso ao painel /admin)';

    public function handle(): int
    {
        $email = $this->argument('email');
        $name = $this->argument('name');
        $password = $this->option('password') ?: $this->secret('Senha');

        $validator = Validator::make(
            ['email' => $email, 'name' => $name, 'password' => $password],
            [
                'email' => ['required', 'email', 'unique:users,email'],
                'name' => ['required', 'string', 'max:255'],
                'password' => ['required', 'string', 'min:8'],
            ]
        );

        if ($validator->fails()) {
            foreach ($validator->errors()->all() as $error) {
                $this->error($error);
            }

            return self::FAILURE;
        }

        $user = User::create([
            'name' => $name,
            'email' => $email,
            'password' => Hash::make($password),
            'profile' => UserProfile::Admin,
            'is_active' => true,
        ]);

        // Consistente com o resto da aplicação: toda conta ganha uma Wallet, mesmo
        // que um admin não deva ter saldo/transações de verdade.
        Wallet::create(['user_id' => $user->id, 'balance' => 0]);

        $this->info("Conta admin criada: {$email}. Acesse /admin pra entrar.");

        return self::SUCCESS;
    }
}

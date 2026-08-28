<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Domain\Company\Models\Company;
use App\Domain\Professional\Models\Professional;
use App\Domain\Shared\Enums\UserProfile;
use App\Domain\Wallet\Models\Wallet;
use Database\Factories\UserFactory;
use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable implements FilamentUser
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'profile',
        'is_active',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'profile' => UserProfile::class,
            'is_active' => 'boolean',
        ];
    }

    public function professional(): HasOne
    {
        return $this->hasOne(Professional::class);
    }

    public function company(): HasOne
    {
        return $this->hasOne(Company::class);
    }

    public function wallet(): HasOne
    {
        return $this->hasOne(Wallet::class);
    }

    public function isProfessional(): bool
    {
        return $this->profile === UserProfile::Professional;
    }

    public function isCompany(): bool
    {
        return $this->profile === UserProfile::Company;
    }

    public function isAdmin(): bool
    {
        return $this->profile === UserProfile::Admin;
    }

    /**
     * Só perfil `admin` e conta ativa entram no painel Filament (`/admin`) — a mesma
     * regra de perfil exclusivo por conta do resto da aplicação, sem guard/tabela
     * separada só pra administradores.
     */
    public function canAccessPanel(Panel $panel): bool
    {
        return $this->isAdmin() && $this->is_active;
    }
}

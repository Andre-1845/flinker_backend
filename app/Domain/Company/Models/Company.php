<?php

namespace App\Domain\Company\Models;

use App\Domain\Flink\Models\Flink;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Company extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'cnpj',
        'responsible_name',
        'responsible_cpf',
        'phone',
        'address',
        'pix_key',
        'reputation',
    ];

    protected function casts(): array
    {
        return [
            'reputation' => 'decimal:2',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function flinks(): HasMany
    {
        return $this->hasMany(Flink::class);
    }
}

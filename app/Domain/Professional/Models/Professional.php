<?php

namespace App\Domain\Professional\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Professional extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'cpf',
        'phone',
        'address',
        'pix_key',
        'photo_url',
        'is_mei',
        'cnpj',
        'reputation',
    ];

    protected function casts(): array
    {
        return [
            'is_mei' => 'boolean',
            'reputation' => 'decimal:2',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}

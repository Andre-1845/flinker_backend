<?php

namespace App\Domain\Professional\Models;

use App\Domain\Match\Models\FlinkMatch;
use App\Domain\Schedule\Models\ScheduleBlock;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

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

    public function matches(): HasMany
    {
        return $this->hasMany(FlinkMatch::class);
    }

    public function scheduleBlocks(): HasMany
    {
        return $this->hasMany(ScheduleBlock::class);
    }
}

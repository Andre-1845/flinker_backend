<?php

namespace App\Domain\Rating\Models;

use App\Domain\Match\Models\FlinkMatch;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Rating extends Model
{
    use HasFactory;

    protected $fillable = [
        'match_id',
        'rater_user_id',
        'rated_user_id',
        'stars',
        'comment',
        'is_hidden',
    ];

    protected function casts(): array
    {
        return [
            'stars' => 'integer',
            'is_hidden' => 'boolean',
        ];
    }

    public function match(): BelongsTo
    {
        return $this->belongsTo(FlinkMatch::class, 'match_id');
    }

    public function rater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'rater_user_id');
    }

    public function rated(): BelongsTo
    {
        return $this->belongsTo(User::class, 'rated_user_id');
    }
}

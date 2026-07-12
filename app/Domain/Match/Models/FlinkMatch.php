<?php

namespace App\Domain\Match\Models;

use App\Domain\Flink\Models\Flink;
use App\Domain\Match\Enums\MatchStatus;
use App\Domain\Professional\Models\Professional;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FlinkMatch extends Model
{
    use HasFactory;

    protected $table = 'matches';

    protected $fillable = [
        'flink_id',
        'professional_id',
        'status',
        'checked_in_at',
        'checkin_latitude',
        'checkin_longitude',
    ];

    protected function casts(): array
    {
        return [
            'status' => MatchStatus::class,
            'checked_in_at' => 'datetime',
            'checkin_latitude' => 'decimal:7',
            'checkin_longitude' => 'decimal:7',
        ];
    }

    public function flink(): BelongsTo
    {
        return $this->belongsTo(Flink::class);
    }

    public function professional(): BelongsTo
    {
        return $this->belongsTo(Professional::class);
    }
}

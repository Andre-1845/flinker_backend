<?php

namespace App\Domain\Schedule\Models;

use App\Domain\Flink\Models\Flink;
use App\Domain\Professional\Models\Professional;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ScheduleBlock extends Model
{
    use HasFactory;

    protected $fillable = [
        'professional_id',
        'flink_id',
        'start_date_time',
        'end_date_time',
        'reason',
    ];

    protected function casts(): array
    {
        return [
            'start_date_time' => 'datetime',
            'end_date_time' => 'datetime',
        ];
    }

    public function professional(): BelongsTo
    {
        return $this->belongsTo(Professional::class);
    }

    public function flink(): BelongsTo
    {
        return $this->belongsTo(Flink::class);
    }

    /**
     * Bloqueios que conflitam com um intervalo de horário (sobreposição), opcionalmente
     * ignorando um bloqueio específico (útil ao reagendar/atualizar).
     */
    public function scopeOverlapping(Builder $query, string $start, string $end, ?int $ignoreId = null): Builder
    {
        return $query
            ->where('start_date_time', '<', $end)
            ->where('end_date_time', '>', $start)
            ->when($ignoreId, fn ($q) => $q->where('id', '!=', $ignoreId));
    }
}

<?php

namespace App\Domain\Flink\Models;

use App\Domain\Company\Models\Company;
use App\Domain\Flink\Enums\FlinkStatus;
use App\Domain\Match\Models\FlinkMatch;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Flink extends Model
{
    use HasFactory;

    protected $fillable = [
        'company_id',
        'activity_type',
        'location',
        'latitude',
        'longitude',
        'start_date_time',
        'end_date_time',
        'requirements',
        'status',
        'net_value',
        'platform_margin',
        'total_value',
    ];

    protected function casts(): array
    {
        return [
            'latitude' => 'decimal:7',
            'longitude' => 'decimal:7',
            'start_date_time' => 'datetime',
            'end_date_time' => 'datetime',
            'status' => FlinkStatus::class,
            'net_value' => 'decimal:2',
            'platform_margin' => 'decimal:2',
            'total_value' => 'decimal:2',
        ];
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function matches(): HasMany
    {
        return $this->hasMany(FlinkMatch::class);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', FlinkStatus::Open->value);
    }

    /**
     * Filtra Flinks dentro de um raio (em km) a partir de um ponto, usando a
     * fórmula de Haversine direto no banco. Simples e suficiente para o MVP —
     * se o volume crescer muito, vale migrar para PostGIS.
     */
    public function scopeNear(Builder $query, float $latitude, float $longitude, float $radiusKm): Builder
    {
        $haversine = '(6371 * acos(cos(radians(?)) * cos(radians(latitude)) *
                        cos(radians(longitude) - radians(?)) + sin(radians(?)) *
                        sin(radians(latitude))))';

        return $query
            ->selectRaw("flinks.*, {$haversine} AS distance_km", [$latitude, $longitude, $latitude])
            ->havingRaw('distance_km <= ?', [$radiusKm])
            ->orderBy('distance_km');
    }
}

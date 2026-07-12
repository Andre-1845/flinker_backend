<?php

namespace App\Domain\Match\Actions;

use App\Domain\Flink\Enums\FlinkStatus;
use App\Domain\Match\Enums\MatchStatus;
use App\Domain\Match\Models\FlinkMatch;
use App\Domain\Shared\Services\GeoDistanceService;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CheckInAction
{
    public function __construct(
        private readonly GeoDistanceService $geoDistanceService,
    ) {}

    public function handle(FlinkMatch $match, float $latitude, float $longitude): FlinkMatch
    {
        if ($match->status !== MatchStatus::Confirmed) {
            throw ValidationException::withMessages([
                'status' => 'Só é possível fazer check-in em um match já confirmado.',
            ]);
        }

        $flink = $match->flink;
        $radiusMeters = config('flinker.checkin_radius_meters');

        $distance = $this->geoDistanceService->distanceInMeters(
            (float) $flink->latitude,
            (float) $flink->longitude,
            $latitude,
            $longitude,
        );

        if ($distance > $radiusMeters) {
            throw ValidationException::withMessages([
                'location' => "Você está a ".round($distance)."m do local do Flink — o check-in exige estar a até {$radiusMeters}m.",
            ]);
        }

        return DB::transaction(function () use ($match, $flink, $latitude, $longitude) {
            $match->update([
                'checked_in_at' => now(),
                'checkin_latitude' => $latitude,
                'checkin_longitude' => $longitude,
            ]);

            $flink->update(['status' => FlinkStatus::InProgress]);

            return $match->fresh(['flink', 'professional']);
        });
    }
}

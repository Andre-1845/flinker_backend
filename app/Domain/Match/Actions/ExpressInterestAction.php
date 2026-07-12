<?php

namespace App\Domain\Match\Actions;

use App\Domain\Flink\Enums\FlinkStatus;
use App\Domain\Flink\Models\Flink;
use App\Domain\Match\Enums\MatchStatus;
use App\Domain\Match\Models\FlinkMatch;
use App\Domain\Professional\Models\Professional;
use Illuminate\Validation\ValidationException;

class ExpressInterestAction
{
    public function handle(Flink $flink, Professional $professional): FlinkMatch
    {
        if ($flink->status !== FlinkStatus::Open) {
            throw ValidationException::withMessages([
                'flink_id' => 'Este Flink não está mais aceitando interesse de profissionais.',
            ]);
        }

        $existing = FlinkMatch::query()
            ->where('flink_id', $flink->id)
            ->where('professional_id', $professional->id)
            ->first();

        if ($existing) {
            throw ValidationException::withMessages([
                'flink_id' => 'Você já demonstrou interesse neste Flink.',
            ]);
        }

        return FlinkMatch::create([
            'flink_id' => $flink->id,
            'professional_id' => $professional->id,
            'status' => MatchStatus::Pending,
        ]);
    }
}

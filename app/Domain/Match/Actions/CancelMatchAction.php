<?php

namespace App\Domain\Match\Actions;

use App\Domain\Flink\Enums\FlinkStatus;
use App\Domain\Match\Enums\MatchStatus;
use App\Domain\Match\Models\FlinkMatch;
use App\Domain\Schedule\Models\ScheduleBlock;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CancelMatchAction
{
    public function handle(FlinkMatch $match): FlinkMatch
    {
        if (in_array($match->status, [MatchStatus::Rejected, MatchStatus::Cancelled], true)) {
            throw ValidationException::withMessages([
                'status' => 'Este match já está encerrado.',
            ]);
        }

        return DB::transaction(function () use ($match) {
            $wasConfirmed = $match->status === MatchStatus::Confirmed;

            $match->update(['status' => MatchStatus::Cancelled]);

            if ($wasConfirmed) {
                // Libera o bloqueio de agenda e reabre o Flink para novos candidatos
                ScheduleBlock::where('flink_id', $match->flink_id)
                    ->where('professional_id', $match->professional_id)
                    ->delete();

                $match->flink->update(['status' => FlinkStatus::Open]);
            }

            return $match->fresh(['flink', 'professional']);
        });
    }
}

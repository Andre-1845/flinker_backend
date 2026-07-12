<?php

namespace App\Domain\Match\Actions;

use App\Domain\Flink\Enums\FlinkStatus;
use App\Domain\Match\Enums\MatchStatus;
use App\Domain\Match\Models\FlinkMatch;
use App\Domain\Schedule\Models\ScheduleBlock;
use App\Domain\Schedule\Services\ScheduleConflictChecker;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ConfirmMatchAction
{
    public function __construct(
        private readonly ScheduleConflictChecker $conflictChecker,
    ) {}

    public function handle(FlinkMatch $match): FlinkMatch
    {
        if ($match->status !== MatchStatus::Accepted) {
            throw ValidationException::withMessages([
                'status' => 'Este match ainda não foi aceito pela empresa, ou já foi confirmado/cancelado.',
            ]);
        }

        $flink = $match->flink;

        if ($this->conflictChecker->hasConflict($match->professional_id, $flink->start_date_time, $flink->end_date_time)) {
            throw ValidationException::withMessages([
                'schedule' => 'Você já tem um compromisso confirmado que conflita com este horário.',
            ]);
        }

        return DB::transaction(function () use ($match, $flink) {
            $match->update(['status' => MatchStatus::Confirmed]);
            $flink->update(['status' => FlinkStatus::Confirmed]);

            ScheduleBlock::create([
                'professional_id' => $match->professional_id,
                'flink_id' => $flink->id,
                'start_date_time' => $flink->start_date_time,
                'end_date_time' => $flink->end_date_time,
                'reason' => 'Flink confirmado: '.$flink->activity_type,
            ]);

            return $match->fresh(['flink', 'professional']);
        });
    }
}

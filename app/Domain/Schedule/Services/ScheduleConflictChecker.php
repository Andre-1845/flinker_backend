<?php

namespace App\Domain\Schedule\Services;

use App\Domain\Schedule\Models\ScheduleBlock;

class ScheduleConflictChecker
{
    public function hasConflict(int $professionalId, string $start, string $end, ?int $ignoreBlockId = null): bool
    {
        return ScheduleBlock::query()
            ->where('professional_id', $professionalId)
            ->overlapping($start, $end, $ignoreBlockId)
            ->exists();
    }
}

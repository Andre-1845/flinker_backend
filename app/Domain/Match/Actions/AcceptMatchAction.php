<?php

namespace App\Domain\Match\Actions;

use App\Domain\Flink\Enums\FlinkStatus;
use App\Domain\Match\Enums\MatchStatus;
use App\Domain\Match\Models\FlinkMatch;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class AcceptMatchAction
{
    public function handle(FlinkMatch $match): FlinkMatch
    {
        if ($match->status !== MatchStatus::Pending) {
            throw ValidationException::withMessages([
                'status' => 'Este candidato não está mais com interesse pendente.',
            ]);
        }

        return DB::transaction(function () use ($match) {
            $match->update(['status' => MatchStatus::Accepted]);

            // Regra de desempate (Fase 2/3, decisão registrada em docs/ARCHITECTURE.md):
            // ao aceitar um candidato, os demais pendentes no mesmo Flink são rejeitados automaticamente.
            FlinkMatch::query()
                ->where('flink_id', $match->flink_id)
                ->where('id', '!=', $match->id)
                ->where('status', MatchStatus::Pending)
                ->update(['status' => MatchStatus::Rejected]);

            $match->flink->update(['status' => FlinkStatus::Matched]);

            return $match->fresh(['flink', 'professional']);
        });
    }
}

<?php

namespace App\Http\Controllers\Api;

use App\Domain\Schedule\Models\ScheduleBlock;
use App\Domain\Schedule\Services\ScheduleConflictChecker;
use App\Http\Controllers\Controller;
use App\Http\Requests\Schedule\BlockScheduleRequest;
use App\Http\Resources\ScheduleBlockResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class ScheduleController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        abort_unless($request->user()->isProfessional(), 403, 'Apenas profissionais têm agenda.');

        $blocks = $request->user()->professional
            ->scheduleBlocks()
            ->orderBy('start_date_time')
            ->get();

        return response()->json(['data' => ScheduleBlockResource::collection($blocks)]);
    }

    public function block(BlockScheduleRequest $request, ScheduleConflictChecker $conflictChecker): JsonResponse
    {
        $professional = $request->user()->professional;
        abort_unless($professional, 422, 'Complete o cadastro de profissional antes de bloquear a agenda.');

        $data = $request->validated();

        if ($conflictChecker->hasConflict($professional->id, $data['start_date_time'], $data['end_date_time'])) {
            throw ValidationException::withMessages([
                'schedule' => 'Já existe um bloqueio ou compromisso nesse intervalo de horário.',
            ]);
        }

        $block = ScheduleBlock::create([
            'professional_id' => $professional->id,
            'flink_id' => null,
            'start_date_time' => $data['start_date_time'],
            'end_date_time' => $data['end_date_time'],
            'reason' => $data['reason'] ?? 'Bloqueio manual',
        ]);

        return response()->json(['data' => new ScheduleBlockResource($block)], 201);
    }
}

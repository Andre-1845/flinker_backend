<?php

namespace App\Http\Controllers\Api;

use App\Domain\Match\Enums\MatchStatus;
use App\Domain\Match\Models\FlinkMatch;
use App\Domain\Match\Models\Message;
use App\Domain\Match\Services\ChatContentFilter;
use App\Http\Controllers\Controller;
use App\Http\Resources\MessageResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/**
 * Chat de um match (achado #6 da auditoria 2026-10-01). Uma conversa por
 * match, liberada só com MatchStatus::Confirmed ("aceite bilateral" — ver
 * MatchStatus::label()). Entrega via polling simples (sem WebSocket/Reverb):
 * o frontend chama index() periodicamente passando after_id pra buscar só
 * mensagens novas.
 */
class MessageController extends Controller
{
    public function index(Request $request, FlinkMatch $match): JsonResponse
    {
        $this->authorizeParticipant($request, $match);
        $this->ensureChatUnlocked($match);

        $query = Message::query()->where('match_id', $match->id)->orderBy('id');

        if ($request->filled('after_id')) {
            $query->where('id', '>', (int) $request->query('after_id'));
        }

        $messages = $query->get();

        return response()->json(['data' => MessageResource::collection($messages)]);
    }

    public function store(Request $request, FlinkMatch $match, ChatContentFilter $filter): JsonResponse
    {
        $this->authorizeParticipant($request, $match);
        $this->ensureChatUnlocked($match);

        $data = $request->validate([
            'body' => ['required', 'string', 'max:2000'],
        ]);

        if ($filter->isBlocked($data['body'])) {
            throw ValidationException::withMessages([
                'body' => $filter->warningMessage(),
            ]);
        }

        $message = Message::create([
            'match_id' => $match->id,
            'sender_user_id' => $request->user()->id,
            'body' => $data['body'],
        ]);

        return response()->json(['data' => new MessageResource($message)], 201);
    }

    public function markRead(Request $request, FlinkMatch $match): JsonResponse
    {
        $this->authorizeParticipant($request, $match);

        Message::query()
            ->where('match_id', $match->id)
            ->where('sender_user_id', '!=', $request->user()->id)
            ->whereNull('read_at')
            ->update(['read_at' => now()]);

        return response()->json(['data' => true]);
    }

    /**
     * Resumo de todas as conversas do usuário logado (última mensagem +
     * contagem de não lidas por match) — usado pra montar a lista de
     * conversas e o badge de não lidas sem precisar de N chamadas.
     */
    public function conversationsSummary(Request $request): JsonResponse
    {
        $user = $request->user();

        $matchIds = FlinkMatch::query()
            ->when($user->isProfessional(), fn ($q) => $q->where('professional_id', $user->professional->id))
            ->when($user->isCompany(), fn ($q) => $q->whereHas('flink', fn ($q2) => $q2->where('company_id', $user->company->id)))
            ->where('status', MatchStatus::Confirmed)
            ->pluck('id');

        $lastMessageByMatch = Message::query()
            ->whereIn('match_id', $matchIds)
            ->orderByDesc('id')
            ->get(['id', 'match_id', 'body', 'created_at'])
            ->unique('match_id')
            ->keyBy('match_id');

        $unreadByMatch = Message::query()
            ->whereIn('match_id', $matchIds)
            ->where('sender_user_id', '!=', $user->id)
            ->whereNull('read_at')
            ->selectRaw('match_id, count(*) as total')
            ->groupBy('match_id')
            ->pluck('total', 'match_id');

        $summary = $matchIds->mapWithKeys(function ($matchId) use ($lastMessageByMatch, $unreadByMatch) {
            $last = $lastMessageByMatch->get($matchId);

            return [
                $matchId => [
                    'last_message' => $last?->body,
                    'last_message_at' => $last?->created_at,
                    'unread_count' => (int) ($unreadByMatch->get($matchId) ?? 0),
                ],
            ];
        });

        return response()->json(['data' => $summary]);
    }

    private function authorizeParticipant(Request $request, FlinkMatch $match): void
    {
        $user = $request->user();

        abort_unless(
            $user->isAdmin()
                || ($user->isProfessional() && $user->professional?->id === $match->professional_id)
                || ($user->isCompany() && $user->company?->id === $match->flink->company_id),
            403,
            'Você não tem permissão para acessar esta conversa.'
        );
    }

    private function ensureChatUnlocked(FlinkMatch $match): void
    {
        abort_unless(
            $match->status === MatchStatus::Confirmed,
            403,
            'O chat só é liberado depois do aceite mútuo do match.'
        );
    }
}

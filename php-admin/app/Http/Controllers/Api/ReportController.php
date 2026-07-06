<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\GameResult;
use App\Models\GameSession;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ReportController extends Controller
{
    public function sessions(Request $request): JsonResponse
    {
        $query = GameSession::query()
            ->with(['game:id,name', 'host:id,name'])
            ->where('status', 'ended')
            ->orderByDesc('ended_at');

        if ($request->filled('game_id')) {
            $query->where('game_id', $request->integer('game_id'));
        }

        if ($request->filled('date_from')) {
            $query->whereDate('ended_at', '>=', $request->string('date_from'));
        }

        if ($request->filled('date_to')) {
            $query->whereDate('ended_at', '<=', $request->string('date_to'));
        }

        $sessions = $query->paginate($request->integer('per_page', 20));

        return $this->jsonSuccess(['sessions' => $sessions]);
    }

    public function sessionDetail(GameSession $session): JsonResponse
    {
        $session->load([
            'game',
            'host:id,name,email',
            'results' => fn ($q) => $q->orderBy('rank'),
            'answers.question:id,content,answer_type',
        ]);

        return $this->jsonSuccess(['session' => $session]);
    }

    public function studentAggregate(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'student_name' => ['required', 'string', 'max:20'],
            'game_id' => ['nullable', 'integer', 'exists:games,id'],
        ]);

        $query = GameResult::query()
            ->select([
                'game_results.student_name',
                DB::raw('COUNT(*) as sessions_played'),
                DB::raw('SUM(game_results.score) as total_score'),
                DB::raw('AVG(game_results.score) as average_score'),
                DB::raw('MIN(game_results.rank) as best_rank'),
            ])
            ->join('game_sessions', 'game_sessions.id', '=', 'game_results.session_id')
            ->where('game_results.student_name', $validated['student_name'])
            ->where('game_sessions.status', 'ended');

        if (! empty($validated['game_id'])) {
            $query->where('game_sessions.game_id', $validated['game_id']);
        }

        $aggregate = $query
            ->groupBy('game_results.student_name')
            ->first();

        if (! $aggregate) {
            return $this->jsonError('Không tìm thấy kết quả cho học sinh này.', 404);
        }

        $history = GameResult::query()
            ->with(['session:id,pin,game_id,ended_at', 'session.game:id,name'])
            ->where('student_name', $validated['student_name'])
            ->whereHas('session', function ($q) use ($validated) {
                $q->where('status', 'ended');
                if (! empty($validated['game_id'])) {
                    $q->where('game_id', $validated['game_id']);
                }
            })
            ->orderByDesc('created_at')
            ->get();

        return $this->jsonSuccess([
            'aggregate' => $aggregate,
            'history' => $history,
        ]);
    }
}

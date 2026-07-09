<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Game;
use App\Models\GameResult;
use App\Models\GameSession;
use App\Models\Keyboard;
use App\Models\Question;
use App\Models\QuestionBankItem;
use App\Models\Quiz;
use App\Models\SiteFeedback;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class DashboardController extends Controller
{
    public function index(Request $request): View
    {
        $user = $request->user();
        $isAdmin = $user->isAdmin();

        $sessionBase = $this->sessionQuery($user);
        $endedBase = (clone $sessionBase)->where('status', 'ended');

        $stats = [
            'keyboards' => Keyboard::count(),
            'games' => Game::count(),
            'quizzes' => Quiz::count(),
            'questions' => Question::count(),
            'question_bank' => QuestionBankItem::count(),
            'sessions_total' => (clone $sessionBase)->count(),
            'sessions_active' => (clone $sessionBase)->where('is_active', true)->count(),
            'sessions_waiting' => (clone $sessionBase)->where('status', 'waiting')->count(),
            'sessions_playing' => (clone $sessionBase)->where('status', 'playing')->count(),
            'sessions_ended' => (clone $sessionBase)->where('status', 'ended')->count(),
            'players_total' => GameResult::query()
                ->when(! $isAdmin, fn (Builder $q) => $q->whereIn('session_id', (clone $sessionBase)->select('id')))
                ->count(),
            'feedback_new' => $isAdmin ? SiteFeedback::where('status', 'new')->count() : SiteFeedback::where('user_id', $user->id)->where('status', 'new')->count(),
        ];

        $statusLabels = [
            'waiting' => 'Chờ',
            'playing' => 'Đang chơi',
            'ended' => 'Kết thúc',
        ];
        $statusCounts = (clone $sessionBase)
            ->selectRaw('status, COUNT(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status');

        $chartStatus = [
            'labels' => [],
            'values' => [],
            'colors' => ['#94a3b8', '#2d46d6', '#16a34a'],
        ];
        foreach (['waiting', 'playing', 'ended'] as $status) {
            $chartStatus['labels'][] = $statusLabels[$status];
            $chartStatus['values'][] = (int) ($statusCounts[$status] ?? 0);
        }

        $chartSessionsDaily = $this->sessionsPerDay($sessionBase, 14);

        $topGames = (clone $endedBase)
            ->selectRaw('game_id, COUNT(*) as total')
            ->whereNotNull('game_id')
            ->groupBy('game_id')
            ->orderByDesc('total')
            ->limit(5)
            ->get()
            ->map(function ($row) {
                $game = Game::query()->find($row->game_id);

                return [
                    'name' => $game?->name ?? 'Game #'.$row->game_id,
                    'total' => (int) $row->total,
                ];
            });

        $recentSessions = (clone $sessionBase)
            ->with(['game:id,name', 'quiz:id,name', 'host:id,name'])
            ->orderByDesc('created_at')
            ->limit(8)
            ->get();

        $recentReports = (clone $endedBase)
            ->with(['game:id,name'])
            ->orderByDesc('ended_at')
            ->limit(5)
            ->get();

        return view('admin.dashboard', [
            'isAdmin' => $isAdmin,
            'user' => $user,
            'stats' => $stats,
            'chartStatus' => $chartStatus,
            'chartSessionsDaily' => $chartSessionsDaily,
            'topGames' => $topGames,
            'recentSessions' => $recentSessions,
            'recentReports' => $recentReports,
        ]);
    }

    public function export(Request $request): StreamedResponse
    {
        $user = $request->user();
        $isAdmin = $user->isAdmin();
        $sessionBase = $this->sessionQuery($user);

        $stats = [
            'Bàn phím' => Keyboard::count(),
            'Game' => Game::count(),
            'Quiz' => Quiz::count(),
            'Câu hỏi quiz' => Question::count(),
            'Bộ câu hỏi' => QuestionBankItem::count(),
            'Tổng phòng' => (clone $sessionBase)->count(),
            'Phòng đang bật' => (clone $sessionBase)->where('is_active', true)->count(),
            'Phòng đã kết thúc' => (clone $sessionBase)->where('status', 'ended')->count(),
            'Lượt chơi (HS)' => GameResult::query()
                ->when(! $isAdmin, fn (Builder $q) => $q->whereIn('session_id', (clone $sessionBase)->select('id')))
                ->count(),
        ];

        $endedSessions = (clone $sessionBase)
            ->with(['game:id,name', 'host:id,name'])
            ->withCount('results')
            ->where('status', 'ended')
            ->orderByDesc('ended_at')
            ->limit(200)
            ->get();

        $filename = 'tong-quan-admin-'.now()->format('Ymd-His').'.csv';

        return response()->streamDownload(function () use ($stats, $endedSessions, $user, $isAdmin) {
            $out = fopen('php://output', 'w');
            fprintf($out, chr(0xEF).chr(0xBB).chr(0xBF));

            fputcsv($out, ['Báo cáo tổng quan — Hóa Thầy Đạt']);
            fputcsv($out, ['Xuất lúc', now()->format('Y-m-d H:i:s')]);
            fputcsv($out, ['Người xuất', $user->name.' ('.$user->email.')']);
            fputcsv($out, ['Phạm vi', $isAdmin ? 'Toàn hệ thống' : 'Phòng của tôi']);
            fputcsv($out, []);

            fputcsv($out, ['Chỉ số', 'Giá trị']);
            foreach ($stats as $label => $value) {
                fputcsv($out, [$label, $value]);
            }

            fputcsv($out, []);
            fputcsv($out, ['Phiên đã kết thúc (tối đa 200 bản ghi gần nhất)']);
            fputcsv($out, ['PIN', 'Tên phòng', 'Game', 'Giáo viên', 'Kết thúc lúc', 'Số HS']);

            foreach ($endedSessions as $session) {
                fputcsv($out, [
                    $session->pin,
                    $session->name ?? '',
                    $session->game?->name ?? '',
                    $session->host?->name ?? '',
                    $session->ended_at?->format('Y-m-d H:i:s') ?? '',
                    $session->results_count,
                ]);
            }

            fclose($out);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    private function sessionQuery(User $user): Builder
    {
        $query = GameSession::query();

        if (! $user->isAdmin()) {
            $query->where('host_id', $user->id);
        }

        return $query;
    }

    /**
     * @return array{labels: list<string>, values: list<int>}
     */
    private function sessionsPerDay(Builder $sessionBase, int $days): array
    {
        $start = now()->subDays($days - 1)->startOfDay();
        $rows = (clone $sessionBase)
            ->where('created_at', '>=', $start)
            ->selectRaw('DATE(created_at) as day, COUNT(*) as total')
            ->groupBy('day')
            ->orderBy('day')
            ->pluck('total', 'day');

        $labels = [];
        $values = [];

        for ($i = 0; $i < $days; $i++) {
            $date = $start->copy()->addDays($i);
            $key = $date->format('Y-m-d');
            $labels[] = $date->format('d/m');
            $values[] = (int) ($rows[$key] ?? 0);
        }

        return ['labels' => $labels, 'values' => $values];
    }
}

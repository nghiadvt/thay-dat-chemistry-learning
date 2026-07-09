<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Game;
use App\Models\GameSession;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ReportController extends Controller
{
    public function index(Request $request): View
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

        $search = trim((string) $request->input('q', ''));
        if ($search !== '') {
            $query->where(function ($builder) use ($search) {
                $builder->where('pin', 'like', '%'.$search.'%')
                    ->orWhere('name', 'like', '%'.$search.'%');
            });
        }

        return view('admin.reports.index', [
            'sessions' => $query->paginate(20)->withQueryString(),
            'games' => Game::orderBy('name')->get(),
            'search' => $search,
        ]);
    }

    public function show(GameSession $session): View
    {
        $session->load([
            'game',
            'host:id,name,email',
            'results' => fn ($q) => $q->orderBy('rank'),
            'answers.question:id,content,answer_type',
        ]);

        return view('admin.reports.show', compact('session'));
    }

    public function export(GameSession $session): StreamedResponse
    {
        $session->load([
            'game:id,name',
            'results' => fn ($q) => $q->orderBy('rank'),
        ]);

        $filename = 'session-'.$session->pin.'-'.now()->format('Ymd').'.csv';

        return response()->streamDownload(function () use ($session) {
            $out = fopen('php://output', 'w');
            fprintf($out, chr(0xEF).chr(0xBB).chr(0xBF));

            fputcsv($out, ['PIN', $session->pin]);
            fputcsv($out, ['Game', $session->game?->name]);
            fputcsv($out, ['Kết thúc', $session->ended_at?->format('Y-m-d H:i:s')]);
            fputcsv($out, []);

            fputcsv($out, ['Hạng', 'Học sinh', 'Điểm', 'Player token']);

            foreach ($session->results as $result) {
                fputcsv($out, [
                    $result->rank,
                    $result->student_name,
                    $result->score,
                    $result->player_token ?? '',
                ]);
            }

            fclose($out);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }
}

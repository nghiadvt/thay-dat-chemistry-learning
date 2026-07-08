<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Game;
use App\Models\GameSession;
use App\Models\Keyboard;
use App\Models\Question;
use App\Models\Quiz;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function index(): View
    {
        return view('admin.dashboard', [
            'stats' => [
                'keyboards' => Keyboard::count(),
                'games' => Game::count(),
                'quizzes' => Quiz::count(),
                'questions' => Question::count(),
            ],
            'recentSessions' => GameSession::query()
                ->with(['game:id,name', 'quiz:id,name'])
                ->orderByDesc('created_at')
                ->limit(5)
                ->get(),
        ]);
    }
}

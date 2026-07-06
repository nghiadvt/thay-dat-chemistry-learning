<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Game;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class GameController extends Controller
{
    public function index(): View
    {
        $games = Game::query()
            ->withCount('quizzes')
            ->orderByDesc('updated_at')
            ->get();

        return view('admin.games.index', compact('games'));
    }

    public function create(): View
    {
        return view('admin.games.form', ['game' => null]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
        ]);

        Game::create([
            ...$validated,
            'created_by' => Auth::id(),
        ]);

        return redirect()->route('admin.games.index')
            ->with('success', 'Đã tạo game.');
    }

    public function edit(Game $game): View
    {
        return view('admin.games.form', compact('game'));
    }

    public function update(Request $request, Game $game): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
        ]);

        $game->update($validated);

        return redirect()->route('admin.games.index')
            ->with('success', 'Đã cập nhật game.');
    }

    public function destroy(Game $game): RedirectResponse
    {
        if ($game->quizzes()->exists()) {
            return back()->with('error', 'Không thể xóa game còn quiz. Xóa hoặc chuyển quiz trước.');
        }

        $game->delete();

        return redirect()->route('admin.games.index')
            ->with('success', 'Đã xóa game.');
    }
}
